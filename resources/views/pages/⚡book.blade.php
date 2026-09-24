<?php

use App\Ai\AiModel;
use App\Domain\Isbn;
use App\Enums\GenerationStatus;
use App\Enums\Status;
use App\Models\Book;
use App\Models\Category;
use App\Models\Quote;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The sheet of one Book: the Record the Source brought on top, mine underneath.
 *
 * This is where everything no Source knows gets written —the Reading, the Note,
 * the lending, the Status, Hidden— and where the Record gets corrected when the
 * Source got it wrong. Each block saves on its own: correcting a typo in the
 * publisher has nothing to do with writing the Note, and one long form would
 * make them share their validation and their save.
 *
 * The screen does not regenerate anything: the buttons for that are on their own
 * issue, so this sheet does not depend on generation existing. What it does do
 * is say which of the three situations the Categories are in, because a Book
 * without them is not one thing but two (ADR-006).
 */
new #[Title('Ficha del Libro')] class extends Component
{
    public Book $book;

    // The Record, as it can be corrected.

    public string $title = '';

    /**
     * The Authors in the order they are credited. The blank row is the one about
     * to be filled in; on save, the ones left empty are discarded.
     *
     * @var list<string>
     */
    public array $authors = [''];

    public string $publisher = '';

    public string $year = '';

    public string $edition = '';

    public string $isbn = '';

    // Mine.

    public string $description = '';

    public string $note = '';

    public bool $isRead = false;

    public string $readOn = '';

    public string $lentTo = '';

    public string $lentOn = '';

    public string $status = '';

    public bool $isHidden = false;

    /** The Category about to be added, by name. */
    public string $category = '';

    /** The Quote about to be written, and its page when it was written down. */
    public string $quoteText = '';

    public string $quotePage = '';

    /** The Quote being corrected, or null while a new one is being typed. */
    public ?int $editingQuote = null;

    /** What the last save did, read at a glance and never in a modal. */
    public ?string $notice = null;

    public function mount(Book $book): void
    {
        $this->book = $book;

        $this->fillFromBook();
    }

    /** The AiModel chosen on the Settings screen, or null while there is none. */
    #[Computed]
    public function aiModel(): ?AiModel
    {
        return AiModel::chosen();
    }

    /** Which of the three situations the Categories of this Book are in. */
    #[Computed]
    public function categoriesStatus(): GenerationStatus
    {
        return $this->book->categoriesStatus();
    }

    public function addAuthor(): void
    {
        $this->authors[] = '';
    }

    public function removeAuthor(int $position): void
    {
        unset($this->authors[$position]);

        // Reindexing is what keeps the order of the ones left.
        $this->authors = array_values($this->authors);

        if ($this->authors === []) {
            $this->authors = [''];
        }
    }

    /** Corrects the Record when the Source got it wrong. */
    public function saveRecord(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'authors' => ['array'],
            'authors.*' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1450', 'max:'.((int) date('Y') + 1)],
            'edition' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', $this->isbnNotOnAnotherBook()],
        ], [
            'title.required' => 'Falta el título.',
            'title.max' => 'El título no puede pasar de 255 caracteres.',
            'authors.*.max' => 'El nombre del Autor no puede pasar de 255 caracteres.',
            'publisher.max' => 'La editorial no puede pasar de 255 caracteres.',
            'year.integer' => 'El año se escribe con dígitos.',
            'year.min' => 'El año tiene que ser de 1450 en adelante.',
            'year.max' => 'El año no puede ser posterior al que viene.',
            'edition.max' => 'La edición no puede pasar de 255 caracteres.',
        ]);

        $typed = trim($this->isbn);

        $this->book->forceFill([
            'title' => trim($this->title),
            'authors' => $this->writtenAuthors(),
            'publisher' => $this->blankIsNull($this->publisher),
            'year' => trim($this->year) === '' ? null : (int) trim($this->year),
            'edition' => $this->blankIsNull($this->edition),
            'isbn' => $typed === '' ? null : Isbn::from($typed)->value(),
        ])->save();

        $this->fillFromBook();

        $this->notice = 'La ficha quedó corregida.';
    }

    /**
     * The Description I write myself. From here on it is mine and no
     * regeneration touches it (ADR-007).
     */
    public function saveDescription(): void
    {
        $this->validate(
            ['description' => ['nullable', 'string', 'max:5000']],
            ['description.max' => 'La descripción no puede pasar de 5000 caracteres.'],
        );

        $this->book->writeDescription($this->description);

        $this->fillFromBook();

        $this->notice = $this->book->hasDescription()
            ? 'La descripción quedó como tuya.'
            : 'El Libro quedó sin descripción.';
    }

    public function saveNote(): void
    {
        $this->validate(
            ['note' => ['nullable', 'string', 'max:20000']],
            ['note.max' => 'El apunte no puede pasar de 20000 caracteres.'],
        );

        $this->book->writeNote($this->note);

        $this->fillFromBook();

        $this->notice = $this->book->note === null
            ? 'El apunte quedó vacío.'
            : 'El apunte quedó guardado.';
    }

    /**
     * The Reading, with its date when there is one.
     *
     * Marking it read does not force a date, and emptying the date does not
     * unmark the Reading: read without knowing when is a state of its own.
     */
    public function saveReading(): void
    {
        $this->validate(
            ['readOn' => ['nullable', 'date', 'before_or_equal:today']],
            [
                'readOn.date' => 'La fecha de lectura no se entiende.',
                'readOn.before_or_equal' => 'La fecha de lectura no puede estar en el futuro.',
            ],
        );

        if (! $this->isRead) {
            $this->book->markAsUnread();
        } else {
            $this->book->markAsRead(trim($this->readOn) === '' ? null : trim($this->readOn));
        }

        $this->fillFromBook();

        $this->notice = 'La lectura quedó en «'.$this->book->readingStatus()->label().'».';
    }

    public function saveLending(): void
    {
        $this->validate([
            'lentTo' => ['nullable', 'string', 'max:255'],
            'lentOn' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'lentTo.max' => 'El nombre no puede pasar de 255 caracteres.',
            'lentOn.date' => 'La fecha del préstamo no se entiende.',
            'lentOn.before_or_equal' => 'La fecha del préstamo no puede estar en el futuro.',
        ]);

        if (trim($this->lentTo) === '') {
            $this->book->returned();

            $this->fillFromBook();

            $this->notice = 'El Libro ya no está prestado.';

            return;
        }

        $this->book->lendTo(
            trim($this->lentTo),
            trim($this->lentOn) === '' ? null : trim($this->lentOn),
        );

        $this->fillFromBook();

        $this->notice = 'El préstamo quedó anotado.';
    }

    /** The Book came back. The lending is a field, so nothing is kept of it. */
    public function returned(): void
    {
        $this->book->returned();

        $this->fillFromBook();

        $this->notice = 'El Libro ya no está prestado.';
    }

    /** The Status and Hidden: where the Book lives and whether it gets shown. */
    public function saveShelving(): void
    {
        $this->validate(
            ['status' => ['required', Rule::enum(Status::class)]],
            ['status.required' => 'Falta la Situación.'],
        );

        Status::from($this->status) === Status::Owned
            ? $this->book->moveToShelf()
            : $this->book->moveBackToWanted();

        $this->isHidden
            ? $this->book->hide()
            : $this->book->show();

        $this->fillFromBook();

        $this->notice = 'La Situación quedó en «'.$this->book->status->label().'».';
    }

    /**
     * Adds a Category to the Book, reusing the one that is already there.
     *
     * They are matched by slug, which is what makes "Realismo mágico" and
     * "realismo magico" the same Category instead of two.
     */
    public function addCategory(): void
    {
        $name = trim($this->category);
        $slug = Str::slug($name);

        if ($slug === '') {
            $this->addError('category', 'Falta el nombre de la Categoría.');

            return;
        }

        $category = Category::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);

        $this->book->categories()->syncWithoutDetaching([$category->getKey()]);

        $this->reset('category');
        $this->resetValidation();

        $this->book->load('categories');

        $this->notice = '«'.$category->name.'» quedó en este Libro.';
    }

    public function removeCategory(int $id): void
    {
        $this->book->categories()->detach($id);

        $this->book->load('categories');

        $this->notice = 'La Categoría salió de este Libro.';
    }

    /**
     * Writes the Quote, and leaves the field ready for the next one.
     *
     * The Quotes get transcribed in one sitting, one after another, so the same
     * form takes the new one and the correction of an old one: nothing reloads
     * and nothing opens in between.
     */
    public function saveQuote(): void
    {
        $this->validate([
            'quoteText' => ['required', 'string', 'max:5000'],
            'quotePage' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], [
            'quoteText.required' => 'Falta el texto de la cita.',
            'quoteText.max' => 'La cita no puede pasar de 5000 caracteres.',
            'quotePage.integer' => 'La página se escribe con dígitos.',
            'quotePage.min' => 'La página empieza en 1.',
            'quotePage.max' => 'Esa página es demasiado alta.',
        ]);

        $page = trim($this->quotePage) === '' ? null : (int) trim($this->quotePage);

        if ($this->editingQuote === null) {
            $this->book->addQuote($this->quoteText, $page);

            $this->notice = 'La cita quedó guardada.';
        } else {
            $this->quoteOfThisBook($this->editingQuote)->rewrite($this->quoteText, $page);

            $this->notice = 'La cita quedó corregida.';
        }

        $this->resetQuoteForm();
        $this->book->load('quotes');

        // The next one gets typed right away, so the field takes the cursor back.
        $this->dispatch('quote-written');
    }

    /** Brings an existing Quote into the same form, to correct it. */
    public function editQuote(int $id): void
    {
        $quote = $this->quoteOfThisBook($id);

        $this->editingQuote = $quote->getKey();
        $this->quoteText = $quote->text;
        $this->quotePage = $quote->page === null ? '' : (string) $quote->page;

        $this->resetValidation();

        $this->notice = null;

        $this->dispatch('quote-written');
    }

    /** Leaves the Quote as it was and the form ready for a new one. */
    public function cancelQuote(): void
    {
        $this->resetQuoteForm();

        $this->notice = null;
    }

    public function deleteQuote(int $id): void
    {
        $this->quoteOfThisBook($id)->delete();

        if ($this->editingQuote === $id) {
            $this->resetQuoteForm();
        }

        $this->book->load('quotes');

        $this->notice = 'La cita se borró.';
    }

    /**
     * A Quote of this Book, by id. Going through the relation is what keeps the
     * sheet of one Book from editing the Quotes of another.
     */
    private function quoteOfThisBook(int $id): Quote
    {
        return $this->book->quotes()->whereKey($id)->firstOrFail();
    }

    private function resetQuoteForm(): void
    {
        $this->reset('quoteText', 'quotePage', 'editingQuote');

        $this->resetValidation();
    }

    /** Leaves every field showing what is stored, after a save. */
    private function fillFromBook(): void
    {
        $this->book->refresh();
        $this->book->load('categories', 'quotes');

        $this->title = $this->book->title;
        $this->authors = $this->book->authors === [] ? [''] : $this->book->authors;
        $this->publisher = (string) $this->book->publisher;
        $this->year = $this->book->year === null ? '' : (string) $this->book->year;
        $this->edition = (string) $this->book->edition;
        $this->isbn = (string) $this->book->isbn;

        $this->description = (string) $this->book->description;
        $this->note = (string) $this->book->note;
        $this->isRead = $this->book->is_read;
        $this->readOn = $this->book->read_on?->format('Y-m-d') ?? '';
        $this->lentTo = (string) $this->book->lent_to;
        $this->lentOn = $this->book->lent_on?->format('Y-m-d') ?? '';
        $this->status = $this->book->status->value;
        $this->isHidden = $this->book->is_hidden;

        unset($this->categoriesStatus);
    }

    /**
     * An ISBN already registered reports which Book has it instead of
     * duplicating it: the ISBN is unique on the Shelf (ADR-005).
     */
    private function isbnNotOnAnotherBook(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $typed = trim((string) $value);

            if ($typed === '') {
                return;
            }

            if (! Isbn::isValid($typed)) {
                $fail('«'.$typed.'» no es un ISBN.');

                return;
            }

            $other = Book::query()
                ->where('isbn', Isbn::from($typed)->value())
                ->whereKeyNot($this->book->getKey())
                ->first();

            if ($other instanceof Book) {
                $fail('«'.$other->title.'» ya está registrado con ese ISBN.');
            }
        };
    }

    /** @return list<string> */
    private function writtenAuthors(): array
    {
        $written = array_map(trim(...), $this->authors);

        return array_values(array_filter($written, fn (string $author): bool => $author !== ''));
    }

    private function blankIsNull(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}; ?>

<div class="flex w-full max-w-3xl flex-col gap-10">
    <div class="flex min-w-0 flex-col gap-2">
        <flux:heading size="xl">{{ $book->title }}</flux:heading>

        @if (filled($book->authors))
            <span class="type-authors text-zinc-500 dark:text-zinc-400">{{ implode(' · ', $book->authors) }}</span>
        @endif

        <flux:text class="font-mono text-sm" data-test="record-line">
            {{ collect([$book->publisher, $book->year, $book->edition, $book->isbn])->filter()->join(' · ') }}
        </flux:text>

        <flux:text class="text-sm">
            {{ $book->status->label() }} · {{ $book->readingStatus()->label() }}
        </flux:text>
    </div>

    @if ($notice)
        <flux:text class="text-base" data-test="notice">{{ $notice }}</flux:text>
    @endif

    {{-- The Cover, with the pieces that already exist: uploading and retrying. --}}
    <section class="flex flex-col gap-4">
        <x-visibility public :hidden="$book->is_hidden" />

        <livewire:book-cover :book="$book" :key="'cover-'.$book->getKey()" />
    </section>

    <flux:separator />

    {{-- What the Source brought. --}}
    <section class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <x-visibility public :hidden="$book->is_hidden" />
            <flux:heading size="lg">La ficha</flux:heading>

            <flux:text data-test="source">
                @if ($book->source === null)
                    Estos campos no traen Fuente anotada. Corrígelos cuando estén mal.
                @elseif ($book->source === \App\Enums\Source::Manual)
                    Estos campos los escribiste tú en la captura manual.
                @else
                    Estos campos los llenó {{ $book->source->label() }}. Corrígelos cuando se haya equivocado.
                @endif
            </flux:text>
        </div>

        <form wire:submit="saveRecord" class="flex flex-col gap-6">
            <flux:input wire:model="title" label="Título" autocomplete="off" data-test="title-field" />

            <flux:field>
                <flux:label>Autores</flux:label>
                <flux:description>En el orden en que vienen acreditados.</flux:description>

                <div class="flex flex-col gap-2">
                    @foreach ($authors as $index => $author)
                        <div wire:key="author-{{ $index }}" class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <flux:input
                                    wire:model="authors.{{ $index }}"
                                    autocomplete="off"
                                    placeholder="Nombre del Autor"
                                    class="flex-1"
                                />

                                @if (count($authors) > 1)
                                    <flux:button
                                        type="button"
                                        icon="x-mark"
                                        variant="subtle"
                                        wire:click="removeAuthor({{ $index }})"
                                        aria-label="Quitar este Autor"
                                    />
                                @endif
                            </div>

                            <flux:error name="authors.{{ $index }}" />
                        </div>
                    @endforeach

                    <flux:button type="button" variant="subtle" class="self-start" wire:click="addAuthor">
                        Agregar otro Autor
                    </flux:button>
                </div>
            </flux:field>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="publisher" label="Editorial" autocomplete="off" />
                <flux:input wire:model="year" label="Año" inputmode="numeric" autocomplete="off" />
                <flux:input wire:model="edition" label="Edición" autocomplete="off" />
                <flux:input
                    wire:model="isbn"
                    label="ISBN"
                    description="Déjalo en blanco cuando el Libro no traiga código de barras."
                    inputmode="numeric"
                    autocomplete="off"
                    data-test="isbn-field"
                />
            </div>

            <flux:button type="submit" variant="primary" class="self-start">Guardar la ficha</flux:button>
        </form>
    </section>

    <flux:separator />

    {{-- The Description: public, and with its Origin in sight (ADR-007). --}}
    <section class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <x-visibility public :hidden="$book->is_hidden" />
            <flux:heading size="lg">Descripción</flux:heading>

            <flux:text data-test="description-origin">
                @if (! $book->hasDescription())
                    @if ($this->aiModel)
                        Ninguna Fuente trajo descripción. Está en la cola de {{ $this->aiModel->name }}.
                    @else
                        Ninguna Fuente trajo descripción, y falta elegir el modelo para que la IA la escriba.
                    @endif
                @elseif ($book->description_origin === \App\Enums\Origin::Owner)
                    La escribiste tú. Ninguna regeneración la toca.
                @elseif ($book->description_origin === \App\Enums\Origin::Source)
                    La trajo {{ $book->source?->label() ?? 'la Fuente' }}. Ninguna regeneración la toca.
                @elseif ($book->description_origin === \App\Enums\Origin::Ai)
                    La escribió {{ $book->description_ai_model }}. Una regeneración la reescribe.
                @endif
            </flux:text>
        </div>

        <form wire:submit="saveDescription" class="flex flex-col gap-3">
            <flux:textarea
                wire:model="description"
                label="De qué va el Libro"
                description="Si la editas queda como tuya, y desde entonces ninguna regeneración la toca."
                rows="6"
                data-test="description-field"
            />

            <flux:button type="submit" variant="primary" class="self-start">Guardar la descripción</flux:button>
        </form>
    </section>

    <flux:separator />

    {{-- The Categories: public, and the way the Showcase gets navigated. --}}
    <section class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <x-visibility public :hidden="$book->is_hidden" />
            <flux:heading size="lg">Categorías</flux:heading>

            <flux:text data-test="categories-status">
                @switch($this->categoriesStatus)
                    @case(\App\Enums\GenerationStatus::Generated)
                        Las escribió {{ $book->categories_ai_model }}, del proveedor {{ $book->categories_provider }}.
                        @break
                    @case(\App\Enums\GenerationStatus::Pending)
                        Están en la cola de {{ $this->aiModel?->name }}.
                        @break
                    @case(\App\Enums\GenerationStatus::Off)
                        Falta elegir el modelo para que la IA las proponga.
                        @break
                @endswitch
            </flux:text>
        </div>

        @if ($book->categories->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2" data-test="categories">
                @foreach ($book->categories as $each)
                    <span
                        wire:key="category-{{ $each->getKey() }}"
                        class="flex items-center gap-1 rounded-md border border-zinc-200 py-1 pl-3 pr-1 text-sm dark:border-zinc-700"
                    >
                        {{ $each->name }}

                        <flux:button
                            size="xs"
                            icon="x-mark"
                            variant="subtle"
                            wire:click="removeCategory({{ $each->getKey() }})"
                            :aria-label="'Quitar '.$each->name"
                        />
                    </span>
                @endforeach
            </div>
        @else
            <flux:text>Este Libro no tiene Categorías.</flux:text>
        @endif

        <form wire:submit="addCategory" class="flex items-end gap-3">
            <flux:input
                wire:model="category"
                label="Agregar una Categoría"
                placeholder="Nombre de la Categoría"
                autocomplete="off"
                class="max-w-xs"
                data-test="category-field"
            />

            <flux:button type="submit" variant="subtle">Agregar</flux:button>
        </form>
    </section>

    <flux:separator />

    {{-- The Quotes: the words of the Book, and they get published. --}}
    <section class="flex flex-col gap-6">
        <div class="flex flex-col gap-1">
            <x-visibility public :hidden="$book->is_hidden" />
            <flux:heading size="lg">Citas</flux:heading>

            <flux:text>
                Los fragmentos del Libro que quieres conservar, con su página. Se ordenan por página, y las que no la
                traen van al final.
            </flux:text>
        </div>

        {{--
            The same form writes the new Quote and corrects an old one: one
            sitting goes through many of them, and a second form would be a
            second place to look.
        --}}
        <form
            wire:submit="saveQuote"
            class="flex flex-col gap-3"
            x-data
            x-on:quote-written.window="$refs.quoteText?.focus()"
        >
            <flux:textarea
                wire:model="quoteText"
                x-ref="quoteText"
                :label="$editingQuote ? 'Corregir la cita' : 'Cita'"
                description="Los saltos de línea se conservan."
                rows="4"
                data-test="quote-field"
            />

            <div class="flex flex-wrap items-end gap-3">
                <flux:input
                    wire:model="quotePage"
                    label="Página"
                    placeholder="Opcional"
                    inputmode="numeric"
                    autocomplete="off"
                    class="max-w-36"
                    data-test="quote-page-field"
                />

                <flux:button type="submit" variant="primary" data-test="save-quote">
                    {{ $editingQuote ? 'Guardar la cita' : 'Agregar cita' }}
                </flux:button>

                @if ($editingQuote)
                    <flux:button type="button" variant="subtle" wire:click="cancelQuote" data-test="cancel-quote">
                        Cancelar
                    </flux:button>
                @endif
            </div>
        </form>

        @forelse ($book->quotes as $quote)
            <figure wire:key="quote-{{ $quote->getKey() }}" class="flex flex-col gap-2">
                <blockquote class="type-quote whitespace-pre-line">{{ $quote->text }}</blockquote>

                <figcaption class="flex flex-wrap items-center gap-3 pl-5">
                    @if ($quote->page)
                        <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">p. {{ $quote->page }}</span>
                    @endif

                    <flux:button
                        size="xs"
                        variant="subtle"
                        wire:click="editQuote({{ $quote->getKey() }})"
                        data-test="edit-quote"
                    >
                        Editar
                    </flux:button>

                    <flux:button
                        size="xs"
                        variant="subtle"
                        wire:click="deleteQuote({{ $quote->getKey() }})"
                        wire:confirm="¿Borrar esta cita?"
                        data-test="delete-quote"
                    >
                        Borrar
                    </flux:button>
                </figcaption>
            </figure>
        @empty
            <flux:text data-test="quotes-empty">Este Libro no tiene Citas guardadas.</flux:text>
        @endforelse
    </section>

    <flux:separator />

    {{-- The Reading: the mark gets published, the date does not. --}}
    <section class="flex flex-col gap-4">
        <flux:heading size="lg">Lectura</flux:heading>

        <form wire:submit="saveReading" class="flex flex-col gap-6">
            {{-- La marca de leído se publica; la fecha en que lo leí, no. --}}
            <div class="flex flex-col gap-2">
                <x-visibility public :hidden="$book->is_hidden" />

                <flux:switch wire:model="isRead" label="Leído" data-test="read-switch" />
            </div>

            <div class="flex flex-col gap-2 border-l border-dashed border-zinc-300 pl-4 dark:border-zinc-600">
                <x-visibility />

                <flux:input
                    wire:model="readOn"
                    type="date"
                    label="Cuándo lo leí"
                    description="Se puede dejar en blanco: el Libro queda leído, sin fecha."
                    class="max-w-xs"
                    data-test="read-on-field"
                />
            </div>

            <flux:button type="submit" variant="primary" class="self-start">Guardar la lectura</flux:button>
        </form>
    </section>

    <flux:separator />

    {{-- The Note: mine, one per Book, and it stays here. --}}
    <section class="flex flex-col gap-4 border-l border-dashed border-zinc-300 pl-4 dark:border-zinc-600">
        <div class="flex flex-col gap-1">
            <x-visibility />
            <flux:heading size="lg">Apunte</flux:heading>
            <flux:text>Qué te dejó el Libro. Es uno solo y se edita aquí mismo.</flux:text>
        </div>

        <form wire:submit="saveNote" class="flex flex-col gap-3">
            <flux:textarea
                wire:model="note"
                label="Mi apunte"
                rows="8"
                data-test="note-field"
            />

            <flux:button type="submit" variant="primary" class="self-start">Guardar el apunte</flux:button>
        </form>
    </section>

    <flux:separator />

    {{-- The lending: a field, not an entity. When it comes back, it gets erased. --}}
    <section class="flex flex-col gap-4 border-l border-dashed border-zinc-300 pl-4 dark:border-zinc-600">
        <div class="flex flex-col gap-1">
            <x-visibility />
            <flux:heading size="lg">Prestado a</flux:heading>
        </div>

        <form wire:submit="saveLending" class="flex flex-col gap-4">
            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input
                    wire:model="lentTo"
                    label="A quién"
                    placeholder="Nombre, como lo escribas"
                    autocomplete="off"
                    data-test="lent-to-field"
                />

                <flux:input
                    wire:model="lentOn"
                    type="date"
                    label="Desde cuándo"
                    data-test="lent-on-field"
                />
            </div>

            <div class="flex gap-3">
                <flux:button type="submit" variant="primary">Guardar el préstamo</flux:button>

                @if ($book->lent_to !== null)
                    <flux:button type="button" variant="subtle" wire:click="returned" data-test="returned">
                        Ya me lo devolvieron
                    </flux:button>
                @endif
            </div>
        </form>
    </section>

    <flux:separator />

    {{-- Where the Book lives, and whether it gets shown. --}}
    <section class="flex flex-col gap-4">
        <flux:heading size="lg">Situación y Vitrina</flux:heading>

        <form wire:submit="saveShelving" class="flex flex-col gap-6">
            <flux:radio.group wire:model="status" label="Situación" variant="segmented">
                <flux:radio value="{{ \App\Enums\Status::Owned->value }}" label="Acervo" />
                <flux:radio value="{{ \App\Enums\Status::Wanted->value }}" label="Por comprar" />
            </flux:radio.group>

            <flux:switch
                wire:model="isHidden"
                label="Oculto"
                description="Oculto saca el Libro de la Vitrina, y sus Citas con él. Aquí se sigue viendo igual."
                data-test="hidden-switch"
            />

            <flux:button type="submit" variant="primary" class="self-start">Guardar la Situación</flux:button>
        </form>
    </section>
</div>
