<?php

namespace App\Jobs;

use App\Covers\CoverStore;
use App\Covers\InvalidImage;
use App\Models\Book;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Downloads the Cover the Source pointed at and stores it (ADR-004).
 *
 * It is queued because capturing happens in bursts: the image takes longer than
 * the Record and the Book has to end up registered without waiting for it. If
 * the download fails, the Book is left without a Cover and with its
 * `cover_source_url` intact, which is what makes it possible to retry later or
 * to upload one by hand.
 */
class DownloadCover implements ShouldQueue
{
    use Queueable;

    /** Network drops, 5xx and disk failures retry on their own. */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Book $book) {}

    /**
     * Writes down the Cover URL and queues the download. Without a URL there is
     * nothing to download and the Book is left without a Cover, which is a valid
     * state.
     */
    public static function forBook(Book $book, ?string $url): void
    {
        if ($url === null || trim($url) === '') {
            return;
        }

        $book->forceFill(['cover_source_url' => trim($url)])->save();

        self::dispatch($book);
    }

    /**
     * Queues the download again. Returns false when there is nowhere to download
     * it from —ManualEntry, or a Source that brought no image—: there the only
     * way is to upload it by hand.
     */
    public static function retry(Book $book): bool
    {
        if ($book->cover_source_url === null || trim($book->cover_source_url) === '') {
            return false;
        }

        self::dispatch($book);

        return true;
    }

    public function handle(CoverStore $store): void
    {
        $url = $this->book->cover_source_url;

        if ($url === null || trim($url) === '') {
            return;
        }

        // A network drop or a timeout raises ConnectionException and the queue
        // retries, which is the right thing: the image is still there.
        $response = Http::timeout((int) config('covers.timeout', 10))->get($url);

        if ($response->serverError()) {
            throw new RuntimeException(
                "The Cover of Book {$this->book->getKey()} answered {$response->status()}."
            );
        }

        if (! $response->successful()) {
            $this->giveUp("answered {$response->status()}");

            return;
        }

        try {
            // If the disk fails, CoverNotStored goes out of here uncaught: that
            // one does get better by retrying.
            $store->store($this->book, $response->body());
        } catch (InvalidImage $failure) {
            $this->giveUp($failure->getMessage());
        }
    }

    /**
     * The download is not going to go better by retrying it on its own. The Book
     * is left without a Cover and the URL stays stored, so the manual retry and
     * the upload are still available.
     */
    private function giveUp(string $reason): void
    {
        Log::warning('The Cover could not be downloaded.', [
            'book' => $this->book->getKey(),
            'url' => $this->book->cover_source_url,
            'reason' => $reason,
        ]);
    }
}
