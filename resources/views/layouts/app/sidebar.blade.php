@use('App\Release\Version')

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="paper min-h-screen dark:bg-zinc-800 dark:bg-none">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            {{--
                Qué versión está corriendo, debajo del nombre de la biblioteca
                (BIB-39). Sale del encabezado más reciente de CHANGELOG.md, y
                mientras no se haya cortado ninguna lo dice así.
            --}}
            <div class="px-2 pb-3 -mt-2">
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ Version::label() }}</flux:text>
            </div>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="rectangle-stack" :href="route('shelf')" :current="request()->routeIs('shelf')" wire:navigate>
                        Acervo
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="magnifying-glass" :href="route('quote-search')" :current="request()->routeIs('quote-search')" wire:navigate>
                        Búsqueda de citas
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="tag" :href="route('categories')" :current="request()->routeIs('categories')" wire:navigate>
                        Categorías
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="plus" :href="route('scan')" :current="request()->routeIs('scan')" wire:navigate>
                        Escaneo
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="book-open-text" :href="route('title-lookup')" :current="request()->routeIs('title-lookup')" wire:navigate>
                        Alta por título
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="camera" :href="route('manual-entry')" :current="request()->routeIs('manual-entry')" wire:navigate>
                        Captura manual
                    </flux:sidebar.item>

                    @php($coverDrafts = \App\Models\CoverDraft::query()->count())

                    <flux:sidebar.item icon="photo" :href="route('cover-drafts')" :current="request()->routeIs('cover-drafts')" :badge="$coverDrafts ?: null" wire:navigate>
                        Fotos por revisar
                    </flux:sidebar.item>

                    {{--
                        Ajustes vivía en el menú del perfil, arriba a la derecha,
                        donde costaba encontrarlo (BIB-56). Entra por Perfil,
                        que es a donde lleva `settings`.

                        El renglón se marca por la dirección y no por el nombre
                        de la ruta: las siete subpantallas cuelgan de
                        `settings/`, así que la siguiente que se agregue lo
                        marca sin que haya que volver acá.
                    --}}
                    <flux:sidebar.item icon="cog" :href="route('profile.edit')" :current="request()->is('settings', 'settings/*')" wire:navigate>
                        Ajustes
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>
        </flux:sidebar>

        {{--
            La barra de arriba. En pantalla angosta abre además el menú lateral;
            en pantalla ancha lleva el menú del usuario y nada más, hasta que
            algo se gane su lugar acá con un argumento (BIB-39).
        --}}
        <flux:header class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <x-user-menu />
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
