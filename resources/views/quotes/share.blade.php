@use('App\Enums\ShareFormat')

@props(['quote', 'format', 'styles', 'cover'])

{{--
    The page the renderer takes the picture of (ADR-009).

    It is not a second design: the card in the middle is the same component the
    Showcase draws, with the same stylesheet. What this page does is put it on
    the paper at the exact size the format asks for and hand it everything it
    would otherwise go looking for over the network.

    The language is written down, like in the Showcase layout: the application
    runs in `en` because that is the language of the code, and what is drawn
    here is in Spanish.
--}}
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">

        <title>{{ $quote->book->title }}</title>

        {{-- Everything, written in: no stylesheet to fetch, no typeface to
             fetch, no image to fetch. A renderer that cannot reach this site
             draws the same picture as one that can. --}}
        <style>{!! $styles !!}</style>

        <style>
            html,
            body {
                width: {{ $format->width() }}px;
                height: {{ $format->height() }}px;
                margin: 0;
                overflow: hidden;
            }
        </style>
    </head>

    {{-- The paper is the page and not the card: the card carries its own, and
         the band around it is what keeps the picture from reading as a
         screenshot with the edges cut off. --}}
    <body class="paper flex h-full w-full items-center justify-center text-ink antialiased">
        {{-- The wide one is the card almost edge to edge, because a network
             draws it small. The tall one leaves the paper showing above and
             below, which is what makes it read as a page of a notebook in a
             story and not as a cropped card. --}}
        <div @class([
            'w-full',
            'px-10 py-8' => $format === ShareFormat::Card,
            'px-12 py-24' => $format === ShareFormat::Story,
        ])>
            <x-quote-card :quote="$quote" :cover="$cover" class="w-full" />
        </div>
    </body>
</html>
