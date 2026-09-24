@use('App\Covers\GeneratedCover')

@props(['piece'])

{{--
    The Cover of a Book no Source gave one for (ADR-008): the title and the
    Authors over a flat field, in the proportion of a book.

    It is drawn here and not stored: the same markup serves the grid of the
    Shelf, the sheet and the card of a Quote, and the day a title gets corrected
    the piece comes out corrected too, with nothing to clean up.

    It is typographic and never photographic. That is half of why it does not
    get confused with a real Cover; the other half is its Origin.
--}}
<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 {{ GeneratedCover::WIDTH }} {{ GeneratedCover::HEIGHT }}"
    role="img"
    aria-label="{{ $piece->label() }}"
    {{ $attributes->class(['cover-edge aspect-2/3 w-full rounded-lg']) }}
>
    <rect width="{{ GeneratedCover::WIDTH }}" height="{{ GeneratedCover::HEIGHT }}" fill="{{ $piece->field() }}" />

    {{-- The hairline: the one thing on the piece that is not type. --}}
    <line
        x1="{{ GeneratedCover::MARGIN }}"
        y1="{{ $piece->ruleBaseline() }}"
        x2="{{ GeneratedCover::MARGIN + 64 }}"
        y2="{{ $piece->ruleBaseline() }}"
        stroke="#1a1a18"
        stroke-opacity="0.34"
        stroke-width="2"
    />

    {{--
        The Ink of the direction, on both blocks. The hierarchy is weight and
        tracking, the same as everywhere else: the muted Ink does not reach
        4.5:1 over these fields, and this is a Cover, not a caption.
    --}}
    <text
        fill="#1a1a18"
        font-family="Instrument Sans, ui-sans-serif, system-ui, sans-serif"
        font-weight="700"
        font-size="{{ $piece->titleSize() }}"
        letter-spacing="{{ $piece->titleTracking() }}"
    >
        @foreach ($piece->titleLines() as $line => $text)
            <tspan x="{{ GeneratedCover::MARGIN }}" y="{{ $piece->titleBaseline($line) }}">{{ $text }}</tspan>
        @endforeach
    </text>

    @if (filled($piece->authorLines()))
        <text
            fill="#1a1a18"
            font-family="Instrument Sans, ui-sans-serif, system-ui, sans-serif"
            font-weight="400"
            font-size="{{ $piece->authorsSize() }}"
            letter-spacing="{{ $piece->authorsTracking() }}"
        >
            @foreach ($piece->authorLines() as $line => $text)
                <tspan x="{{ GeneratedCover::MARGIN }}" y="{{ $piece->authorBaseline($line) }}">{{ $text }}</tspan>
            @endforeach
        </text>
    @endif
</svg>
