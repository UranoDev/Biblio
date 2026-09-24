<?php

namespace App\Covers;

use RuntimeException;

/**
 * What arrived is not an image that can be stored as a Cover.
 *
 * It happens on both paths: a Source returning an error page with status 200,
 * and a file uploaded by hand. The message is the one the upload screen shows,
 * so it says which of the reasons it was.
 */
final class InvalidImage extends RuntimeException
{
    public static function notAnImage(): self
    {
        return new self('El archivo no es una imagen que se pueda leer.');
    }

    public static function unsupportedFormat(string $format): self
    {
        return new self("Las Portadas no se guardan en formato {$format}.");
    }

    public static function tooHeavy(int $bytes, int $maximum): self
    {
        return new self("La imagen pesa {$bytes} bytes y el máximo es {$maximum}.");
    }

    /**
     * Too small to be a Cover: on the shelf it ends up stretched and
     * pixelated.
     */
    public static function tooSmall(int $side, int $minimum): self
    {
        return new self("La imagen mide {$side} px de lado corto y el mínimo es {$minimum}.");
    }

    /**
     * One of the images a catalog hands back in place of a Cover it does not
     * have (see Marker).
     */
    public static function isAMarker(string $marker): self
    {
        return new self("La imagen es el marcador de «sin portada» de la Fuente: {$marker}.");
    }

    /** A single flat color, which is what those markers look like. */
    public static function isFlat(): self
    {
        return new self('La imagen es de un solo color plano, así que no es una portada.');
    }
}
