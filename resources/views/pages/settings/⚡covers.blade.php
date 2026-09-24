<?php

use App\Covers\MissingCovers;
use App\Models\Book;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The screen of the Covers that are still owed an image (BIB-57).
 *
 * A catalog answering the ISBN without an image of that edition is the normal
 * case: the Book gets registered showing the generated piece (ADR-008) and
 * nothing ever comes back for it. This is where those are counted and where
 * the Sources get asked again, which is what `books:backfill-covers` does from
 * the terminal.
 */
new #[Title('Portadas')] class extends Component
{
    public ?string $notice = null;

    /** How many Books there is still somewhere to look for a Cover for. */
    #[Computed]
    public function pending(): int
    {
        return MissingCovers::count();
    }

    /** Of those, the ones where a download was tried and gave up (BIB-52). */
    #[Computed]
    public function failed(): int
    {
        return Book::query()->withFailedCoverDownload()->count();
    }

    /** How many catalogs each Book can end up costing a request to. */
    #[Computed]
    public function sources(): int
    {
        return count((array) config('sources.order', []));
    }

    /**
     * What the line of what is missing says: how many Books, and at most how
     * many requests that is. At most, because a Book whose first catalog has
     * the image costs one and the cascade stops there.
     */
    #[Computed]
    public function summary(): string
    {
        if ($this->pending === 0) {
            return 'Ningún libro quedó sin portada y con dónde buscarla.';
        }

        $most = $this->pending * $this->sources;

        return ($this->pending === 1 ? 'Un libro muestra' : $this->pending.' libros muestran')
            .' la portada generada: '
            .($most === 1 ? 'es una petición.' : 'son hasta '.$most.' peticiones.');
    }

    /**
     * Puts the question in the queue. It is the same thing
     * `books:backfill-covers` does, reachable from the screen that counts
     * them.
     */
    public function backfill(): void
    {
        $queued = MissingCovers::queue();

        unset($this->pending, $this->failed, $this->summary);

        $this->notice = match ($queued) {
            0 => 'No quedaba ningún libro al que preguntarle.',
            1 => 'Un libro entró a la cola.',
            default => $queued.' libros entraron a la cola.',
        };
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout
        heading="Portadas"
        subheading="Los libros que muestran la portada generada y todavía tienen dónde buscar una imagen. Se les pregunta a las fuentes en orden y se guarda la primera portada que alguna tenga."
    >
        <div class="flex flex-col gap-8">
            @if ($notice)
                <flux:text class="text-base" data-test="notice">{{ $notice }}</flux:text>
            @endif

            <div class="flex flex-col gap-2" data-test="pending">
                <flux:heading size="lg">Lo que falta</flux:heading>

                <flux:text data-test="pending-count">{{ $this->summary }}</flux:text>

                @if ($this->failed)
                    <flux:text data-test="failed-count">
                        {{ $this->failed === 1
                            ? 'A uno de ellos ya se le había bajado una imagen que no servía. Esa dirección se salta y se busca en las demás fuentes.'
                            : 'A '.$this->failed.' de ellos ya se les había bajado una imagen que no servía. Esas direcciones se saltan y se busca en las demás fuentes.' }}
                    </flux:text>
                @endif

                @if ($this->pending)
                    <flux:button wire:click="backfill" variant="primary" class="self-start" data-test="backfill">
                        {{ $this->pending === 1
                            ? 'Buscar esa portada'
                            : 'Buscar esas '.$this->pending.' portadas' }}
                    </flux:button>

                    <flux:text variant="subtle" data-test="nothing-found">
                        Los libros que ninguna fuente tenga se quedan con su portada generada.
                    </flux:text>
                @endif
            </div>
        </div>
    </x-pages::settings.layout>
</section>
