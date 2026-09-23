<?php

use App\Enums\ScanOutcome;
use App\Intake\RegisterScan;
use App\Intake\ScanResult;
use App\Models\Book;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Capture by Scan: an always-focused ISBN field and a burst of twenty books
 * without a single click in between (ADR-001).
 *
 * Nothing that happens here opens a modal or asks for confirmation, not even to
 * report that a Book was already there: a modal takes the focus away, the
 * Scanner types into the void and the book does not get registered with nothing
 * to say so.
 */
new #[Title('Escaneo')] class extends Component
{
    public string $isbn = '';

    /** How the last Scan ended. It is read at a glance and does not interrupt. */
    public ?string $notice = null;

    /**
     * The ISBN no Source knows. It is kept so ManualEntry starts out with it and
     * it does not have to be typed again.
     */
    public ?string $isbnWithoutSource = null;

    /**
     * What has been scanned in this burst, the most recent on top.
     *
     * @var list<array{key: string, outcome: string, label: string, color: string, title: string|null, isbn: string, book: int|null, undoable: bool}>
     */
    public array $burst = [];

    public function register(RegisterScan $scan): void
    {
        $typed = trim($this->isbn);

        // The field is always emptied, even when the Scan registered nothing:
        // whatever is left typed sticks to the next code.
        $this->reset('isbn', 'isbnWithoutSource');

        if ($typed !== '') {
            $this->writeDown($scan->register($typed));
        }

        $this->giveTheFocusBack();
    }

    /** Sends back to Wanted the Book the rescan moved to the Shelf. */
    public function undo(string $key): void
    {
        foreach ($this->burst as $position => $entry) {
            if ($entry['key'] !== $key || ! $entry['undoable']) {
                continue;
            }

            Book::query()->find($entry['book'])?->moveBackToWanted();

            $this->burst[$position]['undoable'] = false;
            $this->burst[$position]['label'] = 'Regresó a Por comprar';
            $this->burst[$position]['color'] = 'zinc';

            $this->notice = $this->quoted($entry['title']).' vuelve a estar Por comprar.';
        }

        $this->giveTheFocusBack();
    }

    private function writeDown(ScanResult $result): void
    {
        $this->notice = $this->noticeFor($result);

        if ($result->offersManualEntry()) {
            $this->isbnWithoutSource = $result->isbn?->value();
        }

        array_unshift($this->burst, [
            'key' => (string) Str::uuid(),
            'outcome' => $result->outcome->value,
            'label' => $result->outcome->label(),
            'color' => $this->colorFor($result->outcome),
            'title' => $result->book?->title,
            'isbn' => $result->isbn?->value() ?? $result->typed,
            'book' => $result->book?->getKey(),
            'undoable' => $result->canBeUndone(),
        ]);
    }

    private function noticeFor(ScanResult $result): string
    {
        $title = $this->quoted($result->book?->title);

        return match ($result->outcome) {
            ScanOutcome::Registered => $title.' quedó registrado.',
            ScanOutcome::AlreadyOnShelf => $title.' ya está en el Acervo.',
            ScanOutcome::MovedToShelf => $title.' estaba Por comprar y pasó al Acervo.',
            ScanOutcome::UnknownIsbn => 'Ninguna Fuente conoce el ISBN '.$result->isbn.'.',
            ScanOutcome::SourceUnavailable => 'Las Fuentes no pudieron contestar por el ISBN '.$result->isbn.'. Vuelve a escanearlo.',
            ScanOutcome::InvalidIsbn => $this->quoted($result->typed).' no es un ISBN.',
        };
    }

    private function colorFor(ScanOutcome $outcome): string
    {
        return match ($outcome) {
            ScanOutcome::Registered, ScanOutcome::MovedToShelf => 'emerald',
            ScanOutcome::AlreadyOnShelf => 'zinc',
            default => 'amber',
        };
    }

    private function quoted(?string $text): string
    {
        return '«'.($text ?? '').'»';
    }

    /**
     * The field gets the focus back after every Scan, without anyone touching
     * anything. If the focus goes away, the Scanner types into the void
     * (ADR-001).
     */
    private function giveTheFocusBack(): void
    {
        $this->dispatch('focus-isbn');
    }
}; ?>

<div
    class="flex w-full flex-col gap-8"
    x-data="{
        timer: null,
        wait: {{ (int) config('intake.idle') }},
        focus() {
            this.$refs.isbn?.focus()
        },
        stop() {
            clearTimeout(this.timer)
        },
        onIdle(value) {
            this.stop()

            if (! this.looksComplete(value)) {
                return
            }

            this.timer = setTimeout(() => {
                this.$wire.set('isbn', value, false)
                this.$wire.register()
            }, this.wait)
        },
        looksComplete(value) {
            const clean = value.replace(/[\s-]/g, '').toUpperCase()

            if (/^\d{13}$/.test(clean)) {
                return true
            }

            // Ten digits starting with 978 or 979 are not an ISBN-10: they are
            // an ISBN-13 still missing keystrokes.
            return /^\d{9}[\dX]$/.test(clean) &amp;&amp; ! /^97[89]/.test(clean)
        },
    }"
    x-init="focus()"
    x-on:focus-isbn.window="focus()"
>
    <div class="flex flex-col gap-1">
        <flux:heading size="xl">Escaneo</flux:heading>
        <flux:text>Pasa el código de barras por el Escáner y el Libro se registra con la Ficha de la Fuente.</flux:text>
    </div>

    <form wire:submit="register" x-on:submit="stop()" class="max-w-md">
        <flux:input
            x-ref="isbn"
            wire:model="isbn"
            x-on:input="onIdle($event.target.value)"
            label="ISBN"
            description="Los Escáneres que no mandan Enter registran solos, al dejar de teclear."
            autofocus
            autocomplete="off"
            inputmode="numeric"
            data-test="isbn-field"
        />
    </form>

    @if ($notice)
        <flux:text class="text-base" data-test="notice">{{ $notice }}</flux:text>
    @endif

    @if ($isbnWithoutSource)
        <flux:button
            variant="subtle"
            class="self-start"
            :href="route('manual-entry', ['isbn' => $isbnWithoutSource])"
            wire:navigate
        >
            Capturar a mano el ISBN {{ $isbnWithoutSource }}
        </flux:button>
    @endif

    <div class="flex flex-col gap-3">
        <flux:heading size="lg">Esta ráfaga</flux:heading>

        @forelse ($burst as $entry)
            <div
                wire:key="{{ $entry['key'] }}"
                class="flex items-center gap-4 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700"
            >
                <span @class([
                    'shrink-0 rounded-md px-2 py-1 text-xs font-medium',
                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' => $entry['color'] === 'emerald',
                    'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' => $entry['color'] === 'zinc',
                    'bg-amber-100 text-amber-900 dark:bg-amber-900 dark:text-amber-100' => $entry['color'] === 'amber',
                ])>{{ $entry['label'] }}</span>

                <div class="min-w-0 flex-1">
                    @if ($entry['title'])
                        <flux:text class="truncate font-medium">{{ $entry['title'] }}</flux:text>
                    @endif

                    <flux:text class="truncate text-sm">{{ $entry['isbn'] }}</flux:text>
                </div>

                @if ($entry['undoable'])
                    <flux:button
                        size="sm"
                        variant="subtle"
                        wire:click="undo('{{ $entry['key'] }}')"
                    >
                        Deshacer
                    </flux:button>
                @endif
            </div>
        @empty
            <flux:text>Aquí aparece lo que vayas escaneando, lo último arriba.</flux:text>
        @endforelse
    </div>
</div>
