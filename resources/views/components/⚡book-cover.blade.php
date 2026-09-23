<?php

use App\Covers\CoverNotStored;
use App\Covers\CoverStore;
use App\Covers\InvalidImage;
use App\Jobs\DownloadCover;
use App\Models\Book;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The Cover of a Book, with the two paths that do not depend on the Source:
 * uploading one by hand and retrying the download (ADR-004).
 */
new class extends Component
{
    use WithFileUploads;

    public Book $book;

    public $file = null;

    public ?string $notice = null;

    public function save(CoverStore $store): void
    {
        $this->validate(
            ['file' => ['required', 'image', 'max:5120']],
            [
                'file.required' => 'Falta elegir una imagen.',
                'file.image' => 'El archivo tiene que ser una imagen.',
                'file.max' => 'La imagen no puede pesar más de 5 MB.',
            ],
        );

        try {
            $store->storeUpload($this->book, $this->file);
        } catch (InvalidImage) {
            $this->addError('file', 'El archivo no se pudo leer como imagen.');

            return;
        } catch (CoverNotStored) {
            $this->addError('file', 'La Portada no se pudo guardar en el disco.');

            return;
        }

        $this->reset('file', 'notice');
        $this->book->refresh();
    }

    public function retry(): void
    {
        $this->notice = DownloadCover::retry($this->book)
            ? 'La descarga de la Portada quedó en cola.'
            : 'Este Libro no tiene de dónde bajar la Portada.';
    }
}; ?>

<div class="flex gap-6">
    <x-cover :book="$book" size="full" class="w-40 shrink-0" />

    <div class="flex flex-col gap-3">
        <flux:heading size="sm">Portada</flux:heading>

        @unless ($book->hasCover())
            <flux:text>
                @if ($book->canRetryCover())
                    La descarga de la Portada no se completó.
                @else
                    Este Libro no tiene Portada.
                @endif
            </flux:text>

            @if ($book->canRetryCover())
                <flux:button wire:click="retry" variant="subtle" class="self-start">
                    Reintentar la descarga
                </flux:button>
            @endif
        @endunless

        @if ($notice)
            <flux:text>{{ $notice }}</flux:text>
        @endif

        <form wire:submit="save" class="flex flex-col gap-3">
            <flux:input
                type="file"
                wire:model="file"
                accept="image/*"
                :label="$book->hasCover() ? 'Reemplazar la Portada' : 'Subir una Portada'"
            />

            <flux:button type="submit" variant="primary" class="self-start">Guardar</flux:button>
        </form>
    </div>
</div>
