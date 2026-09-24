@use('App\Release\Version')

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

{{--
    Qué versión está corriendo, comprobable sin iniciar sesión (BIB-39). Sin
    changelog no hay etiqueta: no hay versión cortada todavía, y eso se dice
    callando en vez de inventar un número.
--}}
@if (Version::current())
    <meta name="version" content="{{ Version::current() }}" />
@endif

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
