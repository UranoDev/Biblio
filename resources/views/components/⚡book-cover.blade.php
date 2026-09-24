<?php

use App\Covers\CoverNotStored;
use App\Covers\CoverStore;
use App\Covers\InvalidImage;
use App\Enums\CoverOrigin;
use App\Jobs\DownloadCover;
use App\Models\Book;
use Livewire\Attributes\On;
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

    /**
     * The stamp of the Reading is drawn over the Cover (BIB-38), and the
     * Reading is saved in the sheet around this block. A nested component does
     * not re-render because its parent did, so the sheet says when it saved one
     * and the Cover reads itself again.
     */
    #[On('reading-saved')]
    public function readingSaved(): void
    {
        $this->book->refresh();
    }

    public function retry(): void
    {
        $this->notice = DownloadCover::retry($this->book)
            ? 'La descarga de la Portada quedó en cola.'
            : 'Este Libro no tiene de dónde bajar la Portada.';
    }

    /**
     * Takes away the stored image. What a catalog answered is not always the
     * Cover of the book —a marker, the inner title page— and the checks of
     * BIB-28 do not catch the ones that are real images of the wrong page
     * (BIB-34). Replacing assumes a photograph that during the intake does not
     * exist yet, so taking it away has to be its own action.
     *
     * The Book is not left with a hole: it goes back to the generated piece
     * (ADR-008), keeping `cover_source_url` so the download can be retried.
     */
    public function remove(CoverStore $store): void
    {
        if (! $this->book->hasCover()) {
            return;
        }

        $store->delete($this->book);

        $this->reset('file');
        $this->book->refresh();

        $this->notice = 'Se quitó la Portada.';
    }
}; ?>

<div>
    <div class="flex gap-6">
        {{-- The stamp of the Reading sits over the Cover, and the line above
             the title says the state in words (BIB-38). --}}
        <div class="relative w-40 shrink-0">
            <x-cover :book="$book" size="full" />

            <x-reading-stamp :book="$book" decorative />
        </div>

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

            {{-- What a catalog answers is not always the Cover of the book, and
                 replacing it assumes a photograph that during the intake does
                 not exist yet (BIB-34). --}}
            @if ($book->hasCover())
                <flux:modal.trigger name="quitar-portada">
                    <flux:button variant="subtle" class="self-start" data-test="remove-cover">
                        Quitar la Portada
                    </flux:button>
                </flux:modal.trigger>
            @endif

            @if ($notice)
                <flux:text>{{ $notice }}</flux:text>
            @endif
        </div>
    </div>

    {{-- The file is deleted from the disk, and it may have cost a photograph,
         so the action is confirmed before it runs. --}}
    @if ($book->hasCover())
        <flux:modal name="quitar-portada" class="max-w-md">
            <div class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg">¿Quitar la Portada?</flux:heading>

                    <flux:subheading>
                        Se borra la imagen guardada y el Libro pasa a mostrar la Portada generada con
                        su título y sus autores.
                        @if ($book->cover_source_url)
                            Queda de dónde bajarla otra vez.
                        @endif
                    </flux:subheading>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">Cancelar</flux:button>
                    </flux:modal.close>

                    <flux:button
                        variant="danger"
                        wire:click="remove"
                        x-on:click="$flux.modal('quitar-portada').close()"
                        data-test="confirm-remove-cover"
                    >
                        Quitar la Portada
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
