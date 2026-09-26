@use('App\Showcase\Showcase')
@use('App\Release\Version')

@props(['meta', 'category' => null, 'browse' => true])

{{--
    The page of the Showcase: paper, and nothing of the back office around it.

    The navigation by Category closes the page, under the collection and never
    above it (BIB-43): the first thing on a page is what the page is about. It
    is drawn here and not on each page, so that no public surface can be added
    without it; the list it names comes from `App\Showcase\Showcase`, which is
    where every public query starts (ADR-003). The sheet of a Book is the one
    page that carries no such list —it names its own Categories, which are one
    to three— and it says so with `browse`.

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

        {{--
            Qué versión está corriendo, acá también (BIB-39). Es la cara que se
            abre sin iniciar sesión, que es justo lo que hacía falta para
            comprobar si lo que se está viendo ya trae el arreglo.
        --}}
        @if (Version::current())
            <meta name="version" content="{{ Version::current() }}">
        @endif

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">

        @fonts

        @vite(['resources/css/app.css'])
    </head>

    <body class="paper min-h-screen text-ink antialiased">
        <main class="mx-auto flex w-full max-w-5xl flex-col gap-16 px-6 py-14 md:px-10 md:py-20">
            {{ $slot }}

            {{-- The closing block: the subjects, and the name of the site,
                 which is the way back to the whole library from anywhere. --}}
            <footer class="flex flex-col gap-8 border-t border-divider pt-8">
                @if ($browse)
                    <x-showcase.categories
                        :categories="Showcase::categories()"
                        :current="$category"
                    />
                @endif

                <div class="flex flex-wrap items-center justify-between gap-4">
                    <a href="{{ route('showcase') }}" class="flex items-center gap-2 text-link">
                        <svg viewBox="0 0 68 76" class="h-5 w-auto fill-current" aria-hidden="true">
                            <rect x="3"  y="14" width="7" height="56" rx="1.5"/>
                            <rect x="11" y="26" width="6" height="44" rx="1.5"/>
                            <rect x="18" y="10" width="8" height="60" rx="1.5"/>
                            <rect x="27" y="30" width="6" height="40" rx="1.5"/>
                            <rect x="34" y="18" width="7" height="52" rx="1.5"/>
                            <rect x="42" y="22" width="6" height="48" rx="1.5"/>
                            <rect x="49" y="8"  width="8" height="62" rx="1.5"/>
                            <rect x="58" y="34" width="6" height="36" rx="1.5"/>
                            <rect x="2"  y="71" width="64" height="3" rx="1" opacity="0.35"/>
                        </svg>
                        <span class="type-section-heading">{{ config('app.name') }}</span>
                    </a>

                    <nav class="flex gap-4 text-sm text-ink-muted">
                        <a href="{{ route('showcase.privacy') }}" class="hover:text-link">Privacidad</a>
                        <a href="{{ route('showcase.terms') }}" class="hover:text-link">Términos de uso</a>
                    </nav>
                </div>
            </footer>
        </main>
    </body>
</html>
