<?php

namespace App\Domain;

use InvalidArgumentException;

/**
 * The text received is not an ISBN. Thrown before going out to the network:
 * a mistyped number is never asked to any Source.
 */
final class InvalidIsbn extends InvalidArgumentException
{
    public static function forFormat(string $value): self
    {
        return new self("«{$value}» is shaped like neither a 10 nor a 13 digit ISBN.");
    }

    public static function forCheckDigit(string $value): self
    {
        return new self("«{$value}» has the wrong check digit.");
    }
}
