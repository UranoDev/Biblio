<?php

namespace App\Showcase;

use App\Enums\ShareFormat;
use App\Models\Book;
use App\Models\Category;
use App\Models\Quote;
use App\Quotes\QuoteCard;
use Illuminate\Support\Str;

/**
 * What the head of a public page says about itself: its title, its description,
 * its canonical address and the card a network draws when the link is pasted
 * (BIB-37).
 *
 * It lives beside `Showcase` and not inside each view for the same reason the
 * queries do: a public surface added tomorrow gets its head from here, so there
 * is no way to add one that goes out without a description or without a
 * canonical. The layout draws whatever this hands it and decides nothing.
 *
 * Every text comes out of the content of the page — the Description of a Book,
 * the name of a Category, how many Books there are. Nothing is the name of the
 * site repeated, which is what a search result shows when a page has no
 * description of its own.
 */
final readonly class Meta
{
    /**
     * @param  string|null  $heading  the name of the page, or nothing on the front page
     * @param  string  $type  the Open Graph vertical: `website`, or `book` on a Book
     */
    private function __construct(
        public ?string $heading,
        public string $description,
        public string $canonical,
        public string $type = 'website',
        public ?string $image = null,
        public ?string $imageAlt = null,
    ) {}

    /**
     * The front page: whose library this is, and how big it is.
     */
    public static function showcase(int $books, int $quotes): self
    {
        $size = array_filter([
            self::counted($books, 'un libro', 'libros'),
            self::counted($quotes, 'una cita', 'citas'),
        ]);

        return new self(
            heading: null,
            description: Str::squish(
                'Los libros que tengo, los que quiero tener y las citas que guardé de ellos. '
                .($size === [] ? '' : ucfirst(implode(' y ', $size)).'.')
            ),
            canonical: route('showcase'),
        );
    }

    /**
     * The page of one Book.
     *
     * What the description says was decided on 2026-09-24 looking at the
     * preview a network was drawing on its own: **the Description when the Book
     * has one, and its Authors, publisher and year when it does not** — and,
     * at the end, how many Quotes there are. The state of Reading is left out,
     * because whether I read it is no business of whoever gets the link, and so
     * is the text of the Quotes: one cut mid-sentence says nothing and takes
     * the whole space. The count stays, because that is what tells the person
     * receiving the link that there is something to read here.
     */
    public static function book(Book $book): self
    {
        $about = $book->hasDescription()
            ? Str::squish((string) $book->description)
            : implode(' · ', array_filter([
                implode(', ', $book->authors),
                $book->publisher,
                $book->year,
            ]));

        // A Book with no Description and no Record —a ManualEntry of a title
        // and nothing else— would be left with a description made of the count
        // alone, or with none at all. It gets told where it is instead.
        if ($about === '') {
            $about = 'Un libro del acervo de '.config('app.name').'.';
        }

        $quotes = self::counted($book->quotes->count(), 'una cita', 'citas');

        return new self(
            heading: $book->title,
            description: Str::limit($about, 180).($quotes === null ? '' : ' · '.$quotes),
            canonical: route('showcase.book', $book),
            type: 'book',
            // The horizontal card, and nothing else (BIB-50). A network draws
            // `summary_large_image` in about 1.91:1, and handing it the Cover
            // —which is a book, so about 2:3— makes it zoom into that frame
            // until what is left is a strip of the middle with the letters cut
            // off. The card is drawn at 1200x630, which is the shape it is
            // going to be shown in.
            //
            // While the card is still in the queue the Cover is **not** put in
            // its place: the networks cache `og:image` by URL and for days, so
            // a link shared during that window would keep the very cut this
            // came from. Without a card the Book goes out with the small card
            // and no image, which is the honest one — and it is also where a
            // Book with no stored Cover stays for good, because the generated
            // piece is drawn in the page and no network can take it from there
            // (ADR-008).
            image: $book->shareImageUrl(),
            imageAlt: $book->hasShareImage() ? 'Portada de '.$book->title : null,
        );
    }

    /**
     * The page of one Quote, which is the one made for being pasted somewhere
     * (BIB-31).
     *
     * The description is the Quote itself and nothing around it: what somebody
     * sees in the preview is the words, which is the only reason they would
     * open it. The title is the Book, because the words already fill the line
     * below and repeating them there wastes it.
     *
     * The image is the card rasterized, and while it is not drawn yet it is the
     * Cover of the Book (ADR-009). A link shared during that window does not go
     * out bare: it goes out with the Cover instead of with the card, and a Book
     * always has one to show (ADR-008) — as long as it is a stored one, because
     * the generated piece is drawn in the page and no network can take it from
     * there.
     */
    public static function quote(Quote $quote): self
    {
        $book = $quote->book;
        $words = QuoteCard::for($quote)->text();

        $image = $quote->imageUrlFor(ShareFormat::shared())
            ?? ($book->hasCover() ? $book->coverUrl() : null);

        return new self(
            heading: $book->title,
            description: Str::limit(Str::squish($words), 200),
            canonical: route('showcase.quote', $quote),
            type: 'article',
            image: $image,
            imageAlt: 'Cita de '.$book->title,
        );
    }

    /**
     * The page of one Category: what it is about and how much of it there is.
     */
    public static function category(Category $category, int $books): self
    {
        return new self(
            heading: $category->name,
            description: ucfirst((string) self::counted($books, 'un libro', 'libros'))
                .' de '.$category->name.' en '.config('app.name').'.',
            canonical: route('showcase.category', $category),
        );
    }

    /**
     * The privacy notice (BIB-47).
     */
    public static function privacy(): self
    {
        return new self(
            heading: 'Aviso de privacidad',
            description: 'Qué datos recopila '.config('app.name').' y qué hace con ellos.',
            canonical: route('showcase.privacy'),
        );
    }

    /**
     * The terms of use (BIB-47).
     */
    public static function terms(): self
    {
        return new self(
            heading: 'Términos de uso',
            description: 'Qué es '.config('app.name').' y qué puede hacer un visitante con su contenido.',
            canonical: route('showcase.terms'),
        );
    }

    /**
     * What goes in the `<title>`: the page and then the site, because the tab
     * and the search result get cut from the right.
     */
    public function title(): string
    {
        $name = (string) config('app.name');

        return $this->heading === null ? $name : $this->heading.' · '.$name;
    }

    /**
     * What goes in `og:title`. The name of the site is not repeated here: it
     * travels in `og:site_name`, and a card that says it twice wastes the one
     * line it has.
     */
    public function socialTitle(): string
    {
        return $this->heading ?? (string) config('app.name');
    }

    /**
     * The card a network draws: the big one when there is an image to fill it,
     * and the small one when there is not.
     */
    public function card(): string
    {
        return $this->image === null ? 'summary' : 'summary_large_image';
    }

    /**
     * How many there are, in words a visitor reads: «un libro», «12 libros»,
     * and nothing at all when there are none — «0 citas» is a hole announced.
     *
     * The singular arrives with its article written in, because the article is
     * the one word that cannot be derived: a libro takes «un» and a cita takes
     * «una».
     */
    private static function counted(int $count, string $one, string $plural): ?string
    {
        return match (true) {
            $count < 1 => null,
            $count === 1 => $one,
            default => $count.' '.$plural,
        };
    }
}
