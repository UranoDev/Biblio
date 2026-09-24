<?php

namespace App\Showcase;

use App\Models\Book;
use App\Models\Category;
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
     * The Books that are on the Shelf, as a visitor sees them; all of them, or
     * the ones of one Category.
     *
     * @return Collection<int, Book>
     */
    public static function onShelf(?Category $category = null): Collection
    {
        return self::inCategory(self::query()->owned(), $category)->get();
    }

    /**
     * The Books that are Wanted, all of them or the ones of one Category.
     *
     * @return Collection<int, Book>
     */
    public static function wanted(?Category $category = null): Collection
    {
        return self::inCategory(self::query()->wanted(), $category)->get();
    }

    /**
     * Every visible Book, on the Shelf or Wanted.
     *
     * The two sections of the front page are two questions —what I have, what I
     * am missing—, and this is the other one: what there is. The sitemap asks
     * it, because a crawler walks addresses and does not care which section a
     * Book is printed in.
     *
     * @return Collection<int, Book>
     */
    public static function books(): Collection
    {
        return self::query()->get();
    }

    /**
     * How many Quotes a visitor can read: the ones of the Books that are on
     * display, and not the ones of a Hidden Book (ADR-003).
     */
    public static function quoteCount(): int
    {
        return Quote::query()->whereHas('book', self::onlyVisible(...))->count();
    }

    /**
     * The Categories a visitor can navigate by: the ones with at least one
     * visible Book, in alphabetical order.
     *
     * A Category whose Books are all Hidden is not shown, and that is not a
     * matter of tidiness: its name would say out loud that something exists
     * that was taken out of the Showcase on purpose (ADR-003). Empty ones do
     * not show either — one left behind by a merge is bookkeeping, not a
     * subject.
     *
     * Each one carries how many visible Books it has, which is what the
     * navigation prints. The count of the Shelf is another number and lives on
     * the back office screen.
     *
     * @return Collection<int, Category>
     */
    public static function categories(): Collection
    {
        return Category::query()
            ->whereHas('books', self::onlyVisible(...))
            ->withCount(['books as books_count' => self::onlyVisible(...)])
            ->orderBy('name')
            ->get();
    }

    /**
     * The Category a public address names, whether it is the slug it holds now
     * or one it answered to before a rename or a merge (BIB-17).
     *
     * Reachable is the same question as shown in the navigation, and that is
     * the point of resolving it here: a Category with no visible Book is not
     * found, so it cannot be reached by typing the address either. Otherwise
     * the navigation would hide it and the URL would still confirm it exists.
     */
    public static function category(string $slug): ?Category
    {
        $category = Category::bySlug($slug);

        if (! $category instanceof Category) {
            return null;
        }

        return $category->books()->visible()->exists() ? $category : null;
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
     * Narrows a public query to one Category, or leaves it as it was.
     *
     * A Book with several Categories belongs to all of them, so this filters
     * and never joins: joining would print the Book once per Category it shares
     * with the page.
     *
     * @param  Builder<Book>  $books
     * @return Builder<Book>
     */
    private static function inCategory(Builder $books, ?Category $category): Builder
    {
        if (! $category instanceof Category) {
            return $books;
        }

        return $books->whereRelation('categories', 'categories.id', $category->getKey());
    }

    /**
     * What the Categories are asked about their Books, in both halves of the
     * same question: which Categories have one, and how many each one has.
     *
     * @param  Builder<Book>  $books
     */
    private static function onlyVisible(Builder $books): void
    {
        $books->visible();
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
