<?php

namespace App\Concerns;

use App\Ai\AiModel;
use App\Ai\Proposals;
use App\Enums\RegenerationTarget;
use App\Models\Book;
use Livewire\Attributes\Computed;

/**
 * Asking the AI again for what it wrote about one Book, from the screen where
 * the Book is being looked at (BIB-22).
 *
 * Two screens press the same button —the grid of the Shelf and the sheet of the
 * Book— and there is one reason for that button: what the AI wrote came out
 * poor, or it was written with an AiModel that is no longer the chosen one. The
 * work is the queue's (ADR-006); what lives here is the button, what the screen
 * says while the answer is on its way, and the two questions that have to be
 * answered before anything is queued.
 *
 * The first is ADR-007: the Description of the Source and mine are never
 * overwritten, so asking for one of those is refused before it costs a request.
 * The second is the Categories corrected by hand, which are replaced only after
 * a yes.
 */
trait RegeneratesBooks
{
    /**
     * The Book whose Categories were corrected by hand and that is waiting for
     * a yes before they get replaced.
     */
    public ?int $confirming = null;

    /** What that Book was going to be asked for again. */
    public string $confirmingTarget = '';

    /** What the last press of the button did, read at a glance and never in a modal. */
    public ?string $regenerationNotice = null;

    /** The AiModel chosen on the Settings screen, or null while there is none. */
    #[Computed]
    public function aiModel(): ?AiModel
    {
        return AiModel::chosen();
    }

    /** The Book the warning is about, so that the screen can name it. */
    #[Computed]
    public function confirmingBook(): ?Book
    {
        return $this->confirming === null ? null : $this->bookToRegenerate($this->confirming);
    }

    /** What that Book was going to be asked for again. */
    #[Computed]
    public function confirmingFor(): RegenerationTarget
    {
        return RegenerationTarget::tryFrom($this->confirmingTarget) ?? RegenerationTarget::Both;
    }

    /**
     * Puts the Book back in the queue, or asks first when what is about to be
     * replaced was corrected by hand.
     */
    public function regenerate(int $book, string $target = 'both'): void
    {
        $this->cancelRegeneration();

        $found = $this->bookToRegenerate($book);

        if (! $found instanceof Book) {
            return;
        }

        $asked = RegenerationTarget::tryFrom($target) ?? RegenerationTarget::Both;

        if ($asked->includesCategories() && $found->categoriesEditedByHand()) {
            $this->confirming = (int) $found->getKey();
            $this->confirmingTarget = $asked->value;
            $this->regenerationNotice = null;

            return;
        }

        $this->queueRegeneration($found, $asked);
    }

    /** Yes: replace the Categories that were corrected by hand. */
    public function confirmRegeneration(): void
    {
        $book = $this->confirmingBook;
        $asked = $this->confirmingFor;

        $this->cancelRegeneration();

        if ($book instanceof Book) {
            $this->queueRegeneration($book, $asked);
        }
    }

    /** No: the corrections by hand stay, and nothing is queued. */
    public function cancelRegeneration(): void
    {
        $this->confirming = null;
        $this->confirmingTarget = '';

        unset($this->confirmingBook, $this->confirmingFor);
    }

    /** What each screen reads again once the Book is back in the queue. */
    protected function afterRegenerating(Book $book): void {}

    /**
     * The Book this screen is allowed to ask about, or null when the id is not
     * one of its own.
     */
    abstract protected function bookToRegenerate(int $id): ?Book;

    /**
     * Queues the request and says what it is going to reach.
     *
     * A Description that the Source brought or that I wrote is not asked for:
     * on its own it is refused, and inside «both» it is left out of the
     * sentence, because promising in the present that it is going to be
     * rewritten is promising something ADR-007 forbids.
     *
     * The one from the Source in another language is the exception, and it is
     * not an exception to that rule: it is not rewritten either, it is
     * translated (BIB-45). So it is asked for, and the sentence says the other
     * thing.
     */
    private function queueRegeneration(Book $book, RegenerationTarget $target): void
    {
        $translates = $target->includesDescriptions() && $book->descriptionAwaitsTranslation();
        $keepsDescription = $target->includesDescriptions()
            && ! $translates
            && ! $book->acceptsAiDescription();

        if ($keepsDescription && ! $target->includesCategories()) {
            $this->regenerationNotice = 'La descripción de «'.$book->title
                .'» no la escribió la IA, así que ninguna regeneración la toca.';

            return;
        }

        $reaches = $keepsDescription ? RegenerationTarget::Categories : $target;

        if (! app(Proposals::class)->queueFor($book, $reaches)) {
            $this->regenerationNotice = 'La generación está apagada: falta elegir el modelo.';

            return;
        }

        $this->regenerationNotice = '«'.$book->title.'» volvió a la cola: '
            .($translates ? $reaches->translatedOnOneBook() : $reaches->queuedOnOneBook()).'.'
            .($keepsDescription ? ' Su descripción se queda como está: no la escribió la IA.' : '');

        unset($this->aiModel);

        $this->afterRegenerating($book);
    }
}
