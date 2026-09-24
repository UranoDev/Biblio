<?php

namespace App\Books;

use App\Models\Book;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Books that nothing ever asked a card for (BIB-50).
 *
 * The render is dispatched from the `updated` hook of the Book, so every Book
 * whose Cover arrived before that hook existed is owed a card with nothing
 * behind it: no job in the queue, no row in `failed_jobs`, and a link that goes
 * out with the small card. Saving each one by hand is what this replaces.
 *
 * Running it twice does not queue anything twice: what it queues stops matching
 * on the way out, because the mark of the queue is exactly what it looks for
 * the absence of.
 */
final class MissingCards
{
    /**
     * The ones that are owed a first render, in the order they were registered.
     *
     * Only the ones with a stored Cover: without one there is no card to draw
     * at all. And the Hidden are left out, because spending a render on a Book
     * that shows nowhere is spending it on nothing (ADR-003).
     *
     * @return Collection<int, Book>
     */
    public static function books(): Collection
    {
        return Book::query()
            ->visible()
            ->whereNotNull('cover_path')
            ->whereNull('share_image_path')
            ->whereNull('share_image_queued_at')
            ->whereNull('share_image_failed_at')
            ->orderBy('id')
            ->get();
    }

    /** How many are owed a first render. Each one is one drawing. */
    public static function count(): int
    {
        return self::books()->count();
    }

    /**
     * Puts them in the queue, and answers how many went in.
     *
     * The ones to queue can be handed in already read, so that a caller that
     * listed them first does not go looking for them a second time and queue a
     * different set than the one it printed.
     *
     * @param  Collection<int, Book>|null  $books
     * @return int<0, max>
     */
    public static function queue(?Collection $books = null): int
    {
        $books ??= self::books();

        $books->each(fn (Book $book) => $book->queueShareImage());

        return $books->count();
    }
}
