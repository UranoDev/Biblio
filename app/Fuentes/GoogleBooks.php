<?php

namespace App\Fuentes;

use App\Dominio\Isbn;
use App\Enums;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * El respaldo de la cascada (ADR-002), y la única de las dos que trae la
 * Descripción en la misma petición.
 *
 * La llave se lee del entorno. Sin llave el adaptador funciona igual, pero
 * queda expuesto a la cuota compartida; cuando ésa se agota, Google contesta
 * HTTP 429, y eso se traduce a «la Fuente no pudo contestar», nunca a «no lo
 * conoce».
 */
final class GoogleBooks implements Fuente
{
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
        $parametros = ['q' => "isbn:{$isbn->valor()}"];

        if ($this->tieneLlave()) {
            $parametros['key'] = $this->llave;
        }

        try {
            $respuesta = Http::acceptJson()
                ->timeout($this->timeout)
                ->get("{$this->url}/books/v1/volumes", $parametros);
        } catch (Throwable $falla) {
            return Respuesta::noDisponible('Google Books no respondió: '.$falla->getMessage());
        }

        if ($respuesta->status() === 429) {
            return Respuesta::noDisponible($this->tieneLlave()
                ? 'Google Books agotó la cuota de la llave configurada (HTTP 429).'
                : 'Google Books agotó la cuota compartida (HTTP 429); no hay llave configurada.');
        }

        if (! $respuesta->successful()) {
            return Respuesta::noDisponible("Google Books respondió HTTP {$respuesta->status()}.");
        }

        $cuerpo = $respuesta->json();

        if (! is_array($cuerpo)) {
            return Respuesta::noDisponible('Google Books respondió algo que no es JSON.');
        }

        $volumen = $this->volumen($cuerpo);
        $titulo = $volumen === null ? null : $this->titulo($volumen);

        if ($volumen === null || $titulo === null) {
            return Respuesta::sinFicha();
        }

        return Respuesta::conFicha(new Ficha(
            isbn: $isbn,
            titulo: $titulo,
            autores: $this->autores($volumen),
            editorial: $this->cadena($volumen['publisher'] ?? null),
            anio: $this->anio($volumen['publishedDate'] ?? null),
            // Google Books no publica el nombre de la edición.
            edicion: null,
            portadaUrl: $this->portada($volumen),
            descripcion: $this->descripcion($volumen),
            fuente: $this->identificador(),
        ));
    }

    /**
     * @param  array<mixed>  $cuerpo
     * @return array<string, mixed>|null
     */
    private function volumen(array $cuerpo): ?array
    {
        $items = $cuerpo['items'] ?? null;

        if (! is_array($items) || $items === []) {
            return null;
        }

        $primero = reset($items);
        $informacion = is_array($primero) ? ($primero['volumeInfo'] ?? null) : null;

        return is_array($informacion) ? $informacion : null;
    }

    /** @param  array<string, mixed>  $volumen */
    private function titulo(array $volumen): ?string
    {
        $titulo = $this->cadena($volumen['title'] ?? null);
        $subtitulo = $this->cadena($volumen['subtitle'] ?? null);

        if ($titulo === null) {
            return null;
        }

        return $subtitulo === null ? $titulo : "{$titulo}: {$subtitulo}";
    }

    /**
     * @param  array<string, mixed>  $volumen
     * @return list<string>
     */
    private function autores(array $volumen): array
    {
        $acreditados = is_array($volumen['authors'] ?? null) ? $volumen['authors'] : [];
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
     * @param  array<string, mixed>  $volumen
     */
    private function descripcion(array $volumen): ?string
    {
        $descripcion = $this->cadena($volumen['description'] ?? null);

        if ($descripcion === null) {
            return null;
        }

        $texto = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $descripcion));

        return $this->cadena(html_entity_decode($texto, ENT_QUOTES | ENT_HTML5));
    }

    /** @param  array<string, mixed>  $volumen */
    private function portada(array $volumen): ?string
    {
        $imagenes = is_array($volumen['imageLinks'] ?? null) ? $volumen['imageLinks'] : [];

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
