@use('App\Showcase\Showcase')

@props(['meta', 'category' => null])

{{--
    The page of the Showcase: paper, and nothing of the back office around it.

    The navigation by Category is here and not on each page, so that no public
    surface can be added without it; the list it names comes from
    `App\Showcase\Showcase`, which is where every public query starts (ADR-003).

    It carries no appearance switch. The back office follows the system, light
    or dark; this face is always paper, because the Covers are the colour here
    and the whole direction is measured against that paper (CONTEXT.md,
    § Dirección visual).

    The language is written down and not read off the locale: the application
    runs in `en`, because that is the language of the code, and everything on
    this page is in Spanish. What is indexed has to say so (BIB-37).
--}}
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{--
            What this page says about itself comes from `App\Showcase\Meta`,
            which builds it out of the content of the page (BIB-37). Nothing is
            decided here, and nothing is left for a network to guess: what is
            not declared gets scraped off the page, and what gets scraped is the
            first text it finds — the state of Reading, half a Quote.
        --}}
        <title>{{ $meta->title() }}</title>

        <meta name="description" content="{{ $meta->description }}">
        <link rel="canonical" href="{{ $meta->canonical }}">

        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:locale" content="es_MX">
        <meta property="og:type" content="{{ $meta->type }}">
        <meta property="og:title" content="{{ $meta->socialTitle() }}">
        <meta property="og:description" content="{{ $meta->description }}">
        <meta property="og:url" content="{{ $meta->canonical }}">
        @if ($meta->image)
            <meta property="og:image" content="{{ $meta->image }}">
            <meta property="og:image:alt" content="{{ $meta->imageAlt }}">
        @endif
        <meta name="twitter:card" content="{{ $meta->card() }}">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css'])
    </head>

    <body class="paper min-h-screen text-ink antialiased">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-16 px-6 py-14 md:px-10 md:py-20">
            {{-- A page with no name of its own is the front page, and that is
                 the one place where «toda la biblioteca» is where you already
                 are. --}}
            <x-showcase.categories
                :categories="Showcase::categories()"
                :current="$category"
                :root="$meta->heading === null"
            />

            {{ $slot }}

            <footer class="border-t border-divider pt-8">
                <a href="{{ route('showcase') }}" class="type-section-heading text-link">
                    {{ config('app.name') }}
                </a>
            </footer>
        </div>
    </body>
</html>
