<?php

use App\Covers\CoverNotStored;
use App\Covers\CoverStore;
use App\Covers\InvalidImage;
use App\Enums\CoverOrigin;
use App\Jobs\DownloadCover;
use App\Models\Book;
use App\Sources\AmazonCovers;
use Illuminate\Support\Facades\Http;
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

    /** The link to the product on Amazon whose Cover is wanted. */
    public string $amazonLink = '';

    /**
     * The photograph just chosen, as the browser can show it before it is
     * saved: the Cover changes the moment the file is picked, and Guardar is
     * what keeps it. Null while there is none, or when what was picked cannot
     * be previewed.
     */
    public function preview(): ?string
    {
        if (! $this->file || ! method_exists($this->file, 'isPreviewable') || ! $this->file->isPreviewable()) {
            return null;
        }

        try {
            return $this->file->temporaryUrl();
        } catch (Throwable) {
            return null;
        }
    }

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

        $this->reset('file');
        $this->book->refresh();

        // A photograph from a phone takes a while to go up and to be stored,
        // and a Cover that changes without a word looks like one that did not.
        $this->notice = 'La Portada quedó guardada.';
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

    /**
     * Brings the Cover from the Amazon product the owner found in the browser.
     *
     * It is for the editions the cascade cannot reach on its own: an ISBN
     * starting with 979 has no ten-digit form, and searching Amazon from the
     * server gets a captcha. The link carries the code of the product, and the
     * image of that code is served without one.
     */
    public function fromAmazon(AmazonCovers $amazon, CoverStore $store): void
    {
        $link = trim($this->amazonLink);

        if ($link === '') {
            $this->addError('amazonLink', 'Pega el enlace del Libro en Amazon.');

            return;
        }

        if (AmazonCovers::asinOf($link) === null) {
            $this->addError('amazonLink', 'Ese enlace no es de un producto de Amazon. Cópialo de la página del Libro, la que muestra la portada.');

            return;
        }

        $url = $amazon->coverUrlForLink($link);

        if ($url === null) {
            $this->addError('amazonLink', 'Amazon no tiene imagen de portada para ese producto.');

            return;
        }

        try {
            $binary = Http::timeout((int) config('covers.timeout', 10))->get($url)->throw()->body();

            $this->book->forceFill(['cover_source_url' => $url])->save();

            // Chosen by the owner: it replaces whatever Cover the Book showed,
            // their own photograph included.
            $store->store(
                $this->book,
                $binary,
                $this->book->coverOrigin() === CoverOrigin::Owner ? CoverOrigin::Owner : CoverOrigin::Source,
            );
        } catch (InvalidImage $failure) {
            $this->addError('amazonLink', $failure->getMessage());

            return;
        } catch (CoverNotStored) {
            $this->addError('amazonLink', 'La Portada no se pudo guardar en el disco.');

            return;
        } catch (\Throwable) {
            $this->addError('amazonLink', 'Amazon no contestó. Vuelve a intentarlo en un momento.');

            return;
        }

        $this->reset('amazonLink', 'file');
        $this->book->refresh();

        $this->notice = 'La Portada quedó guardada, traída de Amazon.';
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
            @if ($preview = $this->preview())
                <img
                    src="{{ $preview }}"
                    alt="Vista previa de la Portada de {{ $book->title }}"
                    class="cover-edge aspect-2/3 w-full rounded-lg object-cover"
                    data-test="cover-preview"
                >
            @else
                <x-cover :book="$book" size="full" />
            @endif

            <x-reading-stamp :book="$book" decorative />
        </div>

        <div class="flex flex-col gap-3">
            <flux:heading size="sm">Portada</flux:heading>

            {{-- Which of the three Portadas this is. The Origin is not named on
                 screen; what it says is shown. --}}
            @if ($this->preview())
                <flux:text data-test="cover-origin">Así se ve tu foto. Guárdala para que se quede.</flux:text>
            @else
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
            @endif

            {{-- La descarga que falló no deja rastro en ningún otro lado: la
                 cola la da por terminada y el servidor no escribe bitácora
                 (BIB-52). Se queda a la vista hasta que una descarga funcione,
                 incluida la que se reintenta desde aquí. --}}
            @if ($book->coverDownloadHasFailed())
                <flux:callout variant="warning" icon="photo" data-test="cover-download-failed">
                    <flux:callout.text>
                        La última descarga de la Portada falló el
                        {{ $book->cover_failed_at->translatedFormat('d M Y, H:i') }},
                        {{ $book->cover_failed_at->diffForHumans() }}.

                        @if ($book->cover_failure)
                            El motivo: «{{ $book->cover_failure }}»
                        @endif
                    </flux:callout.text>
                </flux:callout>
            @endif

            <form wire:submit="save" class="flex flex-col gap-3">
                <flux:input
                    type="file"
                    wire:model="file"
                    accept="image/*"
                    :label="$book->hasGeneratedCover() ? 'Subir tu foto de la Portada' : 'Reemplazar la Portada'"
                />

                {{-- The file goes up the moment it is chosen. Saving before it
                     arrives saves nothing, so the button waits for it and the
                     screen says it is on its way. --}}
                <div wire:loading.flex wire:target="file" class="items-center gap-2" data-test="cover-uploading">
                    <flux:icon.loading class="size-4" />
                    <flux:text>Subiendo la foto…</flux:text>
                </div>

                <flux:button
                    type="submit"
                    variant="primary"
                    class="self-start"
                    wire:loading.attr="disabled"
                    wire:target="file,save"
                >
                    Guardar
                </flux:button>
            </form>

            <form wire:submit="fromAmazon" class="flex flex-col gap-3" data-test="cover-from-amazon">
                <flux:input
                    wire:model="amazonLink"
                    label="Portada desde Amazon"
                    description="Pega el enlace de la página del Libro en Amazon y se trae su portada."
                    placeholder="https://www.amazon.com.mx/…/dp/…"
                    autocomplete="off"
                    data-test="amazon-link-field"
                />

                <flux:button type="submit" variant="outline" class="self-start" wire:target="fromAmazon">
                    Traer la portada de Amazon
                </flux:button>
            </form>

            @if ($book->canRetryCover())
                <flux:button wire:click="retry" variant="outline" class="self-start">
                    Reintentar la descarga
                </flux:button>
            @endif

            {{-- What a catalog answers is not always the Cover of the book, and
                 replacing it assumes a photograph that during the intake does
                 not exist yet (BIB-34). --}}
            @if ($book->hasCover())
                <flux:modal.trigger name="quitar-portada">
                    <flux:button variant="outline" class="self-start" data-test="remove-cover">
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
