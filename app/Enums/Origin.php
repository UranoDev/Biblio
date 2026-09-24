<?php

namespace App\Enums;

/**
 * Where the Description of a Book came from (ADR-007, BIB-45).
 *
 * The one the AI wrote is the only one a regeneration rewrites: neither the one
 * from the Source nor mine is ever overwritten. `Translated` is a fourth thing
 * and not a shade of the other three — what it says is the content came from
 * the catalogue and the Spanish came from a machine —, and it is rewritten too,
 * because a translation can always be made again from the original that is kept
 * beside it.
 */
enum Origin: string
{
    case Source = 'source';
    case Ai = 'ai';
    case Owner = 'owner';
    case Translated = 'translated';

    public function label(): string
    {
        return match ($this) {
            self::Source => 'De la Fuente',
            self::Ai => 'Escrita por IA',
            self::Owner => 'Mía',
            self::Translated => 'Traducida por IA',
        };
    }

    /**
     * Whether a regeneration may write this Description from scratch.
     *
     * A translated one is not here: what a regeneration does with it is
     * translate its original again, which is another thing and never invents
     * the content.
     */
    public function isRegenerated(): bool
    {
        return $this === self::Ai;
    }

    /**
     * Whether the words that are published were put together by a machine,
     * whether it wrote them or translated them. It is what the Showcase marks.
     */
    public function isMachineWritten(): bool
    {
        return $this === self::Ai || $this === self::Translated;
    }
}
