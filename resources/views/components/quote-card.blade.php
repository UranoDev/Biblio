@use('App\Enums\QuoteScale')
@use('App\Quotes\QuoteCard')

@props(['quote', 'cover' => null, 'standalone' => true])

@php
    $book = $quote->book;
    $card = QuoteCard::for($quote);

    // `cover` is the stored image already inlined, and it arrives from one
    // place only: the copy of this card the renderer draws (ADR-009). There the
    // address of the Cover leads nowhere, because the renderer runs off this
    // site. On every screen it is null and the Cover is served by its route.

    // The stored image gets cropped by the edge of the card; the generated
    // piece does not. Cropping a photograph reads editorial, and cropping type
    // just cuts words — the same reason the grid of the Shelf shows the piece
    // whole (ADR-008).
    $photograph = $book->hasCover();
@endphp

{{--
    The card of a Quote, in the two shapes it is drawn in (BIB-41).

    **Standing on its own** —the page of the Quote, the image that gets shared—
    it carries the Book with it: the Cover, the title and the Authors, because
    there is nothing else around it saying which Book these words are from. It
    also carries its own paper and its own Ink instead of inheriting the ones of
    the screen, since the same markup has to hold up rasterized into an image
    (ADR-009), where there is no screen around it at all.

    **Inside the sheet of its own Book** it is handed `:standalone="false"` and
    draws the Quote and its page, and nothing else. The Cover, the title and the
    Authors are already at the top of that page, and a Book with forty Quotes
    would otherwise show its Cover forty-one times.

    It draws the Quote it is handed and asks nothing: whether this Book belongs
    in the Showcase is decided by the query that feeds the card, which filters
    by Hidden like every other one (ADR-003).
--}}
<figure
    data-test="quote-card"
    {{ $attributes->class([
        'paper relative overflow-hidden rounded-xl border border-divider text-ink',
        'md:flex md:items-stretch' => $standalone,
    ]) }}
>
    {{-- The column of text. On a phone it is the whole card; from md up it
         leaves the right side to the Cover, when there is one. --}}
    <div @class([
        'flex flex-col gap-7 p-8 md:gap-9 md:py-12 md:pl-12',
        'md:min-w-0 md:flex-1 md:pr-10' => $standalone,
        'md:pr-12' => ! $standalone,
    ])>
        @if ($quote->page)
            {{-- The heading line of the reference is a maker's mark, which is
                 not ours to copy. What goes in its place is the one thing the
                 card knows that the blocks below do not say. --}}
            <p class="type-section-heading text-ink-muted" data-test="quote-page">Página {{ $quote->page }}</p>
        @endif

        {{-- The Quote, in the size its own length asks for, and never cut. The
             quotation marks are drawn here and not stored with it, and they are
             the angular ones: those are the first level in Spanish, and the
             English pair is left for what the Book quotes inside them. --}}
        <blockquote
            data-test="quote-text"
            @class([
                'whitespace-pre-line',
                'type-quote-large' => $card->scale() === QuoteScale::Large,
                'type-quote' => $card->scale() === QuoteScale::Regular,
                'type-quote-small' => $card->scale() === QuoteScale::Small,
            ])
        >«{{ $card->text() }}»</blockquote>

        @if ($standalone)
            {{-- The caption. It starts where the Rule starts, not where the
                 Quote does: that outdent is what keeps it reading as a caption
                 under the Quote and not as a second block beside it. --}}
            <figcaption class="flex flex-col gap-2">
                <span class="type-book-title" data-test="quote-book-title">{{ $book->title }}</span>

                @if (filled($book->authors))
                    <span class="type-authors text-ink-muted" data-test="quote-authors">
                        {{ implode(', ', $book->authors) }}
                    </span>
                @endif
            </figcaption>
        @endif
    </div>

    @if ($standalone)
        {{--
            The frame the Cover is drawn in, and the whole reason the Cover
            comes out the same on every card.

            Its width is a fraction of the card and its height follows from the
            proportion of a book, so the Cover is measured against the card and
            never in pixels of its own. What changes from one Quote to the next
            is the size of the type, which makes the column of text taller or
            shorter; the frame keeps its size, stays centred, and the card is
            never shorter than it — so the same amount of Cover shows on a Quote
            of six words and on one of forty.

            The margins are the room the lean needs: rotated, the corners of the
            Cover reach past the frame, and without it a short Quote would clip
            them and a long one would not.
        --}}
        <div
            data-test="{{ $photograph ? 'cover-cropped' : 'cover-whole' }}"
            @class([
                // Every side is written out instead of `mx`/`my`: which of two
                // utilities on one element wins is decided by their order in
                // the compiled stylesheet, and `mb-0` beating `my-[2%]` is not
                // something the markup would show.
                'relative ml-8 mr-8 mb-8 w-32 md:mt-[2%] md:mb-[2%] md:ml-0 md:aspect-2/3 md:w-[26%] md:shrink-0 md:self-center',
                // The photograph leans off the right edge and gets cropped by
                // it; the generated piece keeps a margin, because cropping type
                // takes a bite out of the title (ADR-008).
                'md:mr-0' => $photograph,
                'md:mr-[3%]' => ! $photograph,
            ])
        >
            <x-cover
                :book="$book"
                :src="$cover"
                size="full"
                decorative
                @class([
                    'w-full rotate-(--cover-tilt) shadow-(--shadow-cover) md:absolute md:inset-0 md:h-full',
                    'md:translate-x-[12%]' => $photograph,
                ])
            />
        </div>
    @endif
</figure>
