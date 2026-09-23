<?php

namespace App\Fuentes;

use App\Dominio\Isbn;
use App\Dominio\IsbnInvalido;
use App\Enums;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * La primera Fuente de la cascada por ISBN y el respaldo en el Alta por título
 * (ADR-002).
 *
 * Open Library guarda la descripción en la obra, no en la edición: el registro
 * de `/isbn/<isbn>.json` sólo apunta a ella con `works[0].key`, así que hace
 * falta una segunda petición. Eso no introduce el concepto de obra —seguimos
 * sin tenerlo—; es nada más dónde está guardado el dato.
 */
final class OpenLibrary implements Fuente
{
    /** Cuántos títulos se piden en un Alta por título. */
    private const MAXIMO_DE_CANDIDATOS = 20;

    /**
     * Lo que se le pide a `/search.json`. Sin `fields` contesta el documento
     * entero de cada obra —cientos de claves de edición en un solo arreglo— y
     * sin `editions.*` no baja nada del nivel de la edición, que es lo único
     * que sirve para elegir una.
     */
    private const CAMPOS_DE_BUSQUEDA = 'key,title,author_name,cover_i,'
        .'editions,editions.key,editions.title,editions.author_name,'
        .'editions.publisher,editions.publish_date,editions.isbn,editions.cover_i';

    public function __construct(
        private readonly string $url,
        private readonly string $portadasUrl,
        private readonly int $timeout,
    ) {}

    public function identificador(): Enums\Fuente
    {
        return Enums\Fuente::OpenLibrary;
    }

    public function porIsbn(Isbn $isbn): Respuesta
    {
        try {
            $edicion = $this->json("/isbn/{$isbn->valor()}.json");
        } catch (FuenteNoDisponible $falla) {
            return Respuesta::noDisponible($falla->getMessage());
        }

        $titulo = $this->titulo($edicion);

        if ($edicion === null || $titulo === null) {
            return Respuesta::sinFicha();
        }

        try {
            $autores = $this->autores($edicion);
        } catch (FuenteNoDisponible $falla) {
            return Respuesta::noDisponible($falla->getMessage());
        }

        return Respuesta::conFicha(new Ficha(
            isbn: $isbn,
            titulo: $titulo,
            autores: $autores,
            editorial: $this->cadena($this->primero($edicion['publishers'] ?? null)),
            anio: $this->anio($edicion['publish_date'] ?? null),
            edicion: $this->cadena($edicion['edition_name'] ?? null),
            portadaUrl: $this->portada($edicion),
            descripcion: $this->descripcion($edicion),
            fuente: $this->identificador(),
        ));
    }

    /**
     * El respaldo del Alta por título, y por eso es el respaldo: `/search.json`
     * busca **obras**, no ediciones, y de cada obra devuelve una sola edición
     * —la que ella elija— sin forma de pedirle más. Comprobado el 2026-09-23
     * contra el catálogo real: «Pedro Páramo» de Rulfo da 6 obras con 134
     * ediciones entre todas, y contesta 6 Candidatos; el de la primera obra no
     * trae ISBN y el de la segunda es una traducción al portugués. Para el Alta
     * por título eso no alcanza —la pantalla existe para distinguir la de
     * bolsillo de la del 2015—, pero sirve de red cuando la otra Fuente no
     * pudo contestar.
     */
    public function porTitulo(string $titulo, ?string $autor = null): Candidatos
    {
        $titulo = trim($titulo);

        if ($titulo === '') {
            return Candidatos::ninguno();
        }

        $parametros = [
            'title' => $titulo,
            'limit' => self::MAXIMO_DE_CANDIDATOS,
            'fields' => self::CAMPOS_DE_BUSQUEDA,
        ];

        $autor = $autor === null ? '' : trim($autor);

        if ($autor !== '') {
            $parametros['author'] = $autor;
        }

        try {
            $cuerpo = $this->json('/search.json', $parametros);
        } catch (FuenteNoDisponible $falla) {
            return Candidatos::noDisponible($falla->getMessage());
        }

        $obras = is_array($cuerpo['docs'] ?? null) ? $cuerpo['docs'] : [];
        $candidatos = [];

        foreach ($obras as $obra) {
            $candidato = is_array($obra) ? $this->candidato($obra) : null;

            if ($candidato !== null) {
                $candidatos[] = $candidato;
            }
        }

        return $candidatos === [] ? Candidatos::ninguno() : Candidatos::con($candidatos);
    }

    /**
     * El Candidato sale de la edición que la obra trajo adentro; lo que a ésa
     * le falte se completa con la obra, que al menos tiene título y autores.
     *
     * @param  array<string, mixed>  $obra
     */
    private function candidato(array $obra): ?Candidato
    {
        $ediciones = is_array($obra['editions'] ?? null) ? $obra['editions'] : [];
        $documentos = is_array($ediciones['docs'] ?? null) ? $ediciones['docs'] : [];
        $primera = $this->primero($documentos);
        $edicion = is_array($primera) ? $primera : [];

        $titulo = $this->cadena($edicion['title'] ?? null) ?? $this->cadena($obra['title'] ?? null);

        if ($titulo === null) {
            return null;
        }

        $portada = $edicion['cover_i'] ?? $obra['cover_i'] ?? null;

        return new Candidato(
            isbn: $this->isbnDeLaEdicion($edicion),
            titulo: $titulo,
            autores: $this->acreditados($edicion) ?: $this->acreditados($obra),
            editorial: $this->cadena($this->primero($edicion['publisher'] ?? null)),
            // El año de la obra no es el de la edición: una edición sin fecha
            // se muestra sin año, y no con el de la primera publicación.
            anio: $this->anio($this->primero($edicion['publish_date'] ?? null)),
            edicion: null,
            portadaUrl: is_int($portada) && $portada > 0 ? "{$this->portadasUrl}/b/id/{$portada}-L.jpg" : null,
            // La Descripción vive en la obra y cada una cuesta otra petición;
            // en una lista de veinte no se pagan, y quien capture no la ve ahí.
            descripcion: null,
            fuente: $this->identificador(),
            referencia: $this->cadena($edicion['key'] ?? null) ?? $this->cadena($obra['key'] ?? null),
        );
    }

    /**
     * El primer ISBN que cuadre, prefiriendo el de trece. Open Library los
     * publica mezclados y sin marcar cuál es cuál, y algunos vienen mal.
     *
     * @param  array<string, mixed>  $edicion
     */
    private function isbnDeLaEdicion(array $edicion): ?Isbn
    {
        $publicados = is_array($edicion['isbn'] ?? null) ? $edicion['isbn'] : [];
        $porLargo = [];

        foreach ($publicados as $publicado) {
            $valor = $this->cadena($publicado);

            if ($valor !== null) {
                $porLargo[strlen($valor) >= 13 ? 0 : 1][] = $valor;
            }
        }

        foreach (array_merge($porLargo[0] ?? [], $porLargo[1] ?? []) as $valor) {
            try {
                return Isbn::desde($valor);
            } catch (IsbnInvalido) {
                continue;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $documento
     * @return list<string>
     */
    private function acreditados(array $documento): array
    {
        $nombres = is_array($documento['author_name'] ?? null) ? $documento['author_name'] : [];
        $autores = [];

        foreach ($nombres as $nombre) {
            $limpio = $this->cadena($nombre);

            if ($limpio !== null) {
                $autores[] = $limpio;
            }
        }

        return $autores;
    }

    /**
     * Los autores vienen como enlaces, así que cada nombre es una petición más.
     * Si alguna no se puede hacer, la consulta entera se da por no respondida:
     * una Ficha a la que le faltan autores en silencio es peor que reintentar.
     *
     * @param  array<string, mixed>  $edicion
     * @return list<string>
     */
    private function autores(array $edicion): array
    {
        $autores = [];
        $enlaces = is_array($edicion['authors'] ?? null) ? $edicion['authors'] : [];

        foreach ($enlaces as $enlace) {
            $clave = is_array($enlace) ? $this->cadena($enlace['key'] ?? null) : null;

            if ($clave === null) {
                continue;
            }

            $autor = $this->json("{$clave}.json");
            $nombre = $this->cadena($autor['name'] ?? $autor['personal_name'] ?? null);

            if ($nombre !== null) {
                $autores[] = $nombre;
            }
        }

        return $autores;
    }

    /**
     * La Descripción vive en la obra. Si esa segunda petición no se puede
     * hacer, la Ficha sale sin Descripción en vez de fallar: una Ficha sin
     * Descripción es válida y la IA la escribe después (ADR-007).
     *
     * En la obra, `description` a veces es una cadena y a veces un objeto con
     * `value`.
     *
     * @param  array<string, mixed>  $edicion
     */
    private function descripcion(array $edicion): ?string
    {
        $obras = is_array($edicion['works'] ?? null) ? $edicion['works'] : [];
        $primera = $this->primero($obras);
        $clave = is_array($primera) ? $this->cadena($primera['key'] ?? null) : null;

        if ($clave === null) {
            return null;
        }

        try {
            $obra = $this->json("{$clave}.json");
        } catch (FuenteNoDisponible) {
            return null;
        }

        $descripcion = $obra['description'] ?? null;

        if (is_array($descripcion)) {
            $descripcion = $descripcion['value'] ?? null;
        }

        return $this->cadena($descripcion);
    }

    /** @param  array<string, mixed>|null  $edicion */
    private function titulo(?array $edicion): ?string
    {
        if ($edicion === null) {
            return null;
        }

        $titulo = $this->cadena($edicion['title'] ?? null);
        $subtitulo = $this->cadena($edicion['subtitle'] ?? null);

        if ($titulo === null) {
            return null;
        }

        return $subtitulo === null ? $titulo : "{$titulo}: {$subtitulo}";
    }

    /** @param  array<string, mixed>  $edicion */
    private function portada(array $edicion): ?string
    {
        $portadas = is_array($edicion['covers'] ?? null) ? $edicion['covers'] : [];

        foreach ($portadas as $identificador) {
            // Open Library marca con -1 las portadas que no tiene.
            if (is_int($identificador) && $identificador > 0) {
                return "{$this->portadasUrl}/b/id/{$identificador}-L.jpg";
            }
        }

        return null;
    }

    private function anio(mixed $publicacion): ?int
    {
        $texto = $this->cadena($publicacion);

        if ($texto === null || preg_match('/\b(\d{4})\b/', $texto, $coincidencia) !== 1) {
            return null;
        }

        return (int) $coincidencia[1];
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @return array<string, mixed>|null null cuando Open Library contesta que no conoce el recurso.
     *
     * @throws FuenteNoDisponible
     */
    private function json(string $ruta, array $parametros = []): ?array
    {
        try {
            $respuesta = Http::acceptJson()
                ->timeout($this->timeout)
                ->get($this->url.$ruta, $parametros);
        } catch (ConnectionException $falla) {
            throw new FuenteNoDisponible('Open Library no respondió: '.$falla->getMessage(), previous: $falla);
        }

        if ($respuesta->notFound()) {
            return null;
        }

        if (! $respuesta->successful()) {
            throw new FuenteNoDisponible("Open Library respondió HTTP {$respuesta->status()}.");
        }

        $cuerpo = $respuesta->json();

        if (! is_array($cuerpo)) {
            throw new FuenteNoDisponible('Open Library respondió algo que no es JSON.');
        }

        return $cuerpo;
    }

    private function primero(mixed $lista): mixed
    {
        if (! is_array($lista) || $lista === []) {
            return null;
        }

        return reset($lista);
    }

    private function cadena(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $limpio = trim($valor);

        return $limpio === '' ? null : $limpio;
    }
}
