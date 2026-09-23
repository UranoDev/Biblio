<?php

namespace App\Jobs;

use App\Models\Libro;
use App\Portadas\AlmacenDePortadas;
use App\Portadas\ImagenInvalida;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Baja la Portada que indicó la Fuente y la guarda (ADR-004).
 *
 * Va en cola porque se captura en ráfagas: la imagen tarda más que la Ficha y
 * el Libro tiene que quedar registrado sin esperarla. Si la descarga falla, el
 * Libro se queda sin Portada y con su `portada_origen_url` intacta, que es lo
 * que hace posible reintentar después o subir una a mano.
 */
class DescargarPortada implements ShouldQueue
{
    use Queueable;

    /** Los cortes de red, los 5xx y las fallas del disco se reintentan solos. */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Libro $libro) {}

    /**
     * Deja anotada la URL de la Portada y pone la descarga en cola. Sin URL no
     * hay nada que bajar y el Libro queda sin Portada, que es un estado válido.
     */
    public static function paraElLibro(Libro $libro, ?string $url): void
    {
        if ($url === null || trim($url) === '') {
            return;
        }

        $libro->forceFill(['portada_origen_url' => trim($url)])->save();

        self::dispatch($libro);
    }

    /**
     * Vuelve a poner la descarga en cola. Devuelve false cuando no hay de
     * dónde bajarla —Captura manual, o una Fuente que no trajo imagen—: ahí la
     * única vía es subirla a mano.
     */
    public static function reintentar(Libro $libro): bool
    {
        if ($libro->portada_origen_url === null || trim($libro->portada_origen_url) === '') {
            return false;
        }

        self::dispatch($libro);

        return true;
    }

    public function handle(AlmacenDePortadas $almacen): void
    {
        $url = $this->libro->portada_origen_url;

        if ($url === null || trim($url) === '') {
            return;
        }

        // Un corte de red o un timeout levantan ConnectionException y la cola
        // reintenta, que es lo que corresponde: la imagen sigue estando ahí.
        $respuesta = Http::timeout((int) config('portadas.timeout', 10))->get($url);

        if ($respuesta->serverError()) {
            throw new RuntimeException(
                "La Portada del Libro {$this->libro->getKey()} respondió {$respuesta->status()}."
            );
        }

        if (! $respuesta->successful()) {
            $this->rendirse("respondió {$respuesta->status()}");

            return;
        }

        try {
            // Si el disco falla, NoSePudoGuardarLaPortada sale de aquí sin
            // atajarse: eso sí mejora reintentándolo.
            $almacen->guardar($this->libro, $respuesta->body());
        } catch (ImagenInvalida $falla) {
            $this->rendirse($falla->getMessage());
        }
    }

    /**
     * La descarga no va a salir mejor reintentándola sola. El Libro se queda
     * sin Portada y la URL sigue guardada, así que el reintento a mano y la
     * subida siguen disponibles.
     */
    private function rendirse(string $motivo): void
    {
        Log::warning('No se pudo descargar la Portada.', [
            'libro' => $this->libro->getKey(),
            'url' => $this->libro->portada_origen_url,
            'motivo' => $motivo,
        ]);
    }
}
