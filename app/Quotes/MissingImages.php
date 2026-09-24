<?php

namespace App\Quotes;

use App\Models\Book;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The Quotes that were stored before anything queued their images, and that
 * nothing in the application was ever going to come back for (BIB-48).
 *
 * The render is dispatched from the `saved` hook of the Quote (ADR-009), so
 * every Quote typed in before that hook existed is owed two images with no job
 * behind them: `quotes` has no row for them, `failed_jobs` has none either, and
 * the only trace is a page saying the image is on its way. This is what finds
 * them and what puts them back in the queue.
 *
 * Running it twice does not queue anything twice: what it queues stops matching
 * on the way out, because the mark of the queue is exactly what it looks for
 * the absence of.
 */
final class MissingImages
{
    /**
     * The ones that are owed a first render, in the order they were written.
     *
     * The Hidden are left out: spending two renders on a Book that shows
     * nowhere is spending them on nothing (ADR-003). The ones too long for the
     * canvas are left out too — there is no image to draw for them, so counting
     * them here would ask for a run that cannot change their page (ADR-009).
     *
     * @return Collection<int, Quote>
     */
    public static function quotes(): Collection
    {
        return Quote::query()
            ->imagesNeverAsked()
            ->whereHas('book', function (Builder $book): void {
                /** @var Builder<Book> $book */
                $book->visible();
            })
            ->with('book')
            ->orderBy('id')
            ->get()
            ->filter(fn (Quote $quote) => $quote->imagesFit())
            ->values();
    }

    /** How many are owed a first render. Each one is two drawings. */
    public static function count(): int
    {
        return self::quotes()->count();
    }

    /**
     * Puts them in the queue, and answers how many went in.
     *
     * The ones to queue can be handed in already read, so that a caller that
     * listed them first does not go looking for them a second time and queue a
     * different set than the one it printed.
     *
     * @param  Collection<int, Quote>|null  $quotes
     * @return int<0, max>
     */
    public static function queue(?Collection $quotes = null): int
    {
        $quotes ??= self::quotes();

        $quotes->each(fn (Quote $quote) => $quote->queueImages());

        return $quotes->count();
    }
}
