<?php

namespace App\Intake;

use App\Domain\Isbn;
use App\Enums\ScanOutcome;
use App\Models\Book;

/**
 * How a Scan ended up, with what the screen needs to report it without looking
 * anything up again.
 *
 * It keeps both the normalized ISBN and what came in typed: when no Source knows
 * the ISBN, ManualEntry starts out with that number already filled in, and when
 * what was typed is not an ISBN, the notice has to be able to say what came in.
 */
final readonly class ScanResult
{
    private function __construct(
        public ScanOutcome $outcome,
        public string $typed,
        public ?Isbn $isbn = null,
        public ?Book $book = null,
        public ?string $reason = null,
    ) {}

    public static function registered(Book $book, Isbn $isbn, string $typed): self
    {
        return new self(ScanOutcome::Registered, $typed, $isbn, $book);
    }

    public static function alreadyOnShelf(Book $book, Isbn $isbn, string $typed): self
    {
        return new self(ScanOutcome::AlreadyOnShelf, $typed, $isbn, $book);
    }

    public static function movedToShelf(Book $book, Isbn $isbn, string $typed): self
    {
        return new self(ScanOutcome::MovedToShelf, $typed, $isbn, $book);
    }

    public static function unknownIsbn(Isbn $isbn, string $typed): self
    {
        return new self(ScanOutcome::UnknownIsbn, $typed, $isbn);
    }

    public static function sourceUnavailable(Isbn $isbn, string $typed, ?string $reason): self
    {
        return new self(ScanOutcome::SourceUnavailable, $typed, $isbn, null, $reason);
    }

    public static function invalidIsbn(string $typed): self
    {
        return new self(ScanOutcome::InvalidIsbn, $typed);
    }

    /** Moving from Wanted to the Shelf is the only step that gets undone. */
    public function canBeUndone(): bool
    {
        return $this->outcome === ScanOutcome::MovedToShelf;
    }

    /**
     * No Source knows it, or what was read is not an ISBN: the remaining path
     * is to type it out by hand.
     */
    public function offersManualEntry(): bool
    {
        return in_array($this->outcome, [ScanOutcome::UnknownIsbn, ScanOutcome::InvalidIsbn], true);
    }
}
