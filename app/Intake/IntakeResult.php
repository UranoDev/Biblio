<?php

namespace App\Intake;

use App\Models\Book;

/**
 * How picking a Candidate in the TitleLookup ended up.
 *
 * There are two outcomes and in both there is a Book: the one just born, or the
 * one that already had that ISBN. That is why the Book is not optional — the
 * screen always has one to point at.
 */
final readonly class IntakeResult
{
    private function __construct(
        public Book $book,
        public bool $alreadyRegistered,
    ) {}

    public static function registered(Book $book): self
    {
        return new self($book, false);
    }

    /** That ISBN was already on the Shelf: which Book it is gets reported and nothing is duplicated. */
    public static function wasAlreadyThere(Book $book): self
    {
        return new self($book, true);
    }
}
