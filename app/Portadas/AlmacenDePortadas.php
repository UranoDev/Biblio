<?php

namespace App\Portadas;

use App\Models\Libro;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda la Portada de un Libro y la reemplaza cuando llega otra (ADR-004).
 *
 * Escribe siempre en el disco por omisión, el que indique FILESYSTEM_DISK, y
 * nunca lo nombra: el día que se prenda S3 se instala el driver y se cambia el
 * `.env`. Ninguna ruta de archivo se arma fuera de aquí.
 */
final readonly class AlmacenDePortadas
{
    public function __construct(
        private string $directorio,
        private int $anchoDeLaMiniatura,
        private int $pesoMaximo,
    ) {}

    /**
     * Guarda la imagen como Portada del Libro: la original tal como llegó y
     * la versión reducida para las retículas. Si el Libro ya tenía Portada,
     * la anterior se borra recién cuando la nueva quedó escrita.
     *
     * @throws ImagenInvalida|NoSePudoGuardarLaPortada
     */
    public function guardar(Libro $libro, string $binario): void
    {
        $peso = strlen($binario);

        if ($peso > $this->pesoMaximo) {
            throw ImagenInvalida::demasiadoPesada($peso, $this->pesoMaximo);
        }

        $imagen = Imagen::desde($binario);

        $anteriores = array_filter([$libro->portada_ruta, $libro->portada_miniatura_ruta]);

        $nombre = Str::lower((string) Str::ulid());
        $ruta = $this->ruta($libro, "{$nombre}.{$imagen->extension()}");
        $miniatura = $this->ruta($libro, "{$nombre}-miniatura.jpg");

        $this->escribir($ruta, $imagen->binario);
        $this->escribir($miniatura, $imagen->reducida($this->anchoDeLaMiniatura));

        $libro->forceFill([
            'portada_ruta' => $ruta,
            'portada_miniatura_ruta' => $miniatura,
        ])->save();

        $this->borrarArchivos($anteriores);
    }

    /**
     * La Portada que se subió a mano. Reemplaza la que hubiera: la Captura
     * manual no trae ninguna y algunas Fuentes devuelven miniaturas
     * inservibles.
     *
     * @throws ImagenInvalida|NoSePudoGuardarLaPortada
     */
    public function guardarSubida(Libro $libro, UploadedFile $archivo): void
    {
        $binario = @file_get_contents($archivo->getRealPath());

        if ($binario === false) {
            throw ImagenInvalida::noEsUnaImagen();
        }

        $this->guardar($libro, $binario);
    }

    /** Deja al Libro sin Portada y borra sus archivos. */
    public function borrar(Libro $libro): void
    {
        $archivos = array_filter([$libro->portada_ruta, $libro->portada_miniatura_ruta]);

        $libro->forceFill([
            'portada_ruta' => null,
            'portada_miniatura_ruta' => null,
        ])->save();

        $this->borrarArchivos($archivos);
    }

    private function ruta(Libro $libro, string $archivo): string
    {
        return "{$this->directorio}/{$libro->getKey()}/{$archivo}";
    }

    /**
     * @throws NoSePudoGuardarLaPortada
     */
    private function escribir(string $ruta, string $contenido): void
    {
        if (Storage::disk()->put($ruta, $contenido) === false) {
            throw NoSePudoGuardarLaPortada::enLaRuta($ruta);
        }
    }

    /** @param  array<int, string>  $archivos */
    private function borrarArchivos(array $archivos): void
    {
        foreach ($archivos as $archivo) {
            Storage::disk()->delete($archivo);
        }
    }
}
