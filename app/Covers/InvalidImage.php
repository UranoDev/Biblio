<?php

namespace App\Covers;

use RuntimeException;

/**
 * What arrived is not an image that can be stored as a Cover.
 *
 * It happens on both paths: a Source returning an error page with status 200,
 * and a file uploaded by hand.
 */
final class InvalidImage extends RuntimeException
{
    public static function notAnImage(): self
    {
        return new self('El archivo no es una imagen que se pueda leer.');
    }

    public static function unsupportedFormat(string $format): self
    {
        return new self("Covers are not stored in {$format} format.");
    }

    public static function tooHeavy(int $bytes, int $maximum): self
    {
        return new self("The image weighs {$bytes} bytes and the maximum is {$maximum}.");
    }
}
