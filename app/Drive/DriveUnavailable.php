<?php

namespace App\Drive;

use RuntimeException;
use Throwable;

/**
 * Drive did not do what it was asked: the key could not be read, the token was
 * refused, or a call answered with an error.
 */
final class DriveUnavailable extends RuntimeException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
