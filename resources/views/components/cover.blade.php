@use('App\Covers\CoverAttributes')

@props([
    'book',
    'size' => 'thumbnail',
    'decorative' => false,
    'src' => null,
])

@php
    // `src` is for the one surface that cannot follow an address: the card as
    // the renderer draws it, where the image travels inlined because there is
    // no network to fetch it over (ADR-009). Everywhere else the Cover is
    // served by the route, which is the only place that knows about Hidden.
    $url = $src ?? ($size === 'full' ? $book->coverUrl() : $book->coverThumbnailUrl());
@endphp

{{--
    Every Book has a Cover (ADR-008). When it is not a stored image it is the
    generated piece, which takes the same place: there are no holes in the grid.

    `decorative` is for the surfaces where the Book is already named next to the
    Cover — the card of a Quote is one. There the image carries nothing the text
    does not, and announcing it reads the title twice to whoever hears the page
    instead of seeing it.
--}}
@if ($url)
    <img
        src="{{ $url }}"
        alt="{{ $decorative ? '' : 'Portada de '.$book->title }}"
        loading="lazy"
        {{ $attributes->class(CoverAttributes::classes($attributes, 'cover-edge aspect-2/3 rounded-lg object-cover')) }}
    >
@else
    <x-generated-cover :piece="$book->generatedCover()" :decorative="$decorative" {{ $attributes }} />
@endif
