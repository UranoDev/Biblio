<?php

namespace App\Showcase;

use App\Models\Book;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Everything the Showcase asks the database, in one place.
 *
 * Hidden is the one thing this face cannot get wrong: a forgotten filter breaks
 * nothing, warns nobody, and errs outwards (ADR-003). So no public screen
 * writes a query of its own — they all come through here, and here every one of
 * them starts at `visible`. Adding a surface is adding a method to this class,
 * and there is no way to add one that forgets.
 */
final class Showcase
{
    /**
     * The Books that are on the Shelf, as a visitor sees them.
     *
     * @return Collection<int, Book>
     */
    public static function onShelf(): Collection
    {
        return self::query()->owned()->get();
    }

    /**
     * The Books that are Wanted.
     *
     * @return Collection<int, Book>
     */
    public static function wanted(): Collection
    {
        return self::query()->wanted()->get();
    }

    /**
     * One Book with everything its page shows.
     *
     * A Hidden one is not found: from outside there is no such Book, which is
     * the same answer an address that was never a Book gets.
     */
    public static function book(int $id): Book
    {
        $book = self::query()
            ->with(['categories', 'quotes'])
            ->findOrFail($id);

        // Each card of a Quote draws the Book beside the words, and asking the
        // Quote for it would be one query per Quote on a Book that may have
        // forty of them. The Book is right here, so it gets handed over.
        $book->quotes->each(fn (Quote $quote) => $quote->setRelation('book', $book));

        return $book;
    }

    /**
     * The starting point of every public query: what is visible, by title.
     *
     * @return Builder<Book>
     */
    private static function query(): Builder
    {
        return Book::query()->visible()->orderBy('title');
    }
}
