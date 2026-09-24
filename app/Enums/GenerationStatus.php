<?php

namespace App\Enums;

/**
 * The situations a Book can be in regarding what the AI writes for it
 * (ADR-006).
 *
 * Three of them look identical from the outside —there is nothing to show— and
 * are not the same thing: one is waiting its turn in the queue, one gave up
 * after hours of trying, and one is not going to happen until somebody chooses
 * an AiModel. Showing any of them as "this Book has no Categories" promises
 * something nobody configured, and showing the one that failed as if it were
 * still in line hides an error that deserves to be seen (BIB-33).
 */
enum GenerationStatus: string
{
    case Generated = 'generated';
    case Pending = 'pending';
    case Failed = 'failed';
    case Off = 'off';

    public function label(): string
    {
        return match ($this) {
            self::Generated => 'Generado',
            self::Pending => 'En la cola',
            self::Failed => 'Falló',
            self::Off => 'Falta elegir el modelo',
        };
    }
}
