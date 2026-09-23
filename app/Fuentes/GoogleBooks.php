<?php

namespace App\Fuentes;

use App\Dominio\Isbn;
use App\Dominio\IsbnInvalido;
use App\Enums;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * El respaldo de la cascada por ISBN y la primera en el Alta por título
 * (ADR-002), y la única de las dos que trae la Descripción en la misma
 * petición.
 *
 * La llave se lee del entorno. Sin llave el adaptador funciona igual, pero
 * queda expuesto a la cuota compartida; cuando ésa se agota, Google contesta
 * HTTP 429, y eso se traduce a «la Fuente no pudo contestar», nunca a «no lo
 * conoce».
 */
final class GoogleBooks implements Fuente
{
    /** Cuántas ediciones se piden en un Alta por título. */
    private const MAXIMO_DE_CANDIDATOS = 20;

    public function __construct(
        private readonly string $url,
        private readonly ?string $llave,
        private readonly int $timeout,
    ) {}

    public function identificador(): Enums\Fuente
    {
        return Enums\Fuente::GoogleBooks;
    }

    public function porIsbn(Isbn $isbn): Respuesta
    {
        try {
            $cuerpo = $this->consultar(['q' => "isbn:{$isbn->valor()}"]);
        } catch (FuenteNoDisponible $falla) {
            return Respuesta::noDisponible($falla->getMessage());
        }

        $informacion = $this->informacion($this->primerVolumen($cuerpo));
        $titulo = $informacion === null ? null : $this->titulo($informacion);

        if ($informacion === null || $titulo === null) {
            return Respuesta::sinFicha();
        }

        return Respuesta::conFicha(new Ficha(
            isbn: $isbn,
            titulo: $titulo,
            autores: $this->autores($informacion),
            editorial: $this->cadena($informacion['publisher'] ?? null),
            anio: $this->anio($informacion['publishedDate'] ?? null),
            // Google Books no publica el nombre de la edición.
            edicion: null,
            portadaUrl: $this->portada($informacion),
            descripcion: $this->descripcion($informacion),
            fuente: $this->identificador(),
        ));
    }

    /**
     * `intitle:` e `inauthor:` acotan cada término a su campo. Sin eso, el
     * autor acaba trayendo todo lo que lo menciona en la Descripción, que es
     * ruido puro cuando lo que se quiere es elegir una edición.
     */
    public function porTitulo(string $titulo, ?string $autor = null): Candidatos
    {
        $titulo = trim($titulo);

        if ($titulo === '') {
            return Candidatos::ninguno();
        }

        $consulta = 'intitle:'.$this->entrecomillado($titulo);
        $autor = $autor === null ? '' : trim($autor);

        if ($autor !== '') {
            $consulta .= ' inauthor:'.$this->entrecomillado($autor);
        }

        try {
            $cuerpo = $this->consultar([
                'q' => $consulta,
                'maxResults' => self::MAXIMO_DE_CANDIDATOS,
                'printType' => 'books',
            ]);
        } catch (FuenteNoDisponible $falla) {
            return Candidatos::noDisponible($falla->getMessage());
        }

        $items = $cuerpo['items'] ?? null;

        if (! is_array($items) || $items === []) {
            return Candidatos::ninguno();
        }

        $candidatos = [];

        foreach ($items as $item) {
            $candidato = is_array($item) ? $this->candidato($item) : null;

            if ($candidato !== null) {
                $candidatos[] = $candidato;
            }
        }

        return $candidatos === [] ? Candidatos::ninguno() : Candidatos::con($candidatos);
    }

    /**
     * Cada volumen de Google es una edición, con su editorial, su año y sus
     * identificadores propios: es justo lo que hace falta para distinguir dos
     * Candidatos del mismo título.
     *
     * @param  array<string, mixed>  $volumen
     */
    private function candidato(array $volumen): ?Candidato
    {
        $informacion = $this->informacion($volumen);
        $titulo = $informacion === null ? null : $this->titulo($informacion);

        if ($informacion === null || $titulo === null) {
            return null;
        }

        return new Candidato(
            isbn: $this->isbn($informacion),
            titulo: $titulo,
            autores: $this->autores($informacion),
            editorial: $this->cadena($informacion['publisher'] ?? null),
            anio: $this->anio($informacion['publishedDate'] ?? null),
            edicion: null,
            portadaUrl: $this->portada($informacion),
            descripcion: $this->descripcion($informacion),
            fuente: $this->identificador(),
            referencia: $this->cadena($volumen['id'] ?? null),
        );
    }

    /**
     * El ISBN de un volumen, o null cuando no publica ninguno o el que publica
     * no cuadra. Google mete de todo en `industryIdentifiers` —ISSN, claves
     * internas, ISBN con el dígito de control mal—, y un Candidato sin ISBN es
     * válido: mejor eso que guardar un número que no identifica nada.
     *
     * @param  array<string, mixed>  $informacion
     */
    private function isbn(array $informacion): ?Isbn
    {
        $identificadores = is_array($informacion['industryIdentifiers'] ?? null)
            ? $informacion['industryIdentifiers']
            : [];

        // El de trece primero: es el que se guarda normalizado.
        foreach (['ISBN_13', 'ISBN_10'] as $tipo) {
            foreach ($identificadores as $identificador) {
                if (! is_array($identificador) || ($identificador['type'] ?? null) !== $tipo) {
                    continue;
                }

                $valor = $this->cadena($identificador['identifier'] ?? null);

                if ($valor === null) {
                    continue;
                }

                try {
                    return Isbn::desde($valor);
                } catch (IsbnInvalido) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @return array<mixed>
     *
     * @throws FuenteNoDisponible
     */
    private function consultar(array $parametros): array
    {
        if ($this->tieneLlave()) {
            $parametros['key'] = $this->llave;
        }

        try {
            $respuesta = Http::acceptJson()
                ->timeout($this->timeout)
                ->get("{$this->url}/books/v1/volumes", $parametros);
        } catch (Throwable $falla) {
            throw new FuenteNoDisponible('Google Books no respondió: '.$falla->getMessage(), previous: $falla);
        }

        if ($respuesta->status() === 429) {
            throw new FuenteNoDisponible($this->tieneLlave()
                ? 'Google Books agotó la cuota de la llave configurada (HTTP 429).'
                : 'Google Books agotó la cuota compartida (HTTP 429); no hay llave configurada.');
        }

        if (! $respuesta->successful()) {
            throw new FuenteNoDisponible("Google Books respondió HTTP {$respuesta->status()}.");
        }

        $cuerpo = $respuesta->json();

        if (! is_array($cuerpo)) {
            throw new FuenteNoDisponible('Google Books respondió algo que no es JSON.');
        }

        return $cuerpo;
    }

    /**
     * @param  array<mixed>  $cuerpo
     * @return array<string, mixed>|null
     */
    private function primerVolumen(array $cuerpo): ?array
    {
        $items = $cuerpo['items'] ?? null;

        if (! is_array($items) || $items === []) {
            return null;
        }

        $primero = reset($items);

        return is_array($primero) ? $primero : null;
    }

    /**
     * @param  array<string, mixed>|null  $volumen
     * @return array<string, mixed>|null
     */
    private function informacion(?array $volumen): ?array
    {
        $informacion = $volumen === null ? null : ($volumen['volumeInfo'] ?? null);

        return is_array($informacion) ? $informacion : null;
    }

    /** @param  array<string, mixed>  $informacion */
    private function titulo(array $informacion): ?string
    {
        $titulo = $this->cadena($informacion['title'] ?? null);
        $subtitulo = $this->cadena($informacion['subtitle'] ?? null);

        if ($titulo === null) {
            return null;
        }

        return $subtitulo === null ? $titulo : "{$titulo}: {$subtitulo}";
    }

    /**
     * @param  array<string, mixed>  $informacion
     * @return list<string>
     */
    private function autores(array $informacion): array
    {
        $acreditados = is_array($informacion['authors'] ?? null) ? $informacion['authors'] : [];
        $autores = [];

        foreach ($acreditados as $acreditado) {
            $nombre = $this->cadena($acreditado);

            if ($nombre !== null) {
                $autores[] = $nombre;
            }
        }

        return $autores;
    }

    /**
     * Google entrega la descripción con etiquetas de HTML. La Ficha guarda
     * texto, así que se limpia aquí y no en cada pantalla que la muestre.
     *
     * @param  array<string, mixed>  $informacion
     */
    private function descripcion(array $informacion): ?string
    {
        $descripcion = $this->cadena($informacion['description'] ?? null);

        if ($descripcion === null) {
            return null;
        }

        $texto = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $descripcion));

        return $this->cadena(html_entity_decode($texto, ENT_QUOTES | ENT_HTML5));
    }

    /** @param  array<string, mixed>  $informacion */
    private function portada(array $informacion): ?string
    {
        $imagenes = is_array($informacion['imageLinks'] ?? null) ? $informacion['imageLinks'] : [];

        foreach (['extraLarge', 'large', 'medium', 'small', 'thumbnail', 'smallThumbnail'] as $tamano) {
            $url = $this->cadena($imagenes[$tamano] ?? null);

            if ($url !== null) {
                return str_replace('http://', 'https://', $url);
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

    /** Las comillas mantienen junta la frase; las que trae el texto sobran. */
    private function entrecomillado(string $termino): string
    {
        return '"'.str_replace('"', '', $termino).'"';
    }

    private function tieneLlave(): bool
    {
        return $this->llave !== null && trim($this->llave) !== '';
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
