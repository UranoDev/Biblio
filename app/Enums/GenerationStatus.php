<?php

namespace App\Enums;

/**
 * The three situations a Book can be in regarding what the AI writes for it
 * (ADR-006).
 *
 * Pending and Off look identical from the outside —there is nothing to show—
 * and are not the same thing: one is waiting in the queue and the other is not
 * going to happen until somebody chooses an AiModel. Showing either of them as
 * "this Book has no Categories" promises something nobody configured.
 */
enum GenerationStatus: string
{
    case Generated = 'generated';
    case Pending = 'pending';
    case Off = 'off';

    public function label(): string
    {
        return match ($this) {
            self::Generated => 'Generado',
            self::Pending => 'En la cola',
            self::Off => 'Falta elegir el modelo',
        };
    }
}
