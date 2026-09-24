<?php

namespace App\Ai;

use App\Enums\RegenerationTarget;
use App\Jobs\ProposeForBook;
use App\Models\Book;
use App\Models\Regeneration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;

/**
 * The mass regeneration: asking the AI again for the Categories and the
 * Descriptions of the Books already registered (BIB-21).
 *
 * It is the run that follows changing the AiModel. It goes through the queue
 * like everything else the AI is asked (ADR-006) —two hundred requests are not
 * made inside a web request— and it goes in a batch, because that is what makes
 * the progress readable from outside the browser that launched it.
 *
 * The rule that makes it safe is ADR-007: the Description of the Source and the
 * one I wrote are never reached. It is applied here, in the query that picks
 * the Books, and again when the answer gets stored.
 */
final class Regenerations
{
    /** How many Books the run would reach, and how many it leaves out. */
    public function plan(RegenerationTarget $target, bool $onlyPrevious): RegenerationPlan
    {
        return new RegenerationPlan(
            $target,
            $onlyPrevious,
            $this->books($target, $onlyPrevious)->count(),
            $target->includesDescriptions()
                ? Book::query()->descriptionKept()->count()
                : 0,
            $target->includesDescriptions()
                ? $this->books($target, $onlyPrevious)->descriptionAwaitingTranslation()->count()
                : 0,
        );
    }

    /**
     * Launches the run and returns it, or null when no AiModel has been chosen:
     * generation is off and there is nobody to ask.
     */
    public function start(RegenerationTarget $target, bool $onlyPrevious): ?Regeneration
    {
        $chosen = AiModel::chosen();

        if (! $chosen instanceof AiModel) {
            return null;
        }

        return $this->queue(
            $this->books($target, $onlyPrevious),
            $target,
            $onlyPrevious,
            $chosen,
        );
    }

    /**
     * Throws the run at the Books the queue gave up on, and at those alone.
     *
     * A failed Book is marked on its own sheet (BIB-33), which is what makes
     * this possible without having to remember which run it belonged to.
     */
    public function retryFailed(RegenerationTarget $target = RegenerationTarget::Both): ?Regeneration
    {
        $chosen = AiModel::chosen();

        if (! $chosen instanceof AiModel) {
            return null;
        }

        return $this->queue(
            Book::query()->withFailedGeneration(),
            $target,
            onlyPrevious: false,
            chosen: $chosen,
        );
    }

    /** How many Books the queue gave up on, whichever run they came from. */
    public function failed(): int
    {
        return Book::query()->withFailedGeneration()->count();
    }

    /**
     * The Books one run reaches.
     *
     * With `$onlyPrevious` it is the ones left on another AiModel, which is
     * what keeps changing the model from costing two hundred requests when
     * twenty are on the old one (ADR-006). Without it, every Book the target
     * allows.
     *
     * @return Builder<Book>
     */
    private function books(RegenerationTarget $target, bool $onlyPrevious): Builder
    {
        $chosen = AiModel::chosen();

        $query = Book::query();

        if (! $target->includesDescriptions()) {
            return $onlyPrevious && $chosen instanceof AiModel
                ? $query->categoriesFromAnotherAiModel($chosen)
                : $query;
        }

        if (! $target->includesCategories()) {
            return $onlyPrevious && $chosen instanceof AiModel
                ? $query->descriptionFromAnotherAiModel($chosen)
                : $query->descriptionWritable();
        }

        // Both: a Book is in when either half of it is. Asking about it costs
        // one request whichever of the two brought it in.
        if (! $onlyPrevious || ! $chosen instanceof AiModel) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($chosen): void {
            $query->categoriesFromAnotherAiModel($chosen)
                ->orWhere(fn (Builder $query) => $query->descriptionFromAnotherAiModel($chosen));
        });
    }

    /**
     * Writes down the run and puts its Books in the queue.
     *
     * The batch allows failures: one Book the provider chokes on does not stop
     * the other hundred and ninety-nine, and the one that failed says so on its
     * own sheet so that it can be thrown at again by itself.
     *
     * @param  Builder<Book>  $books
     */
    private function queue(Builder $books, RegenerationTarget $target, bool $onlyPrevious, AiModel $chosen): Regeneration
    {
        /** @var list<Book> $queued */
        $queued = $books->get()->all();

        $regeneration = new Regeneration;

        $regeneration->target = $target;
        $regeneration->only_previous = $onlyPrevious;
        $regeneration->total = count($queued);
        $regeneration->kept = $target->includesDescriptions()
            ? Book::query()->descriptionKept()->count()
            : 0;

        if ($queued === []) {
            $regeneration->save();

            return $regeneration;
        }

        // Every one of them is waiting its turn again, so a failure from the
        // previous run stops showing on the sheet the moment the new one is
        // queued and not once its answer arrives (BIB-33), and each sheet says
        // it is in line from that same moment (BIB-22).
        Book::query()
            ->whereKey(array_map(static fn (Book $book): int => (int) $book->getKey(), $queued))
            ->update([
                'generation_queued_at' => now(),
                'generation_failed_at' => null,
                'generation_failure' => null,
                'generation_provider' => $chosen->provider,
                'generation_ai_model' => $chosen->name,
            ]);

        $batch = Bus::batch(array_map(
            fn (Book $book): ProposeForBook => new ProposeForBook($book, $chosen->provider, $chosen->name, $target),
            $queued,
        ))
            ->name('regeneration')
            ->allowFailures()
            ->dispatch();

        $regeneration->batch_id = $batch->id;
        $regeneration->save();

        return $regeneration;
    }
}
