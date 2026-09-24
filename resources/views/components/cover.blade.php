@props([
    'book',
    'size' => 'thumbnail',
])

@php
    $url = $size === 'full' ? $book->coverUrl() : $book->coverThumbnailUrl();
@endphp

{{-- A Book without a Cover does not show a broken image: it shows its title. --}}
@if ($url)
    <img
        src="{{ $url }}"
        alt="Portada de {{ $book->title }}"
        loading="lazy"
        {{ $attributes->class(['cover-edge aspect-2/3 w-full rounded-lg object-cover']) }}
    >
@else
    <div
        role="img"
        aria-label="{{ $book->title }}, sin Portada"
        {{ $attributes->class([
            'flex aspect-2/3 w-full flex-col justify-end gap-1 rounded-lg',
            'cover-edge bg-zinc-100 p-3',
            'dark:bg-zinc-800',
        ]) }}
    >
        <span class="line-clamp-4 text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $book->title }}</span>

        @if (filled($book->authors))
            <span class="line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">{{ implode(', ', $book->authors) }}</span>
        @endif
    </div>
@endif
