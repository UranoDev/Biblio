<?php

namespace App\Jobs;

use App\Models\Quote;
use App\Quotes\Credits;
use App\Quotes\OutOfCredits;
use App\Quotes\RenderFailed;
use App\Quotes\ShareImages;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Draws the two shareable images of one Quote (ADR-009).
 *
 * It is queued because storing a Quote must never wait for a browser somewhere
 * else to lay out a page and take a picture of it. And it is drawn on storing
 * and not when somebody asks for it: the first to ask for a shared page is
 * usually the robot of the network, in a hurry and with a memory, and a link
 * that went out without an image stays without one for days.
 *
 * What happens when the renderer says no is the interesting half:
 *
 * - **Out of credits stops the queue.** The month ran out, and it is the same
 *   month for the two hundred Quotes behind this one. Retrying is spending the
 *   next month's quota the instant it renews, so the switch goes down, the
 *   Quotes stay owed their images, and a person turns it back on.
 * - **A transient failure goes back to the queue** with a growing wait, like
 *   anything that gets better by itself.
 * - **Anything else fails now.** An invalid key does not improve by being tried
 *   four times, and each attempt is paid for.
 */
class RenderQuoteImages implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Quote $quote) {}

    /** A network drop or a service falling over is worth a few attempts. */
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

    public function handle(ShareImages $images): void
    {
        // The month ran out and nothing has been paid since. Leaving without
        // drawing is the point: the Quote stays owed its images and is counted
        // with the rest of what is waiting for credits.
        if (Credits::areOut()) {
            return;
        }

        // Longer than the canvas holds. There is no smaller size left to set
        // it in, so there is nothing to draw and no attempt to pay for; the
        // shared link goes out with the Cover of the Book (ADR-009).
        if (! $this->quote->imagesFit()) {
            return;
        }

        // No renderer configured. It is not a failure of this Quote and it does
        // not deserve four attempts and a row in `failed_jobs`; the screen that
        // counts what is pending is where it shows.
        if (! $images->available()) {
            return;
        }

        try {
            $images->renderFor($this->quote);
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
     * Called when the job gives up. The Quote says so, so that one on its way
     * and one the renderer refused do not look alike on the screen that counts
     * them.
     */
    public function failed(?Throwable $failure): void
    {
        if (! $this->quote->exists) {
            return;
        }

        $this->quote->imagesFailed(
            $failure instanceof Throwable ? $failure->getMessage() : 'The queue gave up on it.'
        );
    }
}
