<?php

namespace App\Enums;

/**
 * Where the Description of a Book came from (ADR-007).
 *
 * Regenerating only reaches the ones with origin `Ai`: neither the one from the
 * Source nor mine is ever overwritten.
 */
enum Origin: string
{
    case Source = 'source';
    case Ai = 'ai';
    case Owner = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::Source => 'De la Fuente',
            self::Ai => 'Escrita por IA',
            self::Owner => 'Mía',
        };
    }

    /**
     * Whether a regeneration may replace this Description.
     */
    public function isRegenerated(): bool
    {
        return $this === self::Ai;
    }
}
