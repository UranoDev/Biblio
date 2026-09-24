<?php

namespace App\Quotes;

use InvalidArgumentException;

/**
 * The text received is not a page of a Book. Thrown when a Quote is being
 * stored, because the alternative is keeping the words and losing the page
 * without saying so.
 *
 * The screen that captures a Quote rejects it before this, with a message
 * saying what is expected.
 */
final class InvalidPageLabel extends InvalidArgumentException
{
    public static function for(string $value): self
    {
        return new self("«{$value}» is neither an arabic nor a roman page number.");
    }
}
