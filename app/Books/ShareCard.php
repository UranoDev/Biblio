<?php

namespace App\Books;

use App\Enums\CoverSize;
use App\Enums\ShareFormat;
use App\Models\Book;
use App\Quotes\Fonts;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Throwable;

/**
 * The page the renderer is handed: the card of a Book, standing on its own
 * (BIB-50).
 *
 * It is the twin of `App\Quotes\ShareCard` and it is a class of its own and not
 * a branch inside that one, because what the two hand the renderer is not the
 * same page: a Quote has words to set and two canvases to set them on, and a
 * Book has a Cover, a title and its Authors on one.
 *
 * What both do is the same, and it is the part that matters: the stylesheet
 * written in, the typefaces written in as data, and the Cover written in as
 * data. The renderer runs somewhere else and may not be able to reach this site
 * at all, and a page that has nothing to fetch cannot come out half drawn.
 */
final readonly class ShareCard
{
    /**
     * Past this many characters the title steps down a size.
     *
     * It is not about fitting: the column beside the Cover holds eight lines of
     * the large size and no title of a book is that long. It is about weight —
     * four lines of the large size outweigh the Cover next to them, and the
     * card stops reading as a book and starts reading as a headline.
     *
     * The number is an estimate, and deliberately one: the real one depends on
     * the face and on the width the card leaves for the text, and what this is
     * for is choosing between two sizes, not laying out the title. Measured at
     * the large size over that column, at about three lines.
     */
    private const LONG = 68;

    private function __construct(private string $title) {}

    public static function for(Book $book): self
    {
        return new self(trim($book->title));
    }

    /**
     * Whether the title is long enough to be set at the smaller of the two
     * sizes.
     *
     * It steps down and is never cut: the title is the one thing this card
     * exists to say, and the card is also what gets shared as an image, where
     * an ellipsis leads nowhere.
     */
    public function titleIsLong(): bool
    {
        return mb_strlen($this->title) > self::LONG;
    }

    /**
     * The HTML of the card of one Book.
     */
    public static function of(Book $book): string
    {
        return view('books.share', [
            'book' => $book,
            'format' => ShareFormat::shared(),
            'styles' => self::styles(),
            'cover' => self::cover($book),
        ])->render();
    }

    /**
     * The stylesheet, inlined: the typefaces first and the compiled sheet after
     * them.
     *
     * The compiled sheet is a build artifact, and without a build it comes back
     * empty. That is the one degradation allowed here, because it is the loud
     * one: an unstyled image is wrong at a glance. The typefaces are the
     * opposite —a fallback face is a silent wrong— so they never depend on the
     * build and a missing file throws.
     */
    private static function styles(): string
    {
        return Fonts::faces()."\n".self::compiled();
    }

    private static function compiled(): string
    {
        try {
            return (string) Vite::content('resources/css/app.css');
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * The stored Cover as data.
     *
     * A Book with no stored Cover has no card at all (BIB-50), so this being
     * null means the file went missing under a column that still names it —
     * and then the card draws the generated piece, which is drawn in the page
     * and has nothing to fetch either (ADR-008).
     */
    private static function cover(Book $book): ?string
    {
        $path = $book->coverPathFor(CoverSize::Full);

        if ($path === null || ! Storage::disk()->exists($path)) {
            return null;
        }

        $bytes = Storage::disk()->get($path);

        if ($bytes === null || $bytes === '') {
            return null;
        }

        $type = Storage::disk()->mimeType($path);

        return 'data:'.($type === false ? 'image/jpeg' : $type).';base64,'.base64_encode($bytes);
    }
}
