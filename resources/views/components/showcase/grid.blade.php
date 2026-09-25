@props(['books', 'showStatus' => false])

{{--
    The grid of the Showcase: the same one the Shelf draws, because a visitor
    recognises a book by its jacket for the same reason its owner does.

    The Cover goes whole, with its lean and its shadow and without cropping:
    here the Cover is not the picture but the way of finding one book among two
    hundred, and recognising it asks for the whole of it (CONTEXT.md,
    § Dirección visual).

    It is handed the Books already loaded. Whether they belong in the Showcase
    was decided by `App\Showcase\Showcase`, which is where every public query
    starts (ADR-003).
--}}
<ul class="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
    @foreach ($books as $book)
        <li class="flex flex-col gap-3">
            <a
                href="{{ route('showcase.book', $book) }}"
                class="group flex flex-col gap-3"
                data-test="book-{{ $book->getKey() }}"
            >
                <div class="px-2 pt-1">
                    {{-- The stamp of the Reading lives inside the box that
                         leans, so the word turns with the Cover and not against
                         it (BIB-38). --}}
                    <div class="relative rotate-(--cover-tilt) transition-transform group-hover:rotate-0">
                        {{-- The title and the Authors are written right under
                             the Cover, so the image carries nothing the text
                             does not: announcing it would read the Book
                             twice. --}}
                        <x-cover :book="$book" decorative class="shadow-(--shadow-cover)" />

                        <x-reading-stamp :book="$book" />
                    </div>
                </div>

                <div class="flex min-w-0 flex-col gap-1">
                    <span class="type-book-title line-clamp-3">{{ $book->title }}</span>

                    @if ($showStatus)
                        @if ($book->is_reading)
                            <span class="type-section-heading text-ink">Leyendo</span>
                        @elseif ($book->status === \App\Enums\Status::Wanted)
                            <span class="type-section-heading text-ink-muted">Por comprar</span>
                        @endif
                    @endif

                    @if (filled($book->authors))
                        <span class="type-authors line-clamp-2 text-ink-muted">
                            {{ implode(' · ', $book->authors) }}
                        </span>
                    @endif
                </div>
            </a>
        </li>
    @endforeach
</ul>
