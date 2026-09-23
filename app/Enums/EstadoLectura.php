<?php

namespace App\Enums;

/**
 * Los tres estados de Lectura de un Libro.
 *
 * No se guarda: se deriva de la marca de Lectura y de la Fecha de lectura.
 * Leído sin fecha es un estado propio — un Libro leído hace años, sin saber
 * cuándo, sigue estando leído.
 */
enum EstadoLectura: string
{
    case NoLeido = 'no_leido';
    case LeidoSinFecha = 'leido_sin_fecha';
    case LeidoConFecha = 'leido_con_fecha';

    public function etiqueta(): string
    {
        return match ($this) {
            self::NoLeido => 'No leído',
            self::LeidoSinFecha => 'Leído, sin fecha',
            self::LeidoConFecha => 'Leído',
        };
    }

    public function estaLeido(): bool
    {
        return $this !== self::NoLeido;
    }
}
