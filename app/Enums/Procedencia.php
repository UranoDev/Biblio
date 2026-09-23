<?php

namespace App\Enums;

/**
 * De dónde salió la Descripción de un Libro (ADR-007).
 *
 * Regenerar sólo alcanza a las de procedencia `Ia`: ni la de la Fuente ni la
 * mía se pisan nunca.
 */
enum Procedencia: string
{
    case Fuente = 'fuente';
    case Ia = 'ia';
    case Mia = 'mia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Fuente => 'De la Fuente',
            self::Ia => 'Escrita por IA',
            self::Mia => 'Mía',
        };
    }

    /**
     * Si una regeneración puede reemplazar esta Descripción.
     */
    public function seRegenera(): bool
    {
        return $this === self::Ia;
    }
}
