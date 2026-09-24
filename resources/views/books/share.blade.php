@props(['book', 'format', 'styles', 'cover'])

{{--
    The page the renderer takes the picture of (BIB-50).

    It is the same page as the one of a Quote and for the same reason (ADR-009):
    the card in the middle is a component of this application with the stylesheet
    of this application, and what this adds is everything a page would otherwise
    go looking for over the network. The renderer runs somewhere else and may not
    be able to reach this site at all — the Covers are served by a route, behind
    a check of visibility, and in development the site is `localhost`.

    There is one format and not two: a Book has nothing that gets uploaded to
    Instagram by hand, which is what the tall one exists for.

    The language is written down, like in the Showcase layout: the application
    runs in `en` because that is the language of the code, and what is drawn here
    is in Spanish.
--}}
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">

        <title>{{ $book->title }}</title>

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
        <div class="h-full w-full px-10 py-8">
            <x-book-card :book="$book" :cover="$cover" class="w-full" />
        </div>
    </body>
</html>
