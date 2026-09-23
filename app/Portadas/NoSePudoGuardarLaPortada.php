<?php

namespace App\Portadas;

use RuntimeException;

/**
 * La imagen era buena y el disco no la aceptó. Es falla de infraestructura,
 * no del archivo, así que se reporta aparte de ImagenInvalida.
 */
final class NoSePudoGuardarLaPortada extends RuntimeException
{
    public static function enLaRuta(string $ruta): self
    {
        return new self("El disco no aceptó la Portada en «{$ruta}».");
    }
}
