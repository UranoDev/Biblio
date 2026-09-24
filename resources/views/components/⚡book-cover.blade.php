<?php

use App\Covers\CoverNotStored;
use App\Covers\CoverStore;
use App\Covers\InvalidImage;
use App\Enums\CoverOrigin;
use App\Jobs\DownloadCover;
use App\Models\Book;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The Cover of a Book, with the two paths that do not depend on the Source:
 * uploading one by hand and retrying the download (ADR-004).
 *
 * Uploading is no longer the emergency exit it was when this screen was
 * written. Of four real ISBN, none gave back a usable image (ADR-008), so this
 * is the way most of the good Covers are going to come in, one book at a time
 * and over months. That is why it is the part of the block that reads first.
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
        } catch (InvalidImage $failure) {
            // The reason is worth showing: «no se pudo leer» is false when what
            // was refused is a thumbnail or the marker of a catalog.
            $this->addError('file', $failure->getMessage());

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

        {{-- Which of the three Portadas this is. The Origin is not named on
             screen; what it says is shown. --}}
        <flux:text data-test="cover-origin">
            @switch ($book->coverOrigin())
                @case (CoverOrigin::Owner)
                    Es tu foto. Los reintentos contra la Fuente no la reemplazan.
                    @break
                @case (CoverOrigin::Source)
                    Vino de la Fuente.
                    @break
                @default
                    Está generada con el título y los autores.
            @endswitch
        </flux:text>

        <form wire:submit="save" class="flex flex-col gap-3">
            <flux:input
                type="file"
                wire:model="file"
                accept="image/*"
                :label="$book->hasGeneratedCover() ? 'Subir tu foto de la Portada' : 'Reemplazar la Portada'"
            />

            <flux:button type="submit" variant="primary" class="self-start">Guardar</flux:button>
        </form>

        @if ($book->canRetryCover())
            <flux:button wire:click="retry" variant="subtle" class="self-start">
                Reintentar la descarga
            </flux:button>
        @endif

        @if ($notice)
            <flux:text>{{ $notice }}</flux:text>
        @endif
    </div>
</div>
