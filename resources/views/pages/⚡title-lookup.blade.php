<?php

use App\Enums\Status;
use App\Intake\AddBook;
use App\Intake\NewBook;
use App\Intake\RegisterCandidate;
use App\Models\Book;
use App\Sources\Candidate;
use App\Sources\SourceResolver;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The TitleLookup: the path for what cannot be scanned —the Wanted Books, which
 * are not in hand, and the old ones, which carry no barcode.
 *
 * It is a single screen for both cases: the flow is the same and the only thing
 * that changes is the Status the Book is born with (decision 14).
 *
 * Since a Book is an edition (ADR-005), the Source returns several of the same
 * title and each Candidate is shown with Cover, authors, publisher and year:
 * without those one chooses blind.
 */
new #[Title('Alta por título')] class extends Component
{
    public string $title = '';

    public string $author = '';

    public string $status = '';

    /**
     * The editions the Source returned, as plain data.
     *
     * The screen remembers them between one request and the next because asking
     * the Source again at the moment of choosing would answer something else —or
     * would not answer—, and what would be registered would not be the edition
     * seen on the list.
     *
     * @var list<array<string, mixed>>
     */
    public array $candidates = [];

    /** The title that was looked up, the one the list belongs to. */
    public string $lookedUp = '';

    public ?string $notice = null;

    /** The Source answered and has no edition: ManualEntry is what is left. */
    public bool $withoutEditions = false;

    /** The Book that was just registered. */
    public ?int $registered = null;

    /** The Book that already had the ISBN of the chosen Candidate. */
    public ?int $duplicate = null;

    public function mount(): void
    {
        // Wanted by default: this is above all the path for the Books that are
        // not there yet.
        $this->status = Status::Wanted->value;
    }

    #[Computed]
    public function book(): ?Book
    {
        return $this->registered === null ? null : Book::find($this->registered);
    }

    #[Computed]
    public function theOneAlreadyThere(): ?Book
    {
        return $this->duplicate === null ? null : Book::find($this->duplicate);
    }

    /**
     * Whether there is somewhere to send the person to see the Book that was
     * already there. The Book sheet arrives with BIB-14; while it does not
     * exist, the notice just says which one it is.
     */
    #[Computed]
    public function hasBookSheet(): bool
    {
        return Route::has('book');
    }

    public function lookUp(SourceResolver $sources): void
    {
        $this->validate($this->rules(), $this->messages());

        $this->reset('candidates', 'notice', 'withoutEditions', 'registered', 'duplicate');
        $this->forgetTheBooks();

        $title = trim($this->title);
        $author = trim($this->author) === '' ? null : trim($this->author);

        $candidates = $sources->byTitle($title, $author);

        $this->lookedUp = $title;

        if ($candidates->hasCandidates()) {
            $this->candidates = array_map(
                fn (Candidate $candidate): array => $candidate->toArray() + ['key' => $candidate->key()],
                $candidates->candidates,
            );

            return;
        }

        if ($candidates->titleUnknown()) {
            $this->withoutEditions = true;
            $this->notice = 'Ninguna Fuente tiene ediciones de «'.$title.'».';

            return;
        }

        // The Source failing to answer does not lead to ManualEntry: it would
        // type out by hand a Book the catalog does have (ADR-002).
        $this->notice = 'Las Fuentes no pudieron contestar por «'.$title.'». Vuelve a intentarlo.';
    }

    public function choose(string $key, RegisterCandidate $register): void
    {
        $row = $this->rowFor($key);

        if ($row === null) {
            return;
        }

        $result = $register->register(
            Candidate::fromArray($row),
            Status::tryFrom($this->status) ?? Status::Wanted,
        );

        $this->forgetTheBooks();

        if ($result->alreadyRegistered) {
            $this->duplicate = $result->book->getKey();
            $this->notice = '«'.$result->book->title.'» ya está registrado con ese ISBN.';

            return;
        }

        $this->duplicate = null;
        $this->registered = $result->book->getKey();
        $this->notice = '«'.$result->book->title.'» quedó registrado. Situación: '
            .$result->book->status->label().'.';
    }

    /**
     * Registers the Book with the title and the Author as typed, without going
     * through a Source: for the books no catalog has, or that carry no ISBN.
     * It is a ManualEntry with two fields, so it is born marked as such.
     *
     * It is always born on the Shelf, whatever the Situación says: a book
     * without an ISBN is almost always an old one already in hand.
     */
    public function registerAsTyped(AddBook $add): void
    {
        $this->validate($this->rules(), $this->messages());

        $author = trim($this->author);

        $book = $add->byHand(new NewBook(
            title: trim($this->title),
            authors: $author === '' ? [] : [$author],
            status: Status::Owned,
        ));

        $this->forgetTheBooks();
        $this->duplicate = null;
        $this->registered = $book->getKey();
        $this->notice = '«'.$book->title.'» quedó registrado sin ISBN. Situación: '
            .$book->status->label().'.';
    }

    /** Leaves the screen ready for the next Book. */
    public function another(): void
    {
        $this->reset('title', 'author', 'candidates', 'lookedUp', 'notice', 'withoutEditions', 'registered', 'duplicate');
        $this->resetValidation();
        $this->forgetTheBooks();

        $this->status = Status::Wanted->value;
    }

    /**
     * The row that was chosen. The key is given by the catalog, so two rows only
     * share it when they are the same edition with the same data — and there it
     * makes no difference which of the two gets registered.
     *
     * @return array<string, mixed>|null
     */
    private function rowFor(string $key): ?array
    {
        foreach ($this->candidates as $row) {
            if (($row['key'] ?? null) === $key) {
                return $row;
            }
        }

        return null;
    }

    private function forgetTheBooks(): void
    {
        unset($this->book, $this->theOneAlreadyThere);
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(Status::class)],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'title.required' => 'Falta el título.',
            'title.max' => 'El título no puede pasar de 255 caracteres.',
            'author.max' => 'El nombre del Autor no puede pasar de 255 caracteres.',
            'status.required' => 'Falta la Situación.',
            'status.Illuminate\Validation\Rules\Enum' => 'La Situación es Acervo o Por comprar.',
        ];
    }
}; ?>

<div class="flex w-full max-w-3xl flex-col gap-8">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl">Alta por título</flux:heading>
        <flux:text>Escribe el título, elige entre las ediciones que trae la Fuente y el Libro queda registrado.</flux:text>
    </div>

    @if ($this->book)
        <div class="flex flex-col gap-6" data-test="registered">
            <flux:text class="text-base" data-test="notice">{{ $notice }}</flux:text>

            <div class="flex gap-3">
                <flux:button wire:click="another" variant="primary">Dar de alta otro Libro</flux:button>

                <flux:button :href="route('scan')" variant="subtle" wire:navigate>
                    Ir al Escaneo
                </flux:button>
            </div>
        </div>
    @else
        <form wire:submit="lookUp" class="flex flex-col gap-6">
            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input
                    wire:model="title"
                    label="Título"
                    autocomplete="off"
                    autofocus
                    data-test="title-field"
                />

                <flux:input
                    wire:model="author"
                    label="Autor"
                    description="Opcional. Acota las ediciones que devuelve la Fuente."
                    autocomplete="off"
                    data-test="author-field"
                />
            </div>

            <flux:radio.group wire:model="status" label="Situación" variant="segmented">
                <flux:radio value="{{ \App\Enums\Status::Wanted->value }}" label="Por comprar" />
                <flux:radio value="{{ \App\Enums\Status::Owned->value }}" label="Acervo" />
            </flux:radio.group>

            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="primary">Consultar la Fuente</flux:button>

                <flux:button
                    type="button"
                    variant="subtle"
                    wire:click="registerAsTyped"
                    data-test="register-as-typed"
                >
                    Registrar en el Acervo solo con título y autor
                </flux:button>
            </div>
        </form>

        @if ($notice)
            <flux:text class="text-base" data-test="notice">{{ $notice }}</flux:text>
        @endif

        @if ($this->theOneAlreadyThere && $this->hasBookSheet)
            <flux:button
                variant="subtle"
                class="self-start"
                :href="route('book', $this->theOneAlreadyThere)"
                wire:navigate
                data-test="go-to-book"
            >
                Ir a «{{ $this->theOneAlreadyThere->title }}»
            </flux:button>
        @endif

        @if ($withoutEditions)
            <flux:button
                variant="subtle"
                class="self-start"
                :href="route('manual-entry', array_filter(['title' => $lookedUp, 'author' => trim($author)]))"
                wire:navigate
                data-test="go-to-manual-entry"
            >
                Capturar a mano «{{ $lookedUp }}»
            </flux:button>
        @endif

        @if ($candidates)
            <div class="flex flex-col gap-3">
                <flux:heading size="lg">Ediciones de «{{ $lookedUp }}»</flux:heading>

                <flux:text>Cada renglón es una edición distinta del mismo título.</flux:text>

                @foreach ($candidates as $edition)
                    <div
                        wire:key="{{ $edition['key'] }}"
                        class="flex items-start gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700"
                        data-test="candidate"
                    >
                        <div class="w-16 shrink-0">
                            <x-candidate-cover
                                :url="$edition['coverUrl']"
                                :title="$edition['title']"
                                :authors="$edition['authors']"
                            />
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <flux:heading class="truncate">{{ $edition['title'] }}</flux:heading>

                            @if (filled($edition['authors']))
                                <flux:text class="truncate">{{ implode(', ', $edition['authors']) }}</flux:text>
                            @endif

                            <flux:text class="text-sm">
                                {{ collect([
                                    $edition['publisher'],
                                    $edition['year'],
                                    $edition['edition'],
                                ])->filter()->implode(' · ') ?: 'Sin editorial ni año' }}
                            </flux:text>

                            <flux:text class="text-sm">
                                {{ $edition['isbn'] ? 'ISBN '.$edition['isbn'] : 'Esta edición no trae ISBN' }}
                            </flux:text>
                        </div>

                        <flux:button
                            size="sm"
                            variant="primary"
                            class="shrink-0"
                            wire:click="choose(@js($edition['key']))"
                        >
                            Es ésta
                        </flux:button>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
