<?php

use App\Enums\ReadingStatus;
use App\Enums\Status;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The Shelf: the private screen where I see what I have.
 *
 * The Cover is the dominant element, so this is a grid of Covers and not a
 * table of rows: two hundred books are recognised by their jacket long before
 * they are read off a list.
 *
 * Everything is here, including what is Hidden: the Showcase is the one that
 * filters (ADR-003), and this screen is behind the login. What is Hidden
 * carries its mark, because that is the difference this screen cannot let get
 * guessed.
 *
 * It does not know anything about the AI: the buttons to regenerate are BIB-22.
 * The screen has to work on a library where nobody ever chose an AiModel, so
 * nothing here assumes a Book has Categories.
 */
new #[Title('Acervo')] class extends Component
{
    /** The Search, inside my own Books: title and Authors, never a Source. */
    public string $search = '';

    /** Which Status is being looked at; empty is both. */
    public string $status = '';

    /** Which of the three Reading states; empty is all of them. */
    public string $reading = '';

    /** The slug of the Category being looked at; empty is all of them. */
    public string $category = '';

    public function mount(): void
    {
        // The screen is called Acervo and opens on it. What is Wanted is not
        // hidden -- it is one tab away, with its count in sight.
        $this->status = Status::Owned->value;
    }

    /** Back to the first sight of the screen. */
    public function clearFilters(): void
    {
        $this->reset('search', 'reading', 'category');

        $this->status = Status::Owned->value;
    }

    /**
     * The Books the grid draws.
     *
     * The Categories are loaded with them and not one by one: the grid shows
     * two hundred of these and a query per Book is the difference between a
     * screen and a wait.
     *
     * @return Collection<int, Book>
     */
    #[Computed]
    public function books(): Collection
    {
        return $this->filtered()
            ->with('categories')
            ->orderBy('title')
            ->get();
    }

    /**
     * The Categories worth offering: the ones some Book actually has. A list of
     * filters that find nothing is not a filter, it is a menu.
     *
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()
            ->whereHas('books')
            ->orderBy('name')
            ->get();
    }

    /**
     * How many Books each tab holds, counting the Search and the other filters
     * but not the Status itself -- a tab announcing a number that changes when
     * you press it is announcing nothing.
     *
     * The three counts come out of one grouped query, so the tabs cost the same
     * whether there are ten Books or two hundred.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $counted = $this->filtered(byStatus: false)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            '' => (int) $counted->sum(),
            Status::Owned->value => (int) $counted->get(Status::Owned->value, 0),
            Status::Wanted->value => (int) $counted->get(Status::Wanted->value, 0),
        ];
    }

    /**
     * The other tab, when this one came out empty and that one did not.
     *
     * Without it, searching a Book that is Wanted from the Acervo tab answers
     * «no hay nada», and the honest answer is that it is registered and on the
     * other list. It costs no query of its own: the counts are already there
     * for the tabs.
     */
    #[Computed]
    public function elsewhere(): ?Status
    {
        $looking = $this->statusFilter();

        if (! $looking instanceof Status || $this->books->isNotEmpty()) {
            return null;
        }

        $other = $looking === Status::Owned ? Status::Wanted : Status::Owned;

        return $this->counts[$other->value] > 0 ? $other : null;
    }

    /** Whether anything is narrowing what the grid shows. */
    #[Computed]
    public function filtering(): bool
    {
        return trim($this->search) !== ''
            || $this->reading !== ''
            || $this->category !== ''
            || $this->status !== Status::Owned->value;
    }

    /**
     * Everything the screen asks the database, in one place: the grid, the
     * counts and the tabs are the same question with the Status in or out.
     *
     * @return Builder<Book>
     */
    private function filtered(bool $byStatus = true): Builder
    {
        $query = Book::query();

        if (trim($this->search) !== '') {
            $query->matching($this->search);
        }

        if ($this->readingFilter() instanceof ReadingStatus) {
            $query->inReadingStatus($this->readingFilter());
        }

        if ($this->category !== '') {
            $query->inCategory($this->category);
        }

        if ($byStatus && $this->statusFilter() instanceof Status) {
            $query->where('status', $this->statusFilter());
        }

        return $query;
    }

    private function statusFilter(): ?Status
    {
        return Status::tryFrom($this->status);
    }

    private function readingFilter(): ?ReadingStatus
    {
        return ReadingStatus::tryFrom($this->reading);
    }
}; ?>

<div class="flex w-full max-w-6xl flex-col gap-8">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">Acervo</flux:heading>

        <flux:text>
            Los Libros registrados, con su portada y su lectura a la vista. Aquí se ven
            todos, incluso los que están ocultos en la Vitrina.
        </flux:text>
    </div>

    <div class="flex flex-col gap-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="Título o autor"
            autocomplete="off"
            label="Búsqueda"
            description="Busca entre tus Libros. Para dar de alta uno que no tienes, usa el alta por título."
            data-test="search-field"
        />

        <div class="flex flex-wrap items-end gap-4">
            <flux:radio.group wire:model.live="status" variant="segmented" label="Situación" data-test="status-filter">
                <flux:radio value="" label="Todo ({{ $this->counts[''] }})" />
                <flux:radio
                    value="{{ \App\Enums\Status::Owned->value }}"
                    label="Acervo ({{ $this->counts[\App\Enums\Status::Owned->value] }})"
                />
                <flux:radio
                    value="{{ \App\Enums\Status::Wanted->value }}"
                    label="Por comprar ({{ $this->counts[\App\Enums\Status::Wanted->value] }})"
                />
            </flux:radio.group>

            <flux:select wire:model.live="reading" label="Lectura" class="max-w-56" data-test="reading-filter">
                <flux:select.option value="">Toda lectura</flux:select.option>

                @foreach (\App\Enums\ReadingStatus::cases() as $state)
                    <flux:select.option value="{{ $state->value }}">{{ $state->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            {{--
                A library where nobody chose an AiModel has no Categories at
                all, and an empty filter would be a promise in the present of
                something that may never have run.
            --}}
            @if ($this->categories->isNotEmpty())
                <flux:select wire:model.live="category" label="Categoría" class="max-w-56" data-test="category-filter">
                    <flux:select.option value="">Todas las categorías</flux:select.option>

                    @foreach ($this->categories as $aCategory)
                        <flux:select.option value="{{ $aCategory->slug }}">{{ $aCategory->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @if ($this->filtering)
                <flux:button variant="subtle" wire:click="clearFilters" data-test="clear-filters">
                    Quitar los filtros
                </flux:button>
            @endif
        </div>
    </div>

    <flux:separator />

    @if ($this->books->isEmpty())
        <div class="flex flex-col gap-2" data-test="empty">
            <flux:text>
                @if ($this->filtering)
                    Ningún Libro coincide con lo que buscas.
                @else
                    Todavía no has registrado ningún Libro.
                @endif
            </flux:text>

            @if ($this->elsewhere)
                <flux:text data-test="elsewhere">
                    @if ($this->elsewhere === \App\Enums\Status::Wanted)
                        Hay {{ $this->counts[\App\Enums\Status::Wanted->value] }} en «Por comprar».
                    @else
                        Hay {{ $this->counts[\App\Enums\Status::Owned->value] }} en «Acervo».
                    @endif
                </flux:text>
            @endif
        </div>
    @else
        {{--
            The grid. The Cover is the scaled-down version: the original weighs
            what a Cover weighs, and two hundred of those is the difference
            between a screen and a download.
        --}}
        <ul
            class="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
            data-test="grid"
        >
            @foreach ($this->books as $book)
                <li wire:key="book-{{ $book->getKey() }}" class="flex flex-col gap-3">
                    <a
                        href="{{ route('book', $book) }}"
                        wire:navigate
                        class="group flex flex-col gap-3"
                        data-test="book-{{ $book->getKey() }}"
                    >
                        {{--
                            Tilted six degrees, with its shadow and its edge:
                            the Cover reads as an object and not as a rectangle
                            glued to the page. The tilt is the token, so it is
                            the same six degrees everywhere.
                        --}}
                        <div class="px-2 pt-1">
                            <x-cover
                                :book="$book"
                                class="rotate-(--cover-tilt) shadow-(--shadow-cover) transition-transform group-hover:rotate-0"
                            />
                        </div>

                        <div class="flex min-w-0 flex-col gap-1">
                            <span class="type-book-title line-clamp-3">{{ $book->title }}</span>

                            @if (filled($book->authors))
                                <span class="type-authors line-clamp-2 text-zinc-500 dark:text-zinc-400">
                                    {{ implode(' · ', $book->authors) }}
                                </span>
                            @endif
                        </div>
                    </a>

                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <x-reading-mark :status="$book->readingStatus()" />

                        @if ($book->status === \App\Enums\Status::Wanted)
                            <flux:badge size="sm" data-test="wanted-mark">Por comprar</flux:badge>
                        @endif

                        @if ($book->is_hidden)
                            <flux:badge size="sm" data-test="hidden-mark">Oculto</flux:badge>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <flux:text data-test="count">
            {{ $this->books->count() }} {{ $this->books->count() === 1 ? 'Libro' : 'Libros' }}.
        </flux:text>
    @endif
</div>
