@props(['label', 'text' => null, 'showLabel' => true])

{{--
    Says that a machine wrote what is next to it (AiBadge).

    The mark is the drawing; the words beside it are optional and can be
    shorter than the full story. Three ways to read it:

    - `label` alone, visible: the words a reader meets for the first time,
      spelled out — «Descripción generada por IA».
    - `label` + `text`, visible: a short word stands in for the full one where
      space is tight and the context already explains it — «IA» inline at the
      end of a paragraph. `label` still carries the meaning, as the accessible
      name of the whole mark; `text` is announced nowhere twice.
    - `showLabel="false"`: the drawing alone, repeated — one per Category in a
      list, where the context has already explained it once.

    What never goes away is the meaning: without visible words, or with only
    the short word, `label` is still announced to a screen reader and shown on
    hover. A sparkle on its own means nothing to somebody who has not been
    told what it means, and nothing at all to somebody who cannot see it.

    It says one thing: a machine wrote this. Anything else that needs
    announcing is a different element — a mark that takes on every notice
    ends up meaning none of them.
--}}
<span
    {{ $attributes->class([
        'inline-flex w-fit items-center gap-1.5 text-sm text-ink-muted',
        'rounded-full border border-rule bg-divider/40 px-3 py-1.5' => $showLabel && $text === null,
    ]) }}
    @if ($text !== null) aria-label="{{ $label }}" @endif
    @if (! $showLabel) title="{{ $label }}" @endif
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

    @if ($showLabel && $text !== null)
        <span aria-hidden="true">{{ $text }}</span>
    @elseif ($showLabel)
        {{ $label }}
    @else
        <span class="sr-only">{{ $label }}</span>
    @endif
</span>
