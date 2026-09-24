<?php

namespace App\Enums;

/**
 * Where the Cover of a Book came from (ADR-008).
 *
 * It is not the same idea as the Origin of the Description, even though both
 * answer «who wrote this»: a Description can be written by an AI and a Cover
 * cannot, and a Cover can be generated out of the Record and a Description
 * cannot. Two lists of values, two enums.
 *
 * `Generated` is not the absence of a Cover: every Book has one, and the ones
 * that carry this Origin are exactly the queue of what is left to photograph.
 */
enum CoverOrigin: string
{
    case Source = 'source';
    case Generated = 'generated';
    case Owner = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::Source => 'De la Fuente',
            self::Generated => 'Generada',
            self::Owner => 'Mía',
        };
    }

    /**
     * Whether a Cover arriving with this Origin may take the place of the one
     * the Book has now.
     *
     * A retry against the Sources replaces a generated one, which is what it is
     * for; it never replaces the photo I took myself. Mine replaces anything,
     * because uploading one is an explicit act. Same rule as the Descriptions
     * in ADR-007.
     */
    public function mayReplace(self $current): bool
    {
        return $this === self::Owner || $current !== self::Owner;
    }
}
