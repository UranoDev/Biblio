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
    <body class="paper h-full w-full text-ink antialiased">
        {{-- The band of paper, and nothing else: the card fills whatever is
             left of the canvas. It used to be handed its own height and centred
             in the middle, which left it sitting in the wide canvas at about
             three quarters of its height with the rest empty — the card lost in
             a blank space that BIB-49 is about.

             The tall one gets the wider band, which is the margin of a page of
             a notebook; the rest of the air it leaves comes from inside the
             card, above the Quote and under the Cover, and it is the same
             however long the Quote runs. --}}
        <div @class([
            'h-full w-full',
            'px-10 py-8' => $format === ShareFormat::Card,
            'p-10' => $format === ShareFormat::Story,
        ])>
            <x-quote-card :quote="$quote" :cover="$cover" :format="$format" class="w-full" />
        </div>
    </body>
</html>
