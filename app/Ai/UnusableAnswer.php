<?php

namespace App\Ai;

use App\Models\Book;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The AI answered and what came back cannot be stored: no Category in it, or no
 * record of which AiModel wrote it.
 *
 * It is a failure and not a wait. Sending the same prompt again in ten minutes
 * produces the same thing, and leaving it in the queue would keep the real
 * problem —a model that does not honour the schema— invisible for hours.
 */
final class UnusableAnswer extends RuntimeException
{
    public static function for(Book $book, string $answer): self
    {
        return new self(sprintf(
            'The AI answered nothing usable for Book %d: %s',
            $book->getKey(),
            Str::limit(Str::squish($answer), 300),
        ));
    }
}
