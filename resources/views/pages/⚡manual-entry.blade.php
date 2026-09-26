<?php

use App\Domain\Isbn;
use App\Enums\Status;
use App\Intake\AddBook;
use App\Intake\NewBook;
use App\Models\Book;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * ManualEntry: registering a Book by typing out its data, because no Source
 * knows it (ADR-002).
 *
 * It is reached from the Scan with the ISBN already filled in and from the
 * TitleLookup with the title and the Author already filled in, so that what was
 * already typed does not have to be typed again.
 */
new #[Title('Captura manual')] class extends Component
{
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

    public string $status = '';

    /** The Book just registered, which can still have its Cover uploaded. */
    public ?int $registered = null;

    public function mount(): void
    {
        // What was already typed on the previous path: the ISBN that was
        // scanned, or the title and the Author the Source was queried with.
        $this->isbn = trim((string) request()->query('isbn', ''));
        $this->title = trim((string) request()->query('title', ''));

        $author = trim((string) request()->query('author', ''));

        if ($author !== '') {
            $this->authors = [$author];
        }

        $this->status = Status::Read->value;
    }

    #[Computed]
    public function book(): ?Book
    {
        return $this->registered === null ? null : Book::find($this->registered);
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

    public function register(AddBook $add): void
    {
        $this->validate($this->rules(), $this->messages());

        $book = $add->byHand(new NewBook(
            title: trim($this->title),
            authors: $this->writtenAuthors(),
            publisher: $this->blankIsNull($this->publisher),
            year: trim($this->year) === '' ? null : (int) trim($this->year),
            edition: $this->blankIsNull($this->edition),
            isbn: trim($this->isbn) === '' ? null : Isbn::from($this->isbn),
            status: Status::from($this->status),
        ));

        $this->registered = $book->getKey();

        unset($this->book);
    }

    /** Leaves the screen ready for the next Book. */
    public function another(): void
    {
        $this->reset('title', 'authors', 'publisher', 'year', 'edition', 'isbn', 'registered');
        $this->resetValidation();

        $this->status = Status::Read->value;

        unset($this->book);
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'authors' => ['array'],
            'authors.*' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1450', 'max:'.((int) date('Y') + 1)],
            'edition' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', $this->isbnNotAlreadyRegistered()],
            'status' => ['required', Rule::enum(Status::class)],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'title.required' => 'Falta el título.',
            'title.max' => 'El título no puede pasar de 255 caracteres.',
            'authors.*.max' => 'El nombre del Autor no puede pasar de 255 caracteres.',
            'publisher.max' => 'La editorial no puede pasar de 255 caracteres.',
            'year.integer' => 'El año se escribe con dígitos.',
            'year.min' => 'El año tiene que ser de 1450 en adelante.',
            'year.max' => 'El año no puede ser posterior al que viene.',
            'edition.max' => 'La edición no puede pasar de 255 caracteres.',
            'status.required' => 'Falta la Situación.',
            'status.Illuminate\Validation\Rules\Enum' => 'La Situación es Acervo o Por comprar.',
        ];
    }

    /**
     * An already registered ISBN reports which Book has it, instead of
     * duplicating it: the ISBN is unique on the Shelf (ADR-005).
     */
    private function isbnNotAlreadyRegistered(): \Closure
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

            $registered = Book::query()
                ->where('isbn', Isbn::from($typed)->value())
                ->first();

            if ($registered instanceof Book) {
                $fail('«'.$registered->title.'» ya está registrado con ese ISBN.');
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

<div class="flex w-full max-w-2xl flex-col gap-8">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl">Captura manual</flux:heading>
        <flux:text>Escribe los datos del Libro que ninguna Fuente conoce. Sólo el título hace falta.</flux:text>
    </div>

    @if ($this->book)
        <div class="flex flex-col gap-6" data-test="registered">
            <flux:text class="text-base">«{{ $this->book->title }}» quedó registrado. Situación: {{ $this->book->status->label() }}.</flux:text>

            <livewire:book-cover :book="$this->book" :key="'cover-'.$this->book->getKey()" />

            <div class="flex gap-3">
                <flux:button wire:click="another" variant="primary">Capturar otro Libro</flux:button>

                <flux:button :href="route('book', $this->book)" variant="subtle" wire:navigate>
                    Ver la ficha
                </flux:button>

                <flux:button :href="route('scan')" variant="subtle" wire:navigate>
                    Ir al Escaneo
                </flux:button>
            </div>
        </div>
    @else
        <form wire:submit="register" class="flex flex-col gap-6">
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
                    description="Si no trae código de barras, déjalo en blanco."
                    inputmode="numeric"
                    autocomplete="off"
                    data-test="isbn-field"
                />
            </div>

            <flux:radio.group wire:model="status" label="Situación" variant="segmented">
                <flux:radio value="{{ \App\Enums\Status::Owned->value }}" label="Acervo" />
                <flux:radio value="{{ \App\Enums\Status::Wanted->value }}" label="Por comprar" />
            </flux:radio.group>

            <flux:text>La Portada se sube en cuanto el Libro quede registrado, y se puede dejar sin ella.</flux:text>

            <flux:button type="submit" variant="primary" class="self-start">Registrar el Libro</flux:button>
        </form>
    @endif
</div>
