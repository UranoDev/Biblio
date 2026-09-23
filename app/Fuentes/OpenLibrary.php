<?php

namespace App\Fuentes;

use App\Dominio\Isbn;
use App\Enums;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * La primera Fuente de la cascada (ADR-002).
 *
 * Open Library guarda la descripción en la obra, no en la edición: el registro
 * de `/isbn/<isbn>.json` sólo apunta a ella con `works[0].key`, así que hace
 * falta una segunda petición. Eso no introduce el concepto de obra —seguimos
 * sin tenerlo—; es nada más dónde está guardado el dato.
 */
final class OpenLibrary implements Fuente
{
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
     * @return array<string, mixed>|null null cuando Open Library contesta que no conoce el recurso.
     *
     * @throws FuenteNoDisponible
     */
    private function json(string $ruta): ?array
    {
        try {
            $respuesta = Http::acceptJson()
                ->timeout($this->timeout)
                ->get($this->url.$ruta);
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
