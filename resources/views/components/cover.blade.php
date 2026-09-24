@props([
    'book',
    'size' => 'thumbnail',
])

@php
    $url = $size === 'full' ? $book->coverUrl() : $book->coverThumbnailUrl();
@endphp

{{--
    Every Book has a Cover (ADR-008). When it is not a stored image it is the
    generated piece, which takes the same place: there are no holes in the grid.
--}}
@if ($url)
    <img
        src="{{ $url }}"
        alt="Portada de {{ $book->title }}"
        loading="lazy"
        {{ $attributes->class(['cover-edge aspect-2/3 w-full rounded-lg object-cover']) }}
    >
@else
    <x-generated-cover :piece="$book->generatedCover()" {{ $attributes }} />
@endif
