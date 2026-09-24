@use('App\Books\ShareCard')

@props(['book', 'cover' => null])

@php
    $card = ShareCard::for($book);
@endphp

{{--
    The card of a Book: the one a network draws when its link is pasted
    (BIB-50).

    It is the header of the sheet of the Book, laid on the canvas the networks
    ask for: the Cover on the left, leaning, and the title with its Authors
    beside it. Nothing else — the Description already travels in `og:description`
    and the Record is looked at, not read from across a timeline.

    The Cover keeps the proportion of a book and the card is what is wide: that
    is the whole point of the issue this came from. Handing a network the Cover
    on its own makes it zoom a 2:3 image into a 1.91:1 frame, and what comes out
    is a strip of the middle with the letters cut off.

    Like the card of a Quote it carries its own paper and its own Ink instead of
    inheriting the ones of the screen, because it has to hold up rasterized
    (ADR-009), where there is no screen around it at all.
--}}
<figure
    data-test="book-card"
    {{ $attributes->class('paper flex h-full w-full items-center gap-16 overflow-hidden rounded-xl border border-divider px-20 text-ink') }}
>
    {{-- The frame the Cover is drawn in. Its width is written in pixels, which
         is allowed here for the same reason it is on the stacked card of a
         Quote: the canvas is fixed too, and there is no column to take a
         fraction of. The margin is the room the lean needs — rotated, the
         corners reach past the frame. --}}
    <div class="my-4 w-[300px] shrink-0">
        <x-cover
            :book="$book"
            :src="$cover"
            size="full"
            decorative
            class="w-full rotate-(--cover-tilt) shadow-(--shadow-cover)"
        />
    </div>

    <figcaption class="flex min-w-0 flex-col gap-7">
        {{-- The title is the message of this card, so it is the thing that is
             set large; the size it gets is read off its own length. --}}
        <p
            data-test="book-card-title"
            @class([
                'type-book-card-title' => ! $card->titleIsLong(),
                'type-book-card-title-small' => $card->titleIsLong(),
            ])
        >{{ $book->title }}</p>

        @if (filled($book->authors))
            <p class="type-book-card-authors text-ink-muted" data-test="book-card-authors">
                {{ implode(', ', $book->authors) }}
            </p>
        @endif
    </figcaption>
</figure>
