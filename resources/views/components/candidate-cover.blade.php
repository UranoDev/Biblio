@props([
    'url' => null,
    'title',
    'authors' => [],
])

{{--
    The Cover of an edition that is not a Book yet, so the image comes from the
    Source: there is none stored to pull from. The one that gets stored is the
    one of the registered Book, and that one is never linked (ADR-004).
--}}
@if (filled($url))
    <img
        src="{{ $url }}"
        alt="Portada de {{ $title }}"
        loading="lazy"
        referrerpolicy="no-referrer"
        {{ $attributes->class(['cover-edge aspect-2/3 w-full rounded-lg object-cover']) }}
    >
@else
    <div
        role="img"
        aria-label="{{ $title }}, sin Portada"
        {{ $attributes->class([
            'flex aspect-2/3 w-full flex-col justify-end gap-1 rounded-lg',
            'cover-edge bg-zinc-100 p-2',
            'dark:bg-zinc-800',
        ]) }}
    >
        <span class="line-clamp-4 text-xs font-medium text-zinc-800 dark:text-zinc-100">{{ $title }}</span>

        @if (filled($authors))
            <span class="line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">{{ implode(', ', $authors) }}</span>
        @endif
    </div>
@endif
