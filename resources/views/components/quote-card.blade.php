@use('App\Enums\QuoteScale')
@use('App\Quotes\QuoteCard')

@props(['quote'])

@php
    $book = $quote->book;
    $card = QuoteCard::for($quote);

    // The stored image gets cropped by the edge of the card; the generated
    // piece does not. Cropping a photograph reads editorial, and cropping type
    // just cuts words — the same reason the grid of the Shelf shows the piece
    // whole (ADR-008).
    $photograph = $book->hasCover();
@endphp

{{--
    The card of a Quote: the words of the Book with the Book beside them.

    It is the piece that gets shared, so it carries its own paper and its own
    Ink instead of inheriting the ones of the screen around it — the same
    markup has to hold up rasterized into an image (ADR-009), where there is no
    screen around it at all.

    It draws the Quote it is handed and asks nothing: whether this Book belongs
    in the Showcase is decided by the query that feeds the card, which filters
    by Hidden like every other one (ADR-003).
--}}
<figure
    data-test="quote-card"
    {{ $attributes->class(['paper relative overflow-hidden rounded-xl border border-divider text-ink']) }}
>
    {{-- The column of text. On a phone it is the whole card; from md up it
         leaves the right side to the Cover. --}}
    <div @class([
        'flex flex-col gap-7 p-8 md:gap-9 md:py-12 md:pl-12',
        // The gutter each Cover needs: the cropped one takes more of the card,
        // and the leaning corner of either has to clear the text.
        'md:pr-[50%]' => $photograph,
        'md:pr-[46%]' => ! $photograph,
    ])>
        @if ($quote->page)
            {{-- The heading line of the reference is a maker's mark, which is
                 not ours to copy. What goes in its place is the one thing the
                 card knows that the three blocks below do not say. --}}
            <p class="type-section-heading text-ink-muted" data-test="quote-page">Página {{ $quote->page }}</p>
        @endif

        {{-- The Quote, in the size its own length asks for, and never cut. The
             quotation marks are drawn here and not stored with it. --}}
        <blockquote
            data-test="quote-text"
            @class([
                'whitespace-pre-line',
                'type-quote-large' => $card->scale() === QuoteScale::Large,
                'type-quote' => $card->scale() === QuoteScale::Regular,
                'type-quote-small' => $card->scale() === QuoteScale::Small,
            ])
        >“{{ $card->text() }}”</blockquote>

        {{-- The caption. It starts where the Rule starts, not where the Quote
             does: that outdent is what keeps it reading as a caption under the
             Quote and not as a second block beside it. --}}
        <figcaption class="flex flex-col gap-2">
            <span class="type-book-title" data-test="quote-book-title">{{ $book->title }}</span>

            @if (filled($book->authors))
                <span class="type-authors text-ink-muted" data-test="quote-authors">
                    {{ implode(', ', $book->authors) }}
                </span>
            @endif
        </figcaption>
    </div>

    @if ($photograph)
        {{--
            The Cover, cropped by the edge of the card and leaning the six
            degrees of the direction. On a phone it is a band under the text,
            cut by the bottom of the card; from md up the wrapper stops being a
            box (`contents`) and the Cover is placed against the card itself,
            running off the right and off the bottom.
        --}}
        <div data-test="cover-cropped" class="relative h-28 overflow-hidden md:contents">
            <x-cover
                :book="$book"
                size="full"
                decorative
                class="absolute left-8 top-4 w-40 rotate-(--cover-tilt) shadow-(--shadow-cover) md:left-auto md:top-[-9%] md:right-[-13%] md:w-[52%]"
            />
        </div>
    @else
        {{--
            The generated piece, whole. It is type on a flat field, and the
            fragment that makes a photograph look editorial only takes a bite
            out of its margins and its title (ADR-008). It keeps the lean and
            the shadow, so it still reads as an object.

            Its height is capped and not just proportional: a long Quote makes a
            tall card, and a piece that grew with it would walk into the column
            of text.
        --}}
        <div data-test="cover-whole" class="flex px-8 pb-8 md:contents">
            <x-cover
                :book="$book"
                size="full"
                decorative
                class="w-32 rotate-(--cover-tilt) shadow-(--shadow-cover) md:absolute md:right-[8%] md:top-1/2 md:h-[min(74%,18rem)] md:w-auto md:-translate-y-1/2"
            />
        </div>
    @endif
</figure>
