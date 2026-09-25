@props(['book', 'decorative' => false])

{{--
    The stamp of a read Book, over its Cover (BIB-38).

    It says one thing only — that the book was read — and it says it from across
    a grid of two hundred Covers. **It does not carry the year.** Fitting four
    digits asks for a bigger stamp, and even then they have to be walked up to;
    letting them go took `LEÍDO` from 4.5 to 7 px, which is the size at which
    the word is read at a glance. The year lives in the line above the title,
    where the question it answers is.

    So in the grid a Book read in 2019 and one read without a date carry the
    same stamp, and that is on purpose: the grid answers «¿lo leí?» and the
    sheet answers «¿cuándo?».

    It is placed inside the box that carries the tilt of the Cover, never beside
    it, so the word turns **with** the Cover and stays parallel to it. A stamp
    that holds level against a leaning Cover reads as glued to the screen.

    The stamp brings its own field of paper because dark ink over a dark
    photograph is not there at all. Over the generated piece (ADR-008) the field
    is flat and the ink would read without it; over a real photograph it may
    fall anywhere, and the contrast cannot depend on which book it landed on.
    tests/Unit/Design/StampTest.php measures the ink against the darkest that
    field can get, which is the one over a black Cover.

    It sits at the top right corner, and that corner is chosen: the generated
    piece hangs its title and its Authors from the bottom, and a stamp down
    there would land on the words of the Cover that most Books have.
--}}
@php
    $status = $book->readingStatus();
@endphp

{{-- A Wanted book has not been bought yet: whether it was read before is
     irrelevant to a visitor looking at the list, and the stamp would read
     as a contradiction next to «Por comprar». --}}
@if ($status->isRead() && $book->status !== \App\Enums\Status::Wanted)
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 100 100"
        @if ($decorative)
            {{-- The line above the title already says it, and hearing «Leído»
                 twice on one sheet is the same repetition the design avoids. --}}
            aria-hidden="true"
        @else
            role="img"
            aria-label="Leído"
        @endif
        {{ $attributes->class(['pointer-events-none absolute top-[6%] right-[7%] w-[30%] select-none']) }}
        data-test="reading-stamp"
        data-reading="{{ $status->value }}"
    >
        <circle
            cx="50"
            cy="50"
            r="47"
            class="fill-stamp-field [fill-opacity:var(--stamp-field-alpha)]"
        />

        {{-- The double ring: the outer one heavy, the inner one a hairline. --}}
        <circle cx="50" cy="50" r="47" fill="none" class="stroke-stamp-ink" stroke-width="3" />
        <circle cx="50" cy="50" r="39" fill="none" class="stroke-stamp-ink" stroke-width="1.5" />

        {{-- The tracking is added after the last letter too, which pulls what
             is seen half a step to the left of the anchor, so the anchor is
             walked the same half step to the right. --}}
        <text
            x="51.8"
            y="50"
            text-anchor="middle"
            dominant-baseline="central"
            class="fill-stamp-ink"
            font-family="Instrument Sans, ui-sans-serif, system-ui, sans-serif"
            font-weight="700"
            font-size="16"
            letter-spacing="3.5"
        >LEÍDO</text>
    </svg>
@endif
