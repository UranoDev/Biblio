<?php

namespace App\Quotes;

use RuntimeException;

/**
 * The renderer did not give back an image, and it was not for want of credits.
 *
 * It carries whether waiting is worth it, because the two answers lead to
 * opposite places: a network drop or a 503 goes back to the queue, and an
 * invalid key or an HTML it refused fails now — retrying those hides the real
 * error for hours and pays for every attempt.
 */
final class RenderFailed extends RuntimeException
{
    private function __construct(string $message, public readonly bool $transient)
    {
        parent::__construct($message);
    }

    /** The renderer is there and is not answering right now. */
    public static function forNow(string $reason): self
    {
        return new self($reason, transient: true);
    }

    /** Waiting is not going to fix this one. */
    public static function forGood(string $reason): self
    {
        return new self($reason, transient: false);
    }

    /** Nothing is asked of the renderer without a key (ADR-006 says where it lives). */
    public static function withoutAKey(): self
    {
        return self::forGood('The renderer has no API key; HTML2IMG_API_KEY is not set.');
    }
}
