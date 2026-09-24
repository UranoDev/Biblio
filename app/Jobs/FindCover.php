<?php

namespace App\Jobs;

use App\Covers\MissingCovers;
use App\Domain\InvalidIsbn;
use App\Domain\Isbn;
use App\Models\Book;
use App\Sources\SourceResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Asks the Sources whether any of them has a Cover for a Book that has none
 * (BIB-57).
 *
 * It is the half that comes before {@see DownloadCover}: that one downloads the
 * image a Source already pointed at, and this one goes looking for the address
 * when there is none, or when the one on record gave up. What it finds is
 * handed straight over, so the download keeps happening in one place.
 *
 * It is queued for the same reason the download is: it is a request per catalog
 * and the answer is not needed to keep going. Queued apart from the download,
 * too — a Book can find its address and still fail to download it, and the two
 * are different news.
 *
 * Finding nothing is not a failure. It is that no catalog has an image of that
 * edition, which is the case ADR-008 was written for: the Book keeps the
 * typographic piece and nothing gets marked.
 */
class FindCover implements ShouldQueue
{
    use Queueable;

    /** A catalog that could not answer is worth asking again. */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Book $book) {}

    public function handle(SourceResolver $sources): void
    {
        // Between being queued and being run, the Cover may have arrived: the
        // download on its way came back, or a photograph was uploaded. Either
        // way there is nothing left to look for.
        if ($this->book->hasCover() || ! $this->book->acceptsCoverFromSource()) {
            return;
        }

        // An address on record with no verdict on it is a download already on
        // its way. It is the same thing {@see \App\Covers\MissingCovers} leaves
        // out, asked again here because two of these can be queued for the same
        // Book —the screen asked twice— and the first one may have found it.
        if ($this->book->cover_source_url !== null && ! $this->book->coverDownloadHasFailed()) {
            return;
        }

        $isbn = $this->isbn();

        if (! $isbn instanceof Isbn) {
            return;
        }

        // Past that guard, an address on record is one that gave up. It gets
        // skipped and the cascade goes on to the next catalog: handing the same
        // one back would queue a download for the marker it was refused.
        $url = $sources->coverUrl($isbn, $this->book->cover_source_url);

        DownloadCover::forBook($this->book, $url);
    }

    /**
     * The ISBN of the Book, or nothing to ask with.
     *
     * A Book with no ISBN never reaches the queue —{@see MissingCovers} leaves
     * it out— and one stored before the normalization is a row nothing else can
     * fix either. Both leave quietly: there is no question to ask.
     */
    private function isbn(): ?Isbn
    {
        if ($this->book->isbn === null) {
            return null;
        }

        try {
            return Isbn::from($this->book->isbn);
        } catch (InvalidIsbn) {
            return null;
        }
    }
}
