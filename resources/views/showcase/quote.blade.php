@use('App\Enums\ShareFormat')

{{--
    The page of one Quote (BIB-31).

    It is the tool for sharing and not one more screen: what it holds is the
    card, the way back to the Book, the address to copy and the image to upload
    where a link brings nothing.

    The card in the middle is the same component the Book page draws, and the
    same one the renderer rasterizes (ADR-009): what somebody receiving the link
    sees in the preview is what they find when they open it.
--}}
<x-showcase.layout :meta="$meta">
    <article class="flex flex-col gap-10">
        <x-quote-card :quote="$quote" />

        <div class="flex flex-col gap-8 border-t border-divider pt-8 md:flex-row md:items-start md:justify-between md:gap-14">
            <div class="flex flex-col gap-3">
                <h2 class="type-section-heading text-ink-muted">De este libro</h2>

                <a href="{{ route('showcase.book', $book) }}" class="type-book-title text-link">
                    {{ $book->title }}
                </a>

                @if (filled($book->authors))
                    <p class="type-authors text-ink-muted" data-test="authors">
                        {{ implode(', ', $book->authors) }}
                    </p>
                @endif
            </div>

            <div class="flex flex-col gap-3 md:items-end">
                <h2 class="type-section-heading text-ink-muted">Compartir</h2>

                {{-- The address, written out and not hidden behind a button:
                     it gets copied by hand as often as it gets clicked, and it
                     is short enough to read. --}}
                <p class="font-mono text-sm break-all text-ink-muted" data-test="share-url">
                    {{ $quote->shareUrl() }}
                </p>

                @if ($quote->hasImageFor(ShareFormat::downloaded()))
                    {{-- The vertical one, for where a link brings nothing.
                         `download` and not `wire:navigate`: this is a file. --}}
                    <a
                        href="{{ route('quote.download', $quote) }}"
                        class="text-link"
                        download
                        data-test="download-image"
                    >
                        Descargar la imagen
                    </a>
                @elseif (! $quote->imagesFit())
                    {{-- Past the smallest legible size there is no image, and
                         there is not going to be one later either (ADR-009), so
                         this does not say it is on its way. --}}
                    <p class="text-sm text-ink-muted" data-test="image-too-long">
                        Esta cita no tiene imagen para descargar: es más larga de lo que cabe legible en una.
                        Al compartir el enlace se ve la portada del libro.
                    </p>
                @else
                    {{-- It gets drawn in the queue, so there is a while in which
                         there is nothing to hand over. Saying so beats handing
                         over a broken file. --}}
                    <p class="text-sm text-ink-muted" data-test="image-pending">
                        La imagen para compartir todavía no está lista.
                    </p>
                @endif
            </div>
        </div>
    </article>
</x-showcase.layout>
