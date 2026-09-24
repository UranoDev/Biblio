<?php

namespace App\Quotes;

use Illuminate\Support\Facades\Cache;

/**
 * Whether there is quota left to draw images with (ADR-009).
 *
 * html2img charges by the month: the credits reset, they do not accumulate, and
 * there is no automatic top-up. With two renders per Quote, running out is not
 * a hypothetical — it is going to happen, and in the middle of a mass
 * regeneration.
 *
 * So the answer is written down and every render asks it first. Without it, the
 * queue would throw the two hundred Books behind this one at a service that
 * says no two hundred times, and each attempt of the month after renewal would
 * be spent on the same mistake.
 *
 * It is one switch for the whole installation and not a mark per Quote: what
 * ran out is the month, not this Quote.
 */
final class Credits
{
    private const KEY = 'quote-images:out-of-credits';

    /** The renderer said there is nothing left to draw with. The queue stops. */
    public static function ranOut(string $reason): void
    {
        Cache::forever(self::KEY, trim($reason) === '' ? 'Se acabaron los créditos.' : trim($reason));
    }

    public static function areOut(): bool
    {
        return self::reason() !== null;
    }

    /** What the renderer said, for the screen that shows the queue is stopped. */
    public static function reason(): ?string
    {
        $said = Cache::get(self::KEY);

        return is_string($said) ? $said : null;
    }

    /**
     * There is something to pay with again: the month renewed, or the plan
     * changed. It is a person who says so, from the screen — nothing here can
     * find it out on its own without spending a credit to ask.
     */
    public static function restored(): void
    {
        Cache::forget(self::KEY);
    }
}
