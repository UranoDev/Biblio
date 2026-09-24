<?php

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The screen where the vocabulary of the Categories gets kept healthy.
 *
 * The AI proposes and it is handed the existing list, but over two hundred
 * Books «Ciencia ficción» and «Ciencia-ficción» still turn up, and so do
 * Categories with a single Book that nobody navigates by (ADR-006). Renaming
 * and merging are what that gets corrected with, and the count beside each name
 * is what makes it visible in the first place.
 *
 * Renaming and merging both move the address of a Category, so both leave the
 * old slug behind pointing at the Category that holds the Books now: what was
 * shared before the change keeps arriving.
 */
new #[Title('Categorías')] class extends Component
{
    /** Part of the name being looked for, to find one in a long list. */
    public string $search = '';

    /** Whether the list is narrowed to the ones with one Book or none. */
    public bool $onlySmall = false;

    /** The Category being renamed, or null while none is. */
    public ?int $renaming = null;

    /** The new name, while it is being typed. */
    public string $name = '';

    /** The Category about to be merged into another one, or null. */
    public ?int $merging = null;

    /** The Category that stays after the merge, by id. */
    public string $into = '';

    /** What the last change did, read at a glance and never in a modal. */
    public ?string $notice = null;

    /**
     * The Categories as the screen lists them: by name, each one with how many
     * Books it has, narrowed by what is being looked for.
     *
     * The empty ones are here on purpose. They are out of the Showcase —a
     * Category with no Books is not a subject anybody can navigate to— but this
     * is the screen where they get noticed.
     *
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        $typed = trim($this->search);

        return Category::query()
            ->withBookCount()
            ->when($typed !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$typed.'%'))
            ->when($this->onlySmall, fn (Builder $query) => $query->has('books', '<=', 1))
            ->get();
    }

    /** How many Categories there are, whatever the list is showing. */
    #[Computed]
    public function total(): int
    {
        return Category::query()->count();
    }

    /**
     * The other Categories, the ones a merge can land on.
     *
     * @return Collection<int, Category>
     */
    #[Computed]
    public function others(): Collection
    {
        return Category::query()
            ->whereKeyNot($this->merging)
            ->orderBy('name')
            ->get();
    }

    public function updatedSearch(): void
    {
        $this->stopEditing();
    }

    public function updatedOnlySmall(): void
    {
        $this->stopEditing();
    }

    /** Opens the name of one Category for correction. */
    public function startRename(int $id): void
    {
        $category = $this->category($id);

        $this->stopEditing();

        $this->renaming = $id;
        $this->name = $category->name;
    }

    /**
     * Writes the new name, and with it the new slug.
     *
     * Two Categories cannot share a name, and when the one that was typed
     * already belongs to another the answer is not an error about a unique
     * column: it is the operation that was actually meant, which is merging
     * them.
     */
    public function rename(): void
    {
        if ($this->renaming === null) {
            return;
        }

        $category = $this->category($this->renaming);
        $written = trim($this->name);
        $slug = Str::slug($written);

        if ($written === '' || $slug === '') {
            $this->addError('name', 'Falta el nombre de la Categoría.');

            return;
        }

        if (Str::length($written) > 255) {
            $this->addError('name', 'El nombre no puede pasar de 255 caracteres.');

            return;
        }

        $taken = Category::query()
            ->whereKeyNot($category->getKey())
            ->where(fn (Builder $query) => $query->where('name', $written)->orWhere('slug', $slug))
            ->first();

        if ($taken instanceof Category) {
            $this->addError('name', '«'.$taken->name.'» ya existe. Para juntarlas, fusiónalas.');

            return;
        }

        $before = $category->name;

        $category->rename($written);

        $this->stopEditing();

        $this->notice = '«'.$before.'» ahora se llama «'.$category->name
            .'». Su dirección anterior sigue llevando aquí.';
    }

    /** Opens the merge of one Category into another. */
    public function startMerge(int $id): void
    {
        $this->category($id);

        $this->stopEditing();

        $this->merging = $id;
    }

    /**
     * Merges the Category into the one that was chosen: its Books pass over
     * without repeating, and it stops existing.
     */
    public function merge(): void
    {
        if ($this->merging === null) {
            return;
        }

        $absorbed = $this->category($this->merging);

        $into = Category::query()
            ->whereKeyNot($absorbed->getKey())
            ->find($this->into);

        if (! $into instanceof Category) {
            $this->addError('into', 'Elige la Categoría que se queda.');

            return;
        }

        $into->absorb($absorbed);

        $left = $into->books()->count();

        $this->stopEditing();

        $this->notice = '«'.$absorbed->name.'» quedó dentro de «'.$into->name.'», con '
            .$this->books($left).'.';
    }

    /** Closes whatever was open, leaving the list as it was. */
    public function stopEditing(): void
    {
        $this->reset('renaming', 'name', 'merging', 'into');

        $this->resetValidation();

        unset($this->categories, $this->total, $this->others);
    }

    /** «un Libro» or «N Libros», which gets written in several places. */
    public function books(int $count): string
    {
        return $count === 1 ? 'un Libro' : $count.' Libros';
    }

    private function category(int $id): Category
    {
        return Category::query()->findOrFail($id);
    }
}; ?>

<div class="flex w-full max-w-3xl flex-col gap-8">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">Categorías</flux:heading>

        <flux:text>
            Los temas de los Libros. Aquí se renombra una Categoría, se fusionan dos que resultaron
            ser la misma, y se ve cuántos Libros tiene cada una.
        </flux:text>
    </div>

    @if ($notice)
        <flux:text class="text-base" data-test="notice">{{ $notice }}</flux:text>
    @endif

    <div class="flex flex-wrap items-end gap-6">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="Parte del nombre"
            autocomplete="off"
            label="Buscar una Categoría"
            class="max-w-xs"
            data-test="search-field"
        />

        <flux:checkbox
            wire:model.live="onlySmall"
            label="Sólo las de un Libro o ninguno"
            data-test="only-small"
        />
    </div>

    <flux:separator />

    @if ($this->total === 0)
        <flux:text data-test="empty">
            Todavía no hay Categorías. Se agregan a mano en la ficha de un Libro, y la IA propone
            las suyas cuando hay un modelo elegido.
        </flux:text>
    @elseif ($this->categories->isEmpty())
        <flux:text data-test="no-matches">Ninguna Categoría se llama así.</flux:text>
    @else
        <flux:text data-test="count">
            {{ $this->categories->count() }}
            {{ $this->categories->count() === 1 ? 'Categoría' : 'Categorías' }}
            de {{ $this->total }}.
        </flux:text>

        <ul class="flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700" data-test="categories">
            @foreach ($this->categories as $category)
                <li class="flex flex-col gap-3 py-4" wire:key="category-{{ $category->getKey() }}">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-col gap-1">
                            <flux:heading data-test="name-{{ $category->getKey() }}">
                                {{ $category->name }}
                            </flux:heading>

                            <flux:text class="text-sm" data-test="count-{{ $category->getKey() }}">
                                {{ (int) $category->books_count === 0
                                    ? 'Ningún Libro'
                                    : $this->books((int) $category->books_count) }}
                            </flux:text>
                        </div>

                        <div class="flex gap-2">
                            <flux:button
                                size="sm"
                                variant="subtle"
                                wire:click="startRename({{ $category->getKey() }})"
                                data-test="rename-{{ $category->getKey() }}"
                            >
                                Renombrar
                            </flux:button>

                            @if ($this->total > 1)
                                <flux:button
                                    size="sm"
                                    variant="subtle"
                                    wire:click="startMerge({{ $category->getKey() }})"
                                    data-test="merge-{{ $category->getKey() }}"
                                >
                                    Fusionar
                                </flux:button>
                            @endif
                        </div>
                    </div>

                    @if ($renaming === $category->getKey())
                        <form wire:submit="rename" class="flex flex-wrap items-end gap-3" data-test="rename-form">
                            <flux:input
                                wire:model="name"
                                label="Nuevo nombre"
                                autocomplete="off"
                                class="max-w-xs"
                                data-test="name-field"
                            />

                            <flux:button type="submit" variant="primary" data-test="save-name">
                                Guardar
                            </flux:button>

                            <flux:button variant="subtle" wire:click="stopEditing">Cancelar</flux:button>
                        </form>
                    @endif

                    @if ($merging === $category->getKey())
                        <form wire:submit="merge" class="flex flex-col gap-3" data-test="merge-form">
                            <flux:select
                                wire:model="into"
                                label="Fusionar «{{ $category->name }}» dentro de"
                                placeholder="La Categoría que se queda"
                                class="max-w-xs"
                                data-test="into-field"
                            >
                                @foreach ($this->others as $other)
                                    <flux:select.option
                                        wire:key="into-{{ $other->getKey() }}"
                                        value="{{ $other->getKey() }}"
                                    >
                                        {{ $other->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:text class="text-sm">
                                Los Libros de «{{ $category->name }}» pasan a la que se queda, y
                                «{{ $category->name }}» desaparece. Su dirección anterior lleva a la que
                                se queda.
                            </flux:text>

                            <div class="flex gap-3">
                                <flux:button type="submit" variant="primary" data-test="confirm-merge">
                                    Fusionar
                                </flux:button>

                                <flux:button variant="subtle" wire:click="stopEditing">Cancelar</flux:button>
                            </div>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
