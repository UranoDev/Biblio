<?php

namespace App\Jobs;

use App\Ai\Proposals;
use App\Ai\ProviderFailure;
use App\Models\Book;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Asks the AI what it proposes for one Book: its Categories and, when no Source
 * brought one, its Description (ADR-006, ADR-007).
 *
 * Capturing the shelf is two hundred Books in one sitting, and every provider
 * puts a ceiling on how fast it will answer. So the queue paces itself —the
 * rhythm lives in the shared cache, where several workers add up to one— and
 * anything transient goes back to the queue with a growing wait instead of
 * failing. Two hundred Books take as long as they take and end on their own,
 * with nothing left in `failed_jobs` for somebody to retry by hand.
 *
 * What waiting does not fix fails at once: an invalid key, a model that does
 * not exist, an answer that does not meet the schema. And the waiting has a
 * cap, because something that has been retrying all morning is not transient
 * any more.
 *
 * On the `sync` connection there is no queue to go back to —a released job is
 * work silently dropped— so the pacing does not apply there. Nothing about this
 * is meant to run during capture anyway.
 */
class ProposeForBook implements ShouldQueue
{
    use Queueable;

    /** The name of the limiter, registered in AppServiceProvider. */
    public const LIMITER = 'ai-provider';

    /**
     * Unlimited attempts, on purpose: with the pace throttling the queue there
     * is no way to know how many attempts a Book needs, and every wait would
     * spend one. The cap is time, and it is in retryUntil().
     */
    public int $tries = 0;

    public bool $deleteWhenMissingModels = true;

    /**
     * The provider and the AiModel are taken at the moment of queueing and
     * travel with the job: choosing another one on the Settings screen changes
     * what gets queued from then on, not the two hundred already in line.
     */
    public function __construct(
        public Book $book,
        public string $provider,
        public string $aiModel,
    ) {}

    /**
     * The cap. Past it the job fails and lands in `failed_jobs`, which is where
     * a provider that has been down all morning belongs.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours((int) config('proposals.give_up_after', 6));
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return $this->job?->getConnectionName() === 'sync'
            ? []
            : [new RateLimited(self::LIMITER)];
    }

    public function handle(Proposals $proposals): void
    {
        try {
            $proposals->askFor($this->book, $this->provider, $this->aiModel);
        } catch (Throwable $failure) {
            if (ProviderFailure::isTransient($failure)) {
                $this->waitAndTryAgain();

                return;
            }

            // Not rethrown: with unlimited attempts, throwing would leave an
            // invalid key retrying until the cap runs out. It fails now.
            $this->fail($failure);

            return;
        }

        Cache::forget($this->waitsKey());
    }

    /**
     * Called when the job gives up: the cap ran out, or what happened was never
     * going to get better. The Book says so on its sheet, so that one waiting
     * its turn and one that failed do not look alike.
     */
    public function failed(?Throwable $failure): void
    {
        Cache::forget($this->waitsKey());

        if (! $this->book->exists) {
            return;
        }

        $this->book->generationFailed(
            $failure instanceof Throwable ? $failure->getMessage() : 'The queue gave up on it.'
        );
    }

    /**
     * Back to the queue, waiting longer each time.
     *
     * The count of waits lives in the cache and not in the job, because a
     * released job comes back with the payload it was queued with. It cannot be
     * the number of attempts either: those also go up every time the pace
     * throttles the job, and a Book at the back of a queue of two hundred would
     * end up waiting hours for its first real failure.
     */
    private function waitAndTryAgain(): void
    {
        $key = $this->waitsKey();
        $hours = (int) config('proposals.give_up_after', 6);

        Cache::add($key, 0, now()->addHours($hours + 1));

        $waits = (int) Cache::increment($key);

        $first = (int) config('proposals.first_wait', 60);
        $longest = (int) config('proposals.longest_wait', 900);

        $this->release(min($first * 2 ** max($waits - 1, 0), $longest));
    }

    private function waitsKey(): string
    {
        return 'ai-waits:'.$this->book->getKey();
    }
}
