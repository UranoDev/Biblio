<?php

namespace App\Dominio;

use InvalidArgumentException;

/**
 * El texto que se recibió no es un ISBN. Se lanza antes de salir a la red:
 * un número mal tecleado no se le pregunta a ninguna Fuente.
 */
final class IsbnInvalido extends InvalidArgumentException
{
    public static function porFormato(string $valor): self
    {
        return new self("«{$valor}» no tiene la forma de un ISBN de 10 ni de 13 dígitos.");
    }

    public static function porDigitoDeControl(string $valor): self
    {
        return new self("«{$valor}» tiene el dígito de control equivocado.");
    }
}
