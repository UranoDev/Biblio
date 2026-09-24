<?php

namespace App\Quotes;

use RuntimeException;

/**
 * The renderer has no quota left to draw with (ADR-009).
 *
 * It is not a transient failure, and treating it as one is the expensive
 * mistake: the credits of html2img reset by the month and do not accumulate, so
 * retrying is burning next month's quota the instant it renews. It stops the
 * queue instead.
 */
final class OutOfCredits extends RuntimeException
{
    public static function said(string $reason): self
    {
        return new self(trim($reason) === '' ? 'The renderer is out of credits.' : trim($reason));
    }
}
