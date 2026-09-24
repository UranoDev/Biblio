<?php

use App\Models\Quote;
use App\Search\FoldedText;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The Search of Quotes: the case where the phrase is remembered and the book is
 * not.
 *
 * It is the other half of the Search of the Shelf, which goes by title and by
 * author, and it shares its rule: the Search is inside what is mine and never
 * against a Source. So each result carries the Book it came out of, because
 * that is the answer somebody came here for.
 *
 * Everything is here, what is Hidden included: this screen is behind the login,
 * and the Showcase is the one that filters (ADR-003). What is Hidden carries
 * its mark, the same as on the Shelf.
 */
new #[Title('Búsqueda de citas')] class extends Component
{
    use WithPagination;

    /** How many results one page holds. */
    private const PER_PAGE = 20;

    /** The words being looked for, inside the Quotes and nowhere else. */
    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        // Page four of the previous search says nothing about this one.
        $this->resetPage();
    }

    /** Back to the screen as it opens. */
    public function clearSearch(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    /**
     * What was typed, as the comparison reads it. The Snippet marks these same
     * words, so what comes out highlighted is what was actually looked for.
     *
     * @return list<string>
     */
    #[Computed]
    public function words(): array
    {
        return FoldedText::words($this->search);
    }

    /** Whether anything was typed worth asking the database about. */
    #[Computed]
    public function searching(): bool
    {
        return $this->words !== [];
    }

    /**
     * The Quotes that hold every word, with the Book of each one already
     * loaded: the list draws the Book beside the Quote, and a query per result
     * is twenty queries for one screen.
     *
     * They come out in the order of a shelf -- by Book, and inside the Book by
     * page -- and not by how well each one matched: with a handful of words
     * there is no ranking worth the name, and two Quotes of the same Book
     * landing together is what makes the list readable.
     *
     * @return LengthAwarePaginator<int, Quote>
     */
    #[Computed]
    public function results(): LengthAwarePaginator
    {
        return Quote::query()
            ->matching($this->search)
            ->with('book')
            ->join('books', 'books.id', '=', 'quotes.book_id')
            ->orderBy('books.title')
            ->orderByRaw('quotes.page is null, quotes.page, quotes.id')
            ->select('quotes.*')
            ->paginate(self::PER_PAGE);
    }
}; ?>

<div class="flex w-full max-w-3xl flex-col gap-8">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">Búsqueda de citas</flux:heading>

        <flux:text>
            Busca una Cita por sus palabras cuando recuerdas la frase y no el Libro. Cada
            resultado dice de qué Libro es.
        </flux:text>
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="Una o varias palabras"
            autocomplete="off"
            label="Búsqueda"
            description="Los acentos no cambian el resultado. Con varias palabras, se buscan las Citas que las tienen todas."
            class="max-w-md"
            data-test="search-field"
        />

        @if ($this->searching)
            <flux:button variant="subtle" wire:click="clearSearch" data-test="clear-search">
                Limpiar
            </flux:button>
        @endif
    </div>

    <flux:separator />

    @if (! $this->searching)
        <flux:text data-test="idle">Escribe una palabra de la Cita que buscas.</flux:text>
    @elseif ($this->results->isEmpty())
        <flux:text data-test="empty">Ninguna Cita tiene esas palabras.</flux:text>
    @else
        <flux:text data-test="count">
            {{ $this->results->total() }} {{ $this->results->total() === 1 ? 'Cita' : 'Citas' }}.
        </flux:text>

        <ul class="flex flex-col gap-8" data-test="results">
            @foreach ($this->results as $quote)
                <li wire:key="quote-{{ $quote->getKey() }}">
                    <figure class="flex flex-col gap-3">
                        {{--
                            The fragment, with what was searched marked inside
                            it. It is one piece of the Quote and not the Quote:
                            the line breaks it was typed with are read on the
                            sheet of the Book, one link away.
                        --}}
                        <blockquote class="type-quote">
                            <x-snippet
                                :text="$quote->text"
                                :words="$this->words"
                                data-test="snippet-{{ $quote->getKey() }}"
                            />
                        </blockquote>

                        <figcaption class="flex flex-wrap items-center gap-x-3 gap-y-1 pl-5">
                            <a
                                href="{{ route('book', $quote->book) }}"
                                wire:navigate
                                class="type-book-title hover:underline"
                                data-test="book-{{ $quote->book->getKey() }}"
                            >
                                {{ $quote->book->title }}
                            </a>

                            @if (filled($quote->book->authors))
                                <span class="type-authors text-zinc-500 dark:text-zinc-400">
                                    {{ implode(' · ', $quote->book->authors) }}
                                </span>
                            @endif

                            @if ($quote->page)
                                <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">
                                    p. {{ $quote->page }}
                                </span>
                            @endif

                            @if ($quote->book->is_hidden)
                                <flux:badge size="sm" data-test="hidden-mark">Oculto</flux:badge>
                            @endif
                        </figcaption>
                    </figure>
                </li>
            @endforeach
        </ul>

        {{--
            The controls are written here and not taken from the pagination
            component of Flux, which labels its pages in English.
        --}}
        @if ($this->results->hasPages())
            <div class="flex flex-wrap items-center justify-between gap-3" data-test="pages">
                <flux:text>
                    Página {{ $this->results->currentPage() }} de {{ $this->results->lastPage() }}
                </flux:text>

                <div class="flex gap-2">
                    <flux:button
                        variant="subtle"
                        icon="chevron-left"
                        wire:click="previousPage"
                        :disabled="$this->results->onFirstPage()"
                        data-test="previous-page"
                    >
                        Anterior
                    </flux:button>

                    <flux:button
                        variant="subtle"
                        icon-trailing="chevron-right"
                        wire:click="nextPage"
                        :disabled="! $this->results->hasMorePages()"
                        data-test="next-page"
                    >
                        Siguiente
                    </flux:button>
                </div>
            </div>
        @endif
    @endif
</div>
