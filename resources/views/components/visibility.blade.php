@props([
    'public' => false,
    'hidden' => false,
])

{{--
    Which side of the Showcase a block of the Book sheet is on. It is the same
    marker everywhere, because the difference between what gets published and
    what stays here is the one thing this screen cannot leave to be guessed.
--}}
<span {{ $attributes->class(['type-section-heading text-zinc-500 dark:text-zinc-400']) }} data-test="visibility">
    @if (! $public)
        No sale de aquí
    @elseif ($hidden)
        Se vería en la Vitrina; este Libro está oculto
    @else
        Se ve en la Vitrina
    @endif
</span>
