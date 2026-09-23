<?php

namespace App\Covers;

use RuntimeException;

/**
 * The image was fine and the disk did not take it. It is an infrastructure
 * failure, not a failure of the file, so it is reported apart from InvalidImage.
 */
final class CoverNotStored extends RuntimeException
{
    public static function atPath(string $path): self
    {
        return new self("The disk did not accept the Cover at «{$path}».");
    }
}
