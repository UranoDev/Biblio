<?php

namespace App\Portadas;

/**
 * La imagen de una Portada, ya validada, con lo poco que hace falta saber de
 * ella: si se puede leer, en qué formato viene y cómo sacarle una versión
 * reducida.
 *
 * Es el único lugar del proyecto que habla con GD.
 */
final readonly class Imagen
{
    /** Los formatos que se aceptan, con la extensión con la que se guardan. */
    private const FORMATOS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_AVIF => 'avif',
    ];

    private function __construct(
        public string $binario,
        private int $formato,
        public int $ancho,
        public int $alto,
    ) {}

    /**
     * @throws ImagenInvalida
     */
    public static function desde(string $binario): self
    {
        $medidas = @getimagesizefromstring($binario);

        if ($medidas === false) {
            throw ImagenInvalida::noEsUnaImagen();
        }

        [$ancho, $alto] = $medidas;
        $formato = $medidas[2];

        if (! array_key_exists($formato, self::FORMATOS)) {
            throw ImagenInvalida::formatoNoSoportado((string) image_type_to_mime_type($formato));
        }

        if ($ancho < 1 || $alto < 1) {
            throw ImagenInvalida::noEsUnaImagen();
        }

        return new self($binario, $formato, $ancho, $alto);
    }

    /** La extensión con la que se guarda la original, sin el punto. */
    public function extension(): string
    {
        return self::FORMATOS[$this->formato];
    }

    /**
     * La versión reducida para las retículas, en JPEG y con fondo blanco: una
     * miniatura transparente sobre un tema oscuro se ve como un recorte.
     *
     * Una imagen más angosta que el ancho pedido no se agranda; se reencoda
     * al mismo tamaño para que la miniatura siempre exista.
     *
     * @throws ImagenInvalida
     */
    public function reducida(int $anchoMaximo): string
    {
        $original = @imagecreatefromstring($this->binario);

        if ($original === false) {
            throw ImagenInvalida::noEsUnaImagen();
        }

        $ancho = max(1, min($anchoMaximo, $this->ancho));
        $alto = max(1, (int) round($this->alto * ($ancho / $this->ancho)));

        $lienzo = imagecreatetruecolor($ancho, $alto);

        if ($lienzo === false) {
            imagedestroy($original);

            throw ImagenInvalida::noEsUnaImagen();
        }

        $blanco = imagecolorallocate($lienzo, 255, 255, 255);

        if ($blanco !== false) {
            imagefilledrectangle($lienzo, 0, 0, $ancho, $alto, $blanco);
        }

        imagecopyresampled($lienzo, $original, 0, 0, 0, 0, $ancho, $alto, $this->ancho, $this->alto);

        ob_start();
        imagejpeg($lienzo, null, 82);
        $reducida = (string) ob_get_clean();

        imagedestroy($lienzo);
        imagedestroy($original);

        return $reducida;
    }
}
