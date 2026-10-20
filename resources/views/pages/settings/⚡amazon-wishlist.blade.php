<?php

use App\Jobs\ImportAmazonWishlist;
use App\Models\WishlistSetting;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Where the Amazon wishlist URL is configured and the sync is run by hand.
 *
 * The URL must be a public Amazon wishlist link — the one Amazon provides
 * under «Compartir lista». Private lists cannot be fetched without credentials.
 *
 * The automatic sync runs once a week from the scheduler. The last-synced
 * timestamp confirms it is working; if it does not update, the scheduler is
 * not running on the server.
 */
new #[Title('Lista de Amazon')] class extends Component
{
    #[Validate('nullable|url|max:500')]
    public string $url = '';

    public ?string $notice = null;

    public function mount(): void
    {
        $this->url = WishlistSetting::configured()?->url ?? '';
    }

    public function save(): void
    {
        $this->validate(
            ['url' => ['nullable', 'url', 'max:500']],
            ['url.url' => 'La URL no se entiende. Cópiala de Amazon tal como viene.'],
        );

        $clean = trim($this->url);

        if ($clean === '') {
            return;
        }

        WishlistSetting::configure($clean);

        $this->notice = 'La URL quedó guardada.';
    }

    /**
     * Dispatches the import job immediately, the same one the scheduler runs
     * each week.
     */
    public function importNow(): void
    {
        $setting = WishlistSetting::configured();

        if (! $setting instanceof WishlistSetting) {
            $this->notice = 'Guarda la URL antes de importar.';

            return;
        }

        ImportAmazonWishlist::dispatch();

        $this->notice = 'La importación quedó en la cola. Los libros aparecen en unos minutos.';
    }

    #[Computed]
    public function importedBooks(): Collection
    {
        return \App\Models\Book::query()
            ->where('source', \App\Enums\Source::Amazon->value)
            ->whereNotNull('isbn')
            ->orderBy('title')
            ->get(['id', 'title', 'isbn', 'authors']);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout
        heading="Lista de Amazon"
        subheading="Los libros de tu lista de deseos de Amazon se agregan con el estado «Por comprar» y se sincronizan una vez a la semana."
    >
        @php $setting = \App\Models\WishlistSetting::configured(); @endphp

        <div class="flex flex-col gap-8">

            @if ($setting)
                <div class="flex flex-col gap-1" data-test="sync-state">
                    @if ($setting->last_synced_at)
                        <flux:text>
                            Última sincronización:
                            <span class="font-medium">{{ $setting->last_synced_at->diffForHumans() }}</span>
                        </flux:text>
                    @else
                        <flux:text>Todavía no se ha sincronizado.</flux:text>
                    @endif

                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        La sincronización automática corre una vez a la semana.
                    </flux:text>
                </div>
            @endif

            @if ($notice)
                <flux:text class="text-base" data-test="notice">{{ $notice }}</flux:text>
            @endif

            <form wire:submit="save" class="flex flex-col gap-4">
                <flux:input
                    wire:model="url"
                    label="URL de la lista"
                    description="La URL pública que Amazon genera en «Compartir lista». Debe ser accesible sin iniciar sesión."
                    placeholder="https://www.amazon.com.mx/hz/wishlist/ls/..."
                    autocomplete="off"
                    data-test="url-field"
                />

                <flux:button type="submit" variant="primary" class="self-start">
                    Guardar la URL
                </flux:button>
            </form>

            @if ($setting)
                <div class="flex flex-col gap-2 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                    <flux:heading>Importar ahora</flux:heading>

                    <flux:text>
                        Corre la misma importación que el programador ejecuta cada semana.
                        Los libros que ya están en la biblioteca no se duplican.
                    </flux:text>

                    <flux:button
                        type="button"
                        variant="outline"
                        class="self-start"
                        wire:click="importNow"
                        data-test="import-now"
                    >
                        Importar ahora
                    </flux:button>
                </div>
            @endif

            @if ($this->importedBooks->isNotEmpty())
                <div class="flex flex-col gap-2 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                    <flux:heading>Libros importados</flux:heading>

                    <div class="flex flex-col gap-1">
                        @foreach ($this->importedBooks as $book)
                            @php
                                try {
                                    $asin = \App\Domain\Isbn::from($book->isbn)->asTen();
                                } catch (\Throwable) {
                                    $asin = null;
                                }
                            @endphp
                            @if ($asin !== null)
                                <div class="flex items-center justify-between gap-4">
                                    <flux:text>{{ $book->title }}</flux:text>
                                    <a
                                        href="https://www.amazon.com.mx/dp/{{ $asin }}/"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="shrink-0 text-sm text-zinc-500 underline hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
                                    >Ver en Amazon</a>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </x-pages::settings.layout>
</section>
