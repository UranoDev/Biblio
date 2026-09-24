@props(['title' => null])

{{--
    The page of the Showcase: paper, and nothing of the back office around it.

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

        <title>{{ filled($title) ? $title.' · '.config('app.name') : config('app.name') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css'])
    </head>

    <body class="paper min-h-screen text-ink antialiased">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-16 px-6 py-14 md:px-10 md:py-20">
            {{ $slot }}

            <footer class="border-t border-divider pt-8">
                <a href="{{ route('showcase') }}" class="type-section-heading text-link">
                    {{ config('app.name') }}
                </a>
            </footer>
        </div>
    </body>
</html>
