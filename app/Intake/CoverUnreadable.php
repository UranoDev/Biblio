<?php

namespace App\Intake;

use RuntimeException;
use Throwable;

/**
 * The photograph of the Cover gave nothing for the form: the AI could not be
 * asked, or it read neither a title nor an ISBN. The form stays as it was, to
 * be typed by hand.
 */
final class CoverUnreadable extends RuntimeException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
