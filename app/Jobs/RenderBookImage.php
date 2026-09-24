<?php

namespace App\Jobs;

use App\Books\ShareImage;
use App\Models\Book;
use App\Quotes\Credits;
use App\Quotes\OutOfCredits;
use App\Quotes\RenderFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Draws the shareable card of one Book (BIB-50).
 *
 * It is the twin of `RenderQuoteImages` and it behaves like it, because what it
 * is protecting is the same: storing a Book must never wait for a browser
 * somewhere else to lay out a page and take a picture of it, and the first to
 * ask for a shared page is usually the robot of the network, in a hurry and
 * with a memory — a link that went out without an image stays without one for
 * days.
 *
 * What happens when the renderer says no is the same too, and for the same
 * reasons:
 *
 * - **Out of credits stops the queue.** The month ran out, and it is the same
 *   month for everything else waiting behind this one.
 * - **A transient failure goes back to the queue** with a growing wait.
 * - **Anything else fails now.** An invalid key does not improve by being tried
 *   four times, and each attempt is paid for.
 */
class RenderBookImage implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Book $book) {}

    /**
     * A network drop or a service falling over is worth a few attempts.
     *
     * The numbers are the ones configured next to the Quotes, and they are read
     * from there on purpose: they describe the renderer —how long it takes to
     * come back, how long to wait before asking again— and not what is being
     * drawn. Two sets of them would drift apart for no reason.
     */
    public function tries(): int
    {
        return (int) config('quotes.tries', 4);
    }

    /**
     * The waits between them, doubling: hitting an overloaded service again at
     * once pushes in the same direction as the cause.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        $first = (int) config('quotes.first_wait', 60);
        $longest = (int) config('quotes.longest_wait', 900);

        return [$first, min($first * 4, $longest), $longest];
    }

    public function handle(ShareImage $images): void
    {
        // The month ran out and nothing has been paid since. Leaving without
        // drawing is the point: the Book stays owed its card and is counted
        // with the rest of what is waiting for credits.
        if (Credits::areOut()) {
            return;
        }

        // No stored Cover, no card (BIB-50): a Book that shows the generated
        // piece goes out without `og:image`, which is the behaviour of ADR-008
        // and the one this job must not spend a credit to change.
        if (! $this->book->hasCover()) {
            return;
        }

        // No renderer configured. It is not a failure of this Book and it does
        // not deserve four attempts and a row in `failed_jobs`.
        if (! $images->available()) {
            return;
        }

        try {
            $images->renderFor($this->book);
        } catch (OutOfCredits $exhausted) {
            Credits::ranOut($exhausted->getMessage());
        } catch (RenderFailed $refused) {
            if ($refused->transient) {
                throw $refused;
            }

            // Not rethrown: with attempts left, throwing would leave an invalid
            // key being tried all afternoon. It fails now.
            $this->fail($refused);
        }
    }

    /**
     * Called when the job gives up. The Book says so, so that one on its way
     * and one the renderer refused do not look alike.
     */
    public function failed(?Throwable $failure): void
    {
        if (! $this->book->exists) {
            return;
        }

        $this->book->shareImageFailed(
            $failure instanceof Throwable ? $failure->getMessage() : 'The queue gave up on it.'
        );
    }
}
