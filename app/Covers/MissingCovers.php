<?php

namespace App\Covers;

use App\Jobs\FindCover;
use App\Models\Book;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Books showing the generated piece with no image on its way (BIB-57).
 *
 * Open Library wins the cascade often and answers the ISBN with no Cover of
 * that edition, so the Book was registered with `cover_source_url` empty and
 * nothing was ever downloaded for it. BIB-55 fixed that forward, at the moment
 * of the intake; this is the way back, for the Books that were registered
 * before it and are sitting like that in the Shelf.
 *
 * Two groups end up here, and they are the two the catalogs can still answer:
 * the ones nothing was ever asked about —no URL on record— and the ones whose
 * download gave up and left the mark of BIB-52. The ones with a URL and no
 * verdict yet are left out: a download is already on its way for them, and
 * asking the Sources again would spend a request on a question that is being
 * answered.
 *
 * Running it twice does not ask twice for the ones that got somewhere: a URL
 * that came back gets written down and clears the mark it had, so the Book
 * stops matching either group. The ones no catalog has an image of stay exactly
 * as they were and will be asked again — that is not a failure, it is that the
 * image does not exist anywhere to be found.
 */
final class MissingCovers
{
    /**
     * The ones worth asking the Sources about, in the order they were
     * registered.
     *
     * Without an ISBN there is nothing to ask —the ManualEntry of a book no
     * catalog knows— so those are left out. The Hidden are **not**: a Cover is
     * not a render paid by the month, it is one request to a public catalog,
     * and the Book shows its Cover in the Shelf whether or not it shows in the
     * Showcase (ADR-003 is about what the Showcase asks, and this is not it).
     *
     * A Book with no stored image is showing the generated piece (ADR-008), so
     * it takes one from a Source by definition; the photograph the owner took
     * is never here, because that one has its file.
     *
     * @return Collection<int, Book>
     */
    public static function books(): Collection
    {
        return Book::query()
            ->whereNull('cover_path')
            ->whereNotNull('isbn')
            ->where(function ($query): void {
                $query->whereNull('cover_source_url')
                    ->orWhereNotNull('cover_failed_at');
            })
            ->orderBy('id')
            ->get();
    }

    /** How many are owed the question. Each one is one or two requests. */
    public static function count(): int
    {
        return self::books()->count();
    }

    /**
     * Puts the question in the queue, and answers how many went in.
     *
     * It is queued and not asked here because asking is a request per Source
     * over the network: a screen that waited for a hundred of them in a row
     * would time out, and the Sources answering slowly is the normal case.
     *
     * The ones to ask can be handed in already read, so that a caller that
     * listed them first does not go looking for them a second time and queue a
     * different set than the one it printed.
     *
     * @param  Collection<int, Book>|null  $books
     * @return int<0, max>
     */
    public static function queue(?Collection $books = null): int
    {
        $books ??= self::books();

        $books->each(fn (Book $book) => FindCover::dispatch($book));

        return $books->count();
    }
}
