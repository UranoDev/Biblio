<?php

namespace App\Ai;

use App\Enums\Origin;
use App\Enums\RegenerationTarget;
use App\Jobs\ProposeForBook;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\AgentResponse;

/**
 * Asking the AI for what it proposes about a Book, and storing what came back.
 *
 * Everything happens in the queue and never during capture (ADR-006): a burst
 * of twenty Scans cannot wait for twenty answers from an API. Registering the
 * Book and writing its Categories are therefore two moments, and in between the
 * Book is a Book without Categories — which the Shelf has to be able to tell
 * apart from one that was never going to get any.
 *
 * A Book whose catalogue Description came in another language is asked for a
 * second thing in the same pass: its translation into Spanish (BIB-45). It is a
 * request of its own, and only the Books that need it pay for it.
 */
final class Proposals
{
    /**
     * Queues the AI for a Book: its Categories, plus its Description when no
     * Source brought one (ADR-007).
     *
     * Returns false when no AiModel has been chosen: generation is off, nothing
     * is queued, and registering a Book goes on working exactly the same.
     *
     * Queueing it again is also how a Book is regenerated —because the AiModel
     * changed, because the provider failed— since nothing is written until the
     * whole answer is there. What is asked for again travels with the job: a
     * Book queued for its Description alone must not have its Categories
     * rewritten on the way through (BIB-22).
     */
    public function queueFor(Book $book, RegenerationTarget $target = RegenerationTarget::Both): bool
    {
        $chosen = AiModel::chosen();

        if (! $chosen instanceof AiModel) {
            return false;
        }

        // The Book is waiting its turn, and its sheet has to say so from this
        // moment and not once the answer arrives. It also records which AiModel
        // it is waiting for, which is the one it was queued with and not the one
        // that happens to be chosen when somebody reads the sheet (BIB-46).
        $book->generationQueued($chosen->provider, $chosen->name);

        ProposeForBook::dispatch($book, $chosen->provider, $chosen->name, $target);

        return true;
    }

    /**
     * Asks the AI and stores what came back.
     *
     * This is what runs inside the queued job, and everything it throws is that
     * job's business: whether to wait or to fail is decided there, with the
     * whole request in front of it.
     *
     * The prompt is built here and not when the Book was queued, which is what
     * makes the list of existing Categories the one there is at the moment of
     * asking. Two hundred Books queued in one sitting would otherwise all be
     * told that no Category exists yet, and every one of them would invent its
     * own spelling — exactly the fragmentation ADR-006 is about.
     */
    public function askFor(
        Book $book,
        string $provider,
        string $aiModel,
        RegenerationTarget $target = RegenerationTarget::Both,
    ): void {
        $withDescription = $target->includesDescriptions() && $book->acceptsAiDescription();

        $response = (new BookAgent($withDescription))
            ->prompt($this->promptFor($book, $withDescription), provider: $provider, model: $aiModel);

        $this->store($book, $response, $target);

        if ($target->includesDescriptions()) {
            $this->translate($book, $provider, $aiModel);
        }
    }

    /**
     * Puts the Description of the catalogue into Spanish, when it is not in
     * Spanish already (BIB-45).
     *
     * It is a second request and it is asked for after the first, not instead
     * of it, because what makes a translation sound like the trade are the
     * Categories of the Book — and on a Book that has just been registered they
     * did not exist a moment ago. Asking in this order means the translation
     * reads the ones the same run just wrote.
     *
     * Nothing is asked for when the Description already reads as Spanish: that
     * is what the language of the text is stored for, and the request that does
     * not happen is the one that neither costs quota nor risks coming back
     * different from what the catalogue said.
     */
    private function translate(Book $book, string $provider, string $aiModel): void
    {
        $book->refresh();

        if (! $book->descriptionAwaitsTranslation()) {
            return;
        }

        $original = $book->descriptionToTranslate();

        if ($original === null) {
            return;
        }

        $response = (new TranslationAgent)
            ->prompt($this->translationPromptFor($book, $original), provider: $provider, model: $aiModel);

        $translation = Translation::from($response);

        if (! $translation instanceof Translation) {
            throw UnusableAnswer::for($book, (string) $response);
        }

        // Asked again, with the answer already in hand: in between, somebody
        // may have written the Description by hand, and mine is never
        // overwritten (ADR-007). What gets written is that reading of the Book
        // and not the one from before the request.
        $current = $book->fresh();

        if (! $current instanceof Book || ! $current->descriptionAwaitsTranslation()) {
            return;
        }

        $current->writeTranslatedDescription($translation->description, $translation->provider, $translation->aiModel);
    }

    /**
     * What is asked to translate one Description: the text, and what the Book
     * is about so that the words come out in the vocabulary of the trade.
     */
    private function translationPromptFor(Book $book, string $original): string
    {
        $lines = ['Title: '.$book->title];

        if ($book->authors !== []) {
            $lines[] = 'Authors: '.implode(', ', $book->authors);
        }

        /** @var list<string> $subjects */
        $subjects = $book->categories->pluck('name')->all();

        $lines[] = $subjects === []
            ? 'No subjects are recorded for this book, so translate it in plain, unmarked Spanish.'
            : 'Subjects of the book, use the vocabulary of these fields: '.implode(', ', $subjects);

        $lines[] = '';
        $lines[] = 'Description to translate:';
        $lines[] = $original;

        return implode(PHP_EOL, $lines);
    }

    /**
     * Stores what the AI answered for the Book that was queued.
     *
     * It goes in one transaction and only once the whole answer is readable: a
     * failure leaves the Book untouched —not half written— and pending, which
     * is what makes queueing it again safe.
     */
    private function store(Book $book, AgentResponse $response, RegenerationTarget $target): void
    {
        $proposal = Proposal::from($response);

        if (! $proposal instanceof Proposal) {
            throw UnusableAnswer::for($book, (string) $response);
        }

        DB::transaction(function () use ($book, $proposal, $target): void {
            // The answer arrived: the Book is no longer waiting its turn,
            // whichever half of it was asked for (BIB-22). The pair it was
            // waiting for goes with the wait: from here on what was asked of
            // whom is written on the Categories and on the Description.
            $attributes = [
                'generation_queued_at' => null,
                'generation_provider' => null,
                'generation_ai_model' => null,
            ];

            // A regeneration of Descriptions alone leaves the Categories where
            // they were. The answer carries them anyway —the agent always
            // proposes them, and it is the same single request either way—, and
            // what decides is what was asked for, not what came back.
            if ($target->includesCategories()) {
                $book->categories()->sync($this->categoryIds($proposal->categories, $book));

                $attributes['categories_provider'] = $proposal->provider;
                $attributes['categories_ai_model'] = $proposal->aiModel;

                // What the AI just wrote is the AI's again: the corrections by
                // hand were the ones there were before, and they are gone. The
                // warning that asked whether to replace them belongs to the
                // press of the button, not to the whole life of the Book.
                $attributes['categories_edited_at'] = null;
            }

            // The Description of the Source and mine are never overwritten, not
            // even by a regeneration (ADR-007). The check is made again here
            // and not only before prompting, because in between somebody may
            // have written one by hand.
            if ($target->includesDescriptions() && $proposal->description !== null && $book->acceptsAiDescription()) {
                $attributes['description'] = $proposal->description;
                $attributes['description_origin'] = Origin::Ai;
                $attributes['description_provider'] = $proposal->provider;
                $attributes['description_ai_model'] = $proposal->aiModel;
            }

            $book->forceFill($attributes)->save();
        });
    }

    /**
     * What is asked about one Book: its Record, and the Categories that already
     * exist so that they get reused instead of being invented again with
     * another spelling (ADR-006).
     */
    private function promptFor(Book $book, bool $withDescription): string
    {
        $lines = ['Title: '.$book->title];

        if ($book->authors !== []) {
            $lines[] = 'Authors: '.implode(', ', $book->authors);
        }

        if ($book->publisher !== null) {
            $lines[] = 'Publisher: '.$book->publisher;
        }

        if ($book->year !== null) {
            $lines[] = 'Year: '.$book->year;
        }

        if ($book->isbn !== null) {
            $lines[] = 'ISBN: '.$book->isbn;
        }

        if ($book->hasDescription()) {
            $lines[] = 'Description: '.$book->description;
        }

        $canonical = Category::query()->canonical()->orderBy('name')->pluck('name')->all();

        $lines[] = '';
        $lines[] = "Pick from this list of categories. Only propose a new one when the book genuinely does not fit any of them:\n- ".implode("\n- ", $canonical);

        if ($withDescription) {
            $lines[] = '';
            $lines[] = 'No source brought a description for this edition. Write one.';
        }

        return implode("\n", $lines);
    }

    /**
     * The Categories by those names, reusing the ones already there.
     *
     * They are matched by slug, which is what makes "Realismo mágico" and
     * "realismo magico" the same Category instead of two.
     *
     * When the AI proposes a name that is not in the canonical list, the
     * Category is created as non-canonical and the Book that triggered it is
     * recorded, so the admin can review and approve it (BIB-68).
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    private function categoryIds(array $names, Book $book): array
    {
        $ids = [];

        foreach ($names as $name) {
            $slug = Str::slug($name);

            if ($slug === '') {
                continue;
            }

            $category = Category::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);

            if ($category->wasRecentlyCreated && ! $category->is_canonical) {
                $category->forceFill(['suggested_by_book_id' => $book->getKey()])->save();
            }

            $ids[] = (int) $category->getKey();
        }

        return array_values(array_unique($ids));
    }
}
