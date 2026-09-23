<?php

namespace App\Portadas;

use RuntimeException;

/**
 * Lo que llegó no es una imagen que se pueda guardar como Portada.
 *
 * Pasa con las dos vías: una Fuente que devuelve una página de error con
 * estado 200, y un archivo que se sube a mano.
 */
final class ImagenInvalida extends RuntimeException
{
    public static function noEsUnaImagen(): self
    {
        return new self('El archivo no es una imagen que se pueda leer.');
    }

    public static function formatoNoSoportado(string $formato): self
    {
        return new self("Las Portadas no se guardan en formato {$formato}.");
    }

    public static function demasiadoPesada(int $bytes, int $maximo): self
    {
        return new self("La imagen pesa {$bytes} bytes y el máximo es {$maximo}.");
    }
}
