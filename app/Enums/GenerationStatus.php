<?php

namespace App\Enums;

/**
 * The situations a Book can be in regarding what the AI writes for it
 * (ADR-006).
 *
 * Four of them look identical from the outside —there is nothing to show— and
 * are not the same thing: one is waiting its turn in the queue, one gave up
 * after hours of trying, one was never asked, and one is not going to happen
 * until somebody chooses an AiModel. Showing any of them as "this Book has no
 * Categories" promises something nobody configured, and showing the one that
 * failed as if it were still in line hides an error that deserves to be seen
 * (BIB-33).
 *
 * `Pending` rests on the mark written when the request was queued and on
 * nothing else. A Book that has never been queued is `NotAsked`, however
 * complete the configuration is: guessing the queue from a chosen AiModel is
 * what left a Book announcing a wait that nobody had ever started (BIB-46).
 */
enum GenerationStatus: string
{
    case Generated = 'generated';
    case Pending = 'pending';
    case Failed = 'failed';
    case NotAsked = 'not_asked';
    case Off = 'off';

    public function label(): string
    {
        return match ($this) {
            self::Generated => 'Generado',
            self::Pending => 'En la cola',
            self::Failed => 'Falló',
            self::NotAsked => 'Nunca se le pidió',
            self::Off => 'Falta elegir el modelo',
        };
    }
}
