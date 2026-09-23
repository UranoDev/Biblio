<?php

namespace App\Enums;

/**
 * Whether a Book is on the Shelf or Wanted.
 *
 * They are the same entity with a different Status, not two lists: a Book that
 * gets bought is not registered again, it only changes Status.
 */
enum Status: string
{
    case Owned = 'owned';
    case Wanted = 'wanted';

    public function label(): string
    {
        return match ($this) {
            self::Owned => 'Acervo',
            self::Wanted => 'Por comprar',
        };
    }
}
