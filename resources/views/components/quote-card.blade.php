@use('App\Enums\QuoteScale')
@use('App\Enums\ShareFormat')
@use('App\Quotes\QuoteCard')

@props(['quote', 'cover' => null, 'standalone' => true, 'format' => null])

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

    // `format` is the canvas the card is being rasterized onto, and it is null
    // on every screen. It is what tells the two images apart, because the width
    // in pixels cannot: the tall canvas is 1080 px wide, which is past `md:`
    // just like the wide one, and asking the breakpoint would give the story
    // the layout of a card that is seen on a screen (BIB-49).
    $stacked = $standalone && $format === ShareFormat::Story;

    // Side by side from `md:` up: the screen, and the wide image.
    $beside = $standalone && ! $stacked;

    // Only the wide image crops the Cover, by the right edge of the card. On
    // the stacked one the Cover sits at the bottom with room all around it,
    // where there is no edge to crop it with.
    $cropped = $photograph && ! $stacked;
@endphp

{{--
    The card of a Quote, in the shapes it is drawn in (BIB-41, BIB-49).

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

    **Stacked on the tall canvas** it is handed `:format` and puts the text
    above and the Cover below instead of beside it. A card of two columns on a
    canvas half as wide as it is tall comes out a short band across the middle
    with the rest of the picture empty, and the two columns are what fix its
    height: nothing about the Quote can make it fill the canvas.

    It draws the Quote it is handed and asks nothing: whether this Book belongs
    in the Showcase is decided by the query that feeds the card, which filters
    by Hidden like every other one (ADR-003).
--}}
<figure
    data-test="quote-card"
    {{ $attributes->class([
        'paper relative overflow-hidden rounded-xl border border-divider text-ink',
        'md:flex md:items-stretch' => $beside,
        // On a canvas of fixed size the card fills it, and the paper of the
        // page shows as a band around it. Left to its own height it lands near
        // three quarters of the wide canvas and reads as a card lost in an
        // empty space, which is what BIB-49 came from.
        'h-full' => $format !== null,
        'flex flex-col items-center px-20 py-16 text-center' => $stacked,
    ]) }}
>
    {{-- The column of text. On a phone it is the whole card; from md up it
         leaves the right side to the Cover, when there is one. Stacked it is
         the whole card again, centred and capped so the lines do not run the
         full width of the canvas. --}}
    <div @class([
        'flex flex-col',
        'gap-7 p-8 md:gap-9 md:py-12 md:pl-12' => ! $stacked,
        'md:min-w-0 md:flex-1 md:pr-10' => $beside,
        'md:pr-12' => ! $standalone,
        // On the wide canvas the column is taller than its content, because the
        // card now fills the canvas. Centred, the block sits where the Cover
        // beside it does instead of hanging from the top edge.
        'md:justify-center' => $format === ShareFormat::Card,
        'w-full max-w-[780px] items-center gap-9' => $stacked,
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
             English pair is left for what the Book quotes inside them.

             Stacked, the same three sizes come from the `story` family: the
             measure there is the width of the card and not of a column beside
             the Cover, so the type is set larger and loses the Rule. --}}
        <blockquote
            data-test="quote-text"
            @class([
                'whitespace-pre-line',
                'type-quote-large' => ! $stacked && $card->scale() === QuoteScale::Large,
                'type-quote' => ! $stacked && $card->scale() === QuoteScale::Regular,
                'type-quote-small' => ! $stacked && $card->scale() === QuoteScale::Small,
                'type-quote-story-large' => $stacked && $card->scale() === QuoteScale::Large,
                'type-quote-story' => $stacked && $card->scale() === QuoteScale::Regular,
                'type-quote-story-small' => $stacked && $card->scale() === QuoteScale::Small,
            ])
        >«{{ $card->text() }}»</blockquote>

        @if ($stacked)
            {{-- The Rule, laid down flat. Down the left of centred type it
                 would hang off nothing, and without it the caption reads as a
                 third paragraph of the Quote. --}}
            <div class="h-px w-16 bg-rule" data-test="quote-rule"></div>
        @endif

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

            Beside the text its width is a fraction of the card and its height
            follows from the proportion of a book, so the Cover is measured
            against the card and never in pixels of its own. What changes from
            one Quote to the next is the size of the type, which makes the
            column of text taller or shorter; the frame keeps its size, stays
            centred, and the card is never shorter than it — so the same amount
            of Cover shows on a Quote of six words and on one of forty.

            The margins are the room the lean needs: rotated, the corners of the
            Cover reach past the frame, and without it a short Quote would clip
            them and a long one would not.

            Stacked there is no column to take a fraction of, so the width is
            written in pixels — the only place in the card where it is, and the
            reason is that the canvas of a story is fixed too. `mt-auto` is what
            holds it at the foot of the card however long the Quote runs, so a
            Quote of six words and one of forty leave the same air.
        --}}
        <div
            data-test="{{ $cropped ? 'cover-cropped' : 'cover-whole' }}"
            @class([
                // Every side is written out instead of `mx`/`my`: which of two
                // utilities on one element wins is decided by their order in
                // the compiled stylesheet, and `mb-0` beating `my-[2%]` is not
                // something the markup would show.
                'relative',
                'ml-8 mr-8 mb-8 w-32 md:mt-[2%] md:mb-[2%] md:ml-0 md:aspect-2/3 md:w-[26%] md:shrink-0 md:self-center' => ! $stacked,
                // The photograph leans off the right edge and gets cropped by
                // it; the generated piece keeps a margin, because cropping type
                // takes a bite out of the title (ADR-008).
                'md:mr-0' => $cropped,
                'md:mr-[3%]' => ! $photograph && ! $stacked,
                'mt-auto w-65 shrink-0' => $stacked,
            ])
        >
            <x-cover
                :book="$book"
                :src="$cover"
                size="full"
                decorative
                @class([
                    'w-full rotate-(--cover-tilt) shadow-(--shadow-cover)',
                    'md:absolute md:inset-0 md:h-full' => ! $stacked,
                    'md:translate-x-[12%]' => $cropped,
                ])
            />
        </div>
    @endif
</figure>
