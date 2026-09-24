<?php

namespace App\Books;

use App\Enums\ShareFormat;
use App\Models\Book;
use App\Quotes\ImageRenderer;
use App\Quotes\OutOfCredits;
use App\Quotes\RenderFailed;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Draws the shareable card of a Book and keeps it (BIB-50).
 *
 * The renderer is the one of ADR-009 and it lives in `App\Quotes` because that
 * is where the decision that brought it in was written down. It is not a thing
 * of the Quotes: it takes HTML and gives back a PNG, and it knows nothing about
 * what is in the page. Moving it would rename half the ADR for no gain.
 *
 * What is of the Book is everything else, and it is one image where a Quote has
 * two: the wide one a network takes on its own when the link is pasted. There
 * is no vertical one, because the vertical one exists for Instagram and a Book
 * has nothing there to upload by hand.
 *
 * Like the images of a Quote, what the renderer hands back is written into a
 * file of our own, on the configured disk (ADR-004), and served by a route of
 * the application. That is what makes the decision reversible: the day the
 * service closes, the cards already drawn keep working.
 */
final readonly class ShareImage
{
    public function __construct(
        private ImageRenderer $renderer,
        private string $directory,
    ) {}

    /** Whether there is anything to draw with at all. */
    public function available(): bool
    {
        return $this->renderer->available();
    }

    /**
     * Draws the card of the Book and stores it, replacing whatever was there.
     *
     * @throws OutOfCredits|RenderFailed
     */
    public function renderFor(Book $book): void
    {
        $fingerprint = $book->shareImageFingerprint();

        $bytes = $this->renderer->render(ShareCard::of($book), ShareFormat::shared());

        $previous = $book->share_image_path;

        $book->shareImageStored(
            path: $this->write($book, $fingerprint, $bytes),
            fingerprint: $fingerprint,
        );

        // The old file goes once the new one is written and written down, the
        // same order the Covers are replaced in: a crash in between leaves two
        // files on the disk, which costs space, and the other order leaves a
        // Book pointing at nothing.
        if ($previous !== null) {
            Storage::disk()->delete($previous);
        }
    }

    /**
     * Writes the file. Its name carries the fingerprint, so a render of the
     * same Book never lands on the name of the one it is replacing — the disk
     * may be a bucket in front of a CDN the day S3 is turned on.
     */
    private function write(Book $book, string $fingerprint, string $bytes): string
    {
        $path = sprintf(
            '%s/%d/card-%s-%s.png',
            $this->directory,
            $book->getKey(),
            $fingerprint,
            Str::lower((string) Str::ulid()),
        );

        Storage::disk()->put($path, $bytes);

        return $path;
    }
}
