<?php

namespace App\Showcase;

use App\Models\Book;
use App\Models\Category;
use DateTimeInterface;
use Illuminate\Support\Facades\Date;

/**
 * The list of the public addresses of the acervo, for a crawler to walk
 * (BIB-37).
 *
 * It is built out of `Showcase` and out of nothing else, which is the whole
 * point of it being here: a sitemap written by hand is a second list of what is
 * public, and the day one of the two is forgotten it is this one, announcing a
 * Book that was Hidden months ago (ADR-003). Asking the same class every public
 * page asks, a Book leaves the sitemap the moment it stops being visible, and
 * comes back the moment it is shown again.
 *
 * There is nothing here about how often a crawler should come back: `priority`
 * and `changefreq` are hints every search engine has said out loud it ignores.
 */
final class Sitemap
{
    /**
     * Every public address, with the date what it shows last changed.
     *
     * @return list<array{loc: string, lastmod: string}>
     */
    public static function entries(): array
    {
        $books = Showcase::books();

        $entries = $books
            ->map(fn (Book $book): array => self::entry(
                route('showcase.book', $book),
                $book->updated_at,
            ))
            ->all();

        // The page of a Category changes when its Books do, but the Category
        // row only changes when it is renamed or merged. Its own date is the
        // one that is true about the address itself, and a crawler that comes
        // back for the Books has each of them listed here already.
        $categories = Showcase::categories()
            ->map(fn (Category $category): array => self::entry(
                route('showcase.category', $category),
                $category->updated_at,
            ))
            ->all();

        // The front page is as old as the newest thing on it, and never older:
        // it lists every visible Book.
        $front = self::entry(
            route('showcase'),
            $books->sortByDesc('updated_at')->first()?->updated_at,
        );

        // The page of a Quote is not here yet; it is born with BIB-31, and it
        // is the one that brings visits.

        return [$front, ...$entries, ...$categories];
    }

    /**
     * @return array{loc: string, lastmod: string}
     */
    private static function entry(string $loc, ?DateTimeInterface $lastmod): array
    {
        return [
            'loc' => $loc,
            // A date and not an instant: the hour a row was touched says
            // nothing to a crawler, and it would move the whole sitemap on
            // every save.
            'lastmod' => ($lastmod ?? Date::now())->format('Y-m-d'),
        ];
    }
}
