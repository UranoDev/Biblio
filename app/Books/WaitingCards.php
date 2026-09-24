<?php

namespace App\Books;

use App\Models\Book;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Books whose card was asked for and never came back (BIB-54).
 *
 * `markShareImageOwed()` writes the mark **before** anything is drawn (BIB-50),
 * and `RenderBookImage` leaves without drawing and without complaining when the
 * credits ran out (ADR-009). What is left is a Book carrying the mark of the
 * queue with no card, no failure and no job: it is waiting for a month that
 * already renewed.
 *
 * This is the other half of {@see MissingCards}, and the two do not overlap on
 * purpose. That one is for the Books nothing ever asked a card for — it looks
 * for the *absence* of the mark. This one is for the ones that were asked and
 * stopped halfway, which is exactly the mark being *there*.
 *
 * Running it twice does not queue anything twice in the sense that matters:
 * what goes in is what was already owed, and each Book goes in once.
 */
final class WaitingCards
{
    /**
     * The ones still owed the card they were queued for, oldest first.
     *
     * The ones the renderer refused are left out: `share_image_failed_at` says
     * waiting is not what they are missing, and pushing them back into the
     * queue would spend a credit on the same no. The Hidden are left out too,
     * and so are the ones with no stored Cover — there is no card to draw for
     * either, so a render on them is a render on nothing (ADR-003, ADR-008).
     *
     * @return Collection<int, Book>
     */
    public static function books(): Collection
    {
        return Book::query()
            ->visible()
            ->whereNotNull('cover_path')
            ->whereNotNull('share_image_queued_at')
            ->whereNull('share_image_path')
            ->whereNull('share_image_failed_at')
            ->orderBy('id')
            ->get();
    }

    /** How many are still waiting. Each one is one drawing. */
    public static function count(): int
    {
        return self::books()->count();
    }

    /**
     * Puts them back in the queue, and answers how many went in.
     *
     * The ones to queue can be handed in already read, so that a caller that
     * counted them first does not go looking for them a second time and queue a
     * different set than the one it reported.
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
