<?php

use App\Ai\AiModel;
use App\Concerns\RegeneratesBooks;
use App\Enums\CoverOrigin;
use App\Enums\GenerationStatus;
use App\Enums\ReadingStatus;
use App\Enums\Status;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
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
 * The Book gets asked of the AI again from here, one by one and without
 * leaving the grid (BIB-22): what came out poor is corrected where it is seen.
 * The screen still has to work on a library where nobody ever chose an AiModel,
 * so nothing here assumes a Book has Categories.
 */
new #[Title('Acervo')] class extends Component
{
    use RegeneratesBooks;

    /** The Search, inside my own Books: title and Authors, never a Source. */
    #[Url(as: 'q', except: '')]
    public string $search = '';

    /**
     * Which Status is being looked at; empty is both.
     *
     * The screen is called Acervo and opens on it: what is Wanted is not
     * hidden, it is one tab away with its count in sight. The default sits on
     * the property and no longer in a `mount()`, so that what arrives in the
     * address wins over it -- the Dashboard links here already narrowed
     * (BIB-40).
     */
    #[Url(except: Status::Owned->value)]
    public string $status = Status::Owned->value;

    /**
     * Which Reading is being looked at: one of the three states, or both read
     * ones at once; empty is all of them.
     */
    #[Url(except: '')]
    public string $reading = '';

    /** The slug of the Category being looked at; empty is all of them. */
    #[Url(except: '')]
    public string $category = '';

    /**
     * Where the Cover came from; empty is anywhere. Generated is the queue of
     * what is left to photograph (ADR-008), and it is what this filter exists
     * for.
     */
    #[Url(except: '')]
    public string $cover = '';

    /** What the AI is doing with the Book: in the queue, or failed (BIB-33). */
    #[Url(except: '')]
    public string $generation = '';

    /** Only the Books nobody has typed a single Quote from. */
    #[Url(as: 'without-quotes', except: false)]
    public bool $withoutQuotes = false;

    /**
     * Whether the grid is narrowed to the Books left on another AiModel: the
     * candidates to regenerate after changing the model (ADR-006).
     */
    #[Url(as: 'another-model', except: false)]
    public bool $fromAnotherAiModel = false;

    /** Back to the first sight of the screen. */
    public function clearFilters(): void
    {
        $this->reset(
            'search',
            'status',
            'reading',
            'category',
            'cover',
            'generation',
            'withoutQuotes',
            'fromAnotherAiModel',
        );
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
            || $this->cover !== ''
            || $this->generation !== ''
            || $this->withoutQuotes
            || $this->fromAnotherAiModel
            || $this->status !== Status::Owned->value;
    }

    /**
     * Whether any of the Books on screen is waiting for the AI.
     *
     * It is what decides whether the grid keeps looking: an answer arrives when
     * the queue gets to it, and until then nothing on this screen changes. With
     * nothing in line there is nothing to wait for, and the screen stops asking.
     */
    #[Computed]
    public function awaiting(): bool
    {
        return $this->books->contains(fn (Book $book): bool => $book->isGenerationQueued());
    }

    /** The Book the grid is allowed to ask the AI about: any of its own. */
    protected function bookToRegenerate(int $id): ?Book
    {
        return Book::query()->find($id);
    }

    /** The grid is drawn again, with the Book that just went back in line. */
    protected function afterRegenerating(Book $book): void
    {
        unset($this->books, $this->counts, $this->categories, $this->awaiting, $this->elsewhere);
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

        // Both read states at once is not a fourth state: it is the question
        // «¿lo leí?», which is the one the Dashboard counts.
        if ($this->reading === ReadingStatus::ANY_READ) {
            $query->read();
        } elseif ($this->readingFilter() instanceof ReadingStatus) {
            $query->inReadingStatus($this->readingFilter());
        }

        if ($this->category !== '') {
            $query->inCategory($this->category);
        }

        if ($this->coverFilter() instanceof CoverOrigin) {
            $query->withCoverOrigin($this->coverFilter());
        }

        match ($this->generationFilter()) {
            GenerationStatus::Pending => $query->awaitingGeneration(),
            GenerationStatus::Failed => $query->withFailedGeneration(),
            default => null,
        };

        if ($this->withoutQuotes) {
            $query->withoutQuotes();
        }

        // The Books left on another AiModel. Without a chosen one there is
        // nothing to compare against, and the filter is not offered either.
        if ($this->fromAnotherAiModel && $this->aiModel instanceof AiModel) {
            $query->fromAnotherAiModel($this->aiModel);
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

    private function coverFilter(): ?CoverOrigin
    {
        return CoverOrigin::tryFrom($this->cover);
    }

    /**
     * Only two of the four situations narrow the grid: the other two -- having
     * it generated, and there being no AiModel chosen -- are not a queue
     * anybody works through.
     */
    private function generationFilter(): ?GenerationStatus
    {
        return match (GenerationStatus::tryFrom($this->generation)) {
            GenerationStatus::Pending => GenerationStatus::Pending,
            GenerationStatus::Failed => GenerationStatus::Failed,
            default => null,
        };
    }
}; ?>

{{--
    The chosen AiModel is read once and handed to every Book: asking for it
    inside the loop is the same question two hundred times, which is the one
    thing a grid of two hundred Covers cannot do.
--}}
@php($chosen = $this->aiModel)

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

                @foreach (\App\Enums\ReadingStatus::filterOptions() as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
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

            {{--
                Where the Cover came from. The generated ones are the queue of
                what is left to photograph, which is the number the Dashboard
                prints (ADR-008, BIB-40).
            --}}
            <flux:select wire:model.live="cover" label="Portada" class="max-w-56" data-test="cover-filter">
                <flux:select.option value="">Toda portada</flux:select.option>

                @foreach (\App\Enums\CoverOrigin::cases() as $origin)
                    <flux:select.option value="{{ $origin->value }}">{{ $origin->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            {{--
                What the AI still owes: what is in line and what it gave up on,
                which are not the same thing (BIB-33).
            --}}
            <flux:select wire:model.live="generation" label="Generación" class="max-w-56" data-test="generation-filter">
                <flux:select.option value="">Toda generación</flux:select.option>

                <flux:select.option value="{{ \App\Enums\GenerationStatus::Pending->value }}">
                    {{ \App\Enums\GenerationStatus::Pending->label() }}
                </flux:select.option>

                <flux:select.option value="{{ \App\Enums\GenerationStatus::Failed->value }}">
                    {{ \App\Enums\GenerationStatus::Failed->label() }}
                </flux:select.option>
            </flux:select>

            <flux:checkbox
                wire:model.live="withoutQuotes"
                label="Sin citas"
                description="Los que todavía no tienen ninguna Cita escrita."
                data-test="without-quotes-filter"
            />

            {{--
                Only with a chosen AiModel: «otro modelo» has nothing to mean
                while there is no current one to be other than (ADR-006).
            --}}
            @if ($chosen)
                <flux:checkbox
                    wire:model.live="fromAnotherAiModel"
                    label="Con otro modelo"
                    description="Los que quedaron con un modelo distinto al de ahora."
                    data-test="another-ai-model-filter"
                />
            @endif

            @if ($this->filtering)
                <flux:button variant="subtle" wire:click="clearFilters" data-test="clear-filters">
                    Quitar los filtros
                </flux:button>
            @endif
        </div>
    </div>

    @if ($this->confirmingBook)
        <x-regeneration-confirm :book="$this->confirmingBook" />
    @endif

    @if ($regenerationNotice)
        <flux:text class="text-base" data-test="regeneration-notice">{{ $regenerationNotice }}</flux:text>
    @endif

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
            @if ($this->awaiting) wire:poll.5s @endif
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
                            {{-- The stamp of the Reading goes inside the box
                                 that leans, not next to it: that is what keeps
                                 the word parallel to the Cover (BIB-38). --}}
                            <div class="relative rotate-(--cover-tilt) transition-transform group-hover:rotate-0">
                                <x-cover :book="$book" class="shadow-(--shadow-cover)" />

                                <x-reading-stamp :book="$book" />
                            </div>
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

                    {{-- Here the Reading is the stamp and nothing else: the
                         grid does not show the year, so a Book read in 2019 and
                         one read without a date look alike, and the sheet is
                         where they stop looking alike. --}}
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        @if ($book->status === \App\Enums\Status::Wanted)
                            <flux:badge size="sm" data-test="wanted-mark">Por comprar</flux:badge>
                        @endif

                        @if ($book->is_hidden)
                            <flux:badge size="sm" data-test="hidden-mark">Oculto</flux:badge>
                        @endif

                        <x-generation-mark :book="$book" />
                    </div>

                    <div class="flex">
                        <x-regenerate-book :book="$book" :chosen="$chosen" />
                    </div>
                </li>
            @endforeach
        </ul>

        <flux:text data-test="count">
            {{ $this->books->count() }} {{ $this->books->count() === 1 ? 'Libro' : 'Libros' }}.
        </flux:text>
    @endif
</div>
