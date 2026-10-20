<?php

use App\Covers\CoverNotStored;
use App\Covers\CoverStore;
use App\Covers\InvalidImage;
use App\Domain\Isbn;
use App\Enums\Status;
use App\Intake\AddBook;
use App\Intake\CoverUnreadable;
use App\Intake\NewBook;
use App\Intake\ReadCover;
use App\Models\Book;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * ManualEntry: registering a Book by typing out its data, because no Source
 * knows it (ADR-002).
 *
 * It is reached from the Scan with the ISBN already filled in and from the
 * TitleLookup with the title and the Author already filled in, so that what was
 * already typed does not have to be typed again.
 *
 * It can also start from a photograph of the Cover (BIB-73): an AI reads what
 * is printed on it and fills in the form, which is reviewed before registering,
 * and the photograph stays as the Cover of the Book.
 */
new #[Title('Captura manual')] class extends Component
{
    use WithFileUploads;

    /** The photograph of the Cover: it fills in the form and becomes the Cover. */
    public $coverPhoto = null;

    /** What reading the photograph did, said in one line. */
    public ?string $readNotice = null;
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

    /**
     * The photograph arrived: it gets read and what was legible fills in the
     * form. A field the photograph had nothing for keeps what was typed.
     */
    public function updatedCoverPhoto(ReadCover $read): void
    {
        $this->readNotice = null;

        $this->validate(
            ['coverPhoto' => ['required', 'image', 'max:5120']],
            [
                'coverPhoto.image' => 'El archivo tiene que ser una imagen.',
                'coverPhoto.max' => 'La imagen no puede pesar más de 5 MB.',
            ],
        );

        try {
            $reading = $read->read($this->coverPhoto);
        } catch (CoverUnreadable $failure) {
            Log::warning('The photograph of a Cover could not be read.', ['reason' => $failure->getMessage()]);

            $this->readNotice = 'No se pudieron leer los datos de la foto. Escríbelos a mano; la foto se queda como Portada.';

            return;
        }

        $this->title = $reading->title ?? $this->title;
        $this->publisher = $reading->publisher ?? $this->publisher;
        $this->year = $reading->year === null ? $this->year : (string) $reading->year;
        $this->isbn = $reading->isbn?->value() ?? $this->isbn;

        if ($reading->authors !== []) {
            $this->authors = $reading->authors;
        }

        $this->resetValidation(['title', 'authors', 'publisher', 'year', 'isbn']);

        $this->readNotice = $reading->fromSource
            ? 'La foto traía el ISBN y la Fuente tenía la ficha: revisa los datos y registra el Libro.'
            : 'Estos datos se leyeron de la foto: revísalos antes de registrar el Libro.';
    }

    /** The photograph as the browser can show it before anything is saved. */
    public function photoPreview(): ?string
    {
        if (! $this->coverPhoto || ! method_exists($this->coverPhoto, 'isPreviewable') || ! $this->coverPhoto->isPreviewable()) {
            return null;
        }

        try {
            return $this->coverPhoto->temporaryUrl();
        } catch (\Throwable) {
            return null;
        }
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

    public function register(AddBook $add, CoverStore $covers): void
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

        $this->keepThePhotoAsTheCover($book, $covers);

        $this->registered = $book->getKey();

        unset($this->book);
    }

    /**
     * The photograph the form was read from is the Cover of the Book. If it is
     * refused —too small, a single flat colour— the Book is registered all the
     * same with its generated Cover, and the screen says why.
     */
    private function keepThePhotoAsTheCover(Book $book, CoverStore $covers): void
    {
        if (! $this->coverPhoto) {
            return;
        }

        try {
            $covers->storeUpload($book, $this->coverPhoto);
        } catch (InvalidImage $failure) {
            $this->readNotice = 'La foto no quedó como Portada: '.$failure->getMessage();
        } catch (CoverNotStored) {
            $this->readNotice = 'La foto no quedó como Portada: no se pudo guardar en el disco.';
        }
    }

    /**
     * Registers the Book and goes back to the Scan, for the book that came out
     * of a burst: the Scan says which one was registered and the burst goes on.
     */
    public function registerAndScan(AddBook $add, CoverStore $covers): void
    {
        $this->register($add, $covers);

        session()->flash('scan-notice', '«'.$this->book->title.'» quedó registrado a mano.');

        $this->redirectRoute('scan', navigate: true);
    }

    /** Leaves the screen ready for the next Book. */
    public function another(): void
    {
        $this->reset('title', 'authors', 'publisher', 'year', 'edition', 'isbn', 'registered', 'coverPhoto', 'readNotice');
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
        <flux:text>Escribe los datos del Libro que ninguna Fuente conoce, o sube una foto de su portada para llenarlos. Sólo el título hace falta.</flux:text>
    </div>

    @if ($this->book)
        <div class="flex flex-col gap-6" data-test="registered">
            <flux:text class="text-base">«{{ $this->book->title }}» quedó registrado. Situación: {{ $this->book->status->label() }}.</flux:text>

            <livewire:book-cover :book="$this->book" :key="'cover-'.$this->book->getKey()" />

            <div class="flex gap-3">
                <flux:button wire:click="another" variant="primary">Capturar otro Libro</flux:button>

                <flux:button :href="route('book', $this->book)" variant="outline" wire:navigate>
                    Ver la ficha
                </flux:button>

                <flux:button :href="route('scan')" variant="outline" wire:navigate>
                    Ir al Escaneo
                </flux:button>
            </div>
        </div>
    @else
        <form wire:submit="register" class="flex flex-col gap-6">
            <div class="flex gap-6" data-test="cover-photo">
                <div class="w-28 shrink-0">
                    @if ($preview = $this->photoPreview())
                        <img
                            src="{{ $preview }}"
                            alt="Foto de la portada"
                            class="cover-edge aspect-2/3 w-full rounded-lg object-cover"
                        >
                    @else
                        <div class="flex aspect-2/3 w-full items-center justify-center rounded-lg border border-dashed border-rule">
                            <flux:icon.camera class="size-8 text-ink-muted" />
                        </div>
                    @endif
                </div>

                <div class="flex min-w-0 flex-1 flex-col gap-3">
                    <flux:input
                        type="file"
                        wire:model="coverPhoto"
                        accept="image/*"
                        label="Foto de la portada"
                        description="Opcional. Se leen los datos que trae y llenan el formulario; la foto queda como Portada."
                        data-test="cover-photo-field"
                    />

                    <div wire:loading.flex wire:target="coverPhoto" class="items-center gap-2" data-test="reading-cover">
                        <flux:icon.loading class="size-4" />
                        <flux:text>Leyendo la portada…</flux:text>
                    </div>

                    @if ($readNotice)
                        <flux:text wire:loading.remove wire:target="coverPhoto" data-test="read-notice">{{ $readNotice }}</flux:text>
                    @endif
                </div>
            </div>

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
                                        variant="outline"
                                        wire:click="removeAuthor({{ $index }})"
                                        aria-label="Quitar este Autor"
                                    />
                                @endif
                            </div>

                            <flux:error name="authors.{{ $index }}" />
                        </div>
                    @endforeach

                    <flux:button type="button" variant="outline" class="self-start" wire:click="addAuthor">
                        Agregar otro Autor
                    </flux:button>
                </div>
            </flux:field>

            <div class="grid items-start gap-6 sm:grid-cols-2">
                <flux:input wire:model="publisher" label="Editorial" autocomplete="off" />
                <flux:input wire:model="year" label="Año" inputmode="numeric" autocomplete="off" />
                <flux:input wire:model="edition" label="Edición" autocomplete="off" />
                <flux:input
                    wire:model="isbn"
                    label="ISBN"
                    description:trailing="Si no trae código de barras, déjalo en blanco."
                    inputmode="numeric"
                    autocomplete="off"
                    data-test="isbn-field"
                />
            </div>

            <flux:radio.group wire:model="status" label="Situación">
                <flux:radio value="{{ \App\Enums\Status::Read->value }}" label="Acervo, leído" />
                <flux:radio value="{{ \App\Enums\Status::Owned->value }}" label="Acervo, por leer" />
                <flux:radio value="{{ \App\Enums\Status::Wanted->value }}" label="Por comprar" />
            </flux:radio.group>

            <flux:text>La Portada se sube en cuanto el Libro quede registrado, y se puede dejar sin ella.</flux:text>

            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="primary">Registrar el Libro</flux:button>

                <flux:button
                    type="button"
                    variant="outline"
                    icon="plus"
                    wire:click="registerAndScan"
                    data-test="register-and-scan"
                >
                    Registrar y volver al Escaneo
                </flux:button>
            </div>
        </form>
    @endif
</div>
