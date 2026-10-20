<?php

namespace App\Sources;

use App\Domain\Isbn;

/**
 * A catalog that only knows how to answer one thing: the Cover of an ISBN.
 *
 * It is not a {@see Source}: it brings no Record —no title, no Authors— so it
 * never decides what gets registered. It is asked after the Sources, when none
 * of them had an image of that edition, and only for the address of one.
 */
interface CoverSource
{
    /** The address of an image of that edition, or null when it has none. */
    public function coverUrl(Isbn $isbn): ?string;
}
