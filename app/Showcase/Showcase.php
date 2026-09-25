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
     * The Books on the Shelf as the front page draws them: the whole Shelf, in
     * a different order on every load.
     *
     * The front page opens on the first rows and keeps the rest behind «cargar
     * más» (BIB-60), so the order decides which books are seen without
     * scrolling. By title that would be the same handful for ever —whatever
     * starts with an A— and the library would look like its first shelf.
     * Shuffled, the three rows are a sample of the whole collection.
     *
     * The page of a Category keeps the order by title: there the whole list is
     * printed, and a list that is read from top to bottom is read in an order.
     *
     * @return Collection<int, Book>
     */
    public static function onShelfAtRandom(): Collection
    {
        return self::query()->owned()->reorder()->inRandomOrder()->get();
    }

    /**
     * The Books I am in the middle of: the ones on the Shelf marked as being
     * read right now (BIB-59).
     *
     * They are asked for on the Shelf, not across everything visible. A Book I
     * do not have yet cannot be one I am reading, and if a row ever says both
     * things at once it is printed under «los que me faltan» — the section that
     * matches its Status — instead of twice on the same page.
     *
     * They stay in the Shelf section as well: that section is the collection
     * and its count is how many Books there are, and a Book stops being one I
     * have for no reason while I am reading it.
     *
     * @return Collection<int, Book>
     */
    public static function beingRead(): Collection
    {
        return self::query()->owned()->beingRead()->get();
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
     * The sections of the front page are questions about a part —what I am
     * reading, what I am missing, what I have—, and this is the other one: what
     * there is. The sitemap asks it, because a crawler walks addresses and does
     * not care which section a Book is printed in.
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
            ->whereHas('books', self::onlyVisible(...), '>=', 5)
            ->withCount(['books as books_count' => self::onlyVisible(...)])
            ->orderByDesc('books_count')
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
     * Every Quote a visitor can open, each with the Book it belongs to.
     *
     * The sitemap asks it: since BIB-31 each Quote is an address of its own,
     * and it is the address that brings visits — what gets pasted somewhere is
     * a Quote, not a catalogue sheet.
     *
     * @return Collection<int, Quote>
     */
    public static function quotes(): Collection
    {
        return Quote::query()
            ->whereHas('book', self::onlyVisible(...))
            ->with('book')
            ->orderBy('id')
            ->get();
    }

    /**
     * One Quote, as a visitor reaches it by its own address (BIB-31).
     *
     * A Quote inherits visibility from its Book and has none of its own
     * (ADR-003): the Quote of a Hidden Book is not found, which is the same
     * answer an address that was never a Quote gets. The image of that Quote is
     * not served either, and that is checked where it is served — a file on a
     * disk knows nothing about any of this.
     */
    public static function quote(int $id): Quote
    {
        /** @var Quote $quote */
        $quote = Quote::query()
            ->whereHas('book', self::onlyVisible(...))
            ->with('book')
            ->findOrFail($id);

        return $quote;
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
