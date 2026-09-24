@props(['label', 'showLabel' => true])

{{--
    Says that a machine wrote what is next to it (AiBadge).

    The mark is the drawing; the words beside it are optional. Where the reader
    may be meeting it for the first time it carries its label; where it repeats
    —one per Category in a list— the drawing alone is enough, because by then
    the context has explained it.

    What never goes away is the meaning: without visible words the label is
    still announced to a screen reader and shown on hover. A sparkle on its own
    means nothing to somebody who has not been told what it means, and nothing
    at all to somebody who cannot see it.

    It says one thing: a machine wrote this. Anything else that needs announcing
    is a different element — a mark that takes on every notice ends up meaning
    none of them.
--}}
<span
    {{ $attributes->class([
        'inline-flex w-fit items-center gap-2 text-sm text-ink-muted',
        'rounded-full border border-rule bg-divider/40 px-3 py-1.5' => $showLabel,
    ]) }}
    @unless ($showLabel) title="{{ $label }}" @endunless
>
    {{-- A stroke, not an emoji: this direction does not use any. --}}
    <svg
        viewBox="0 0 16 16"
        class="size-4 shrink-0"
        fill="none"
        stroke="currentColor"
        stroke-width="1.25"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path d="M8 1.5 9.4 6.6 14.5 8 9.4 9.4 8 14.5 6.6 9.4 1.5 8 6.6 6.6Z" />
        <path d="M13 1.5v2.4M14.2 2.7h-2.4" />
    </svg>

    @if ($showLabel)
        {{ $label }}
    @else
        <span class="sr-only">{{ $label }}</span>
    @endif
</span>
