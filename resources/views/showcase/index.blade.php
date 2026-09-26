{{--
    The Showcase, at the root of the site.

    With the collection indexed, most people do not come in through here: they
    land on a Quote from a search and walk back. So this page says whose library
    this is and lets it be walked, instead of introducing a product.

    Two sections: «En mi lista» (what I am reading and what I still need to buy,
    merged — BIB-63) and the Shelf (what I have). «En mi lista» is short and
    answers what is going on right now; the Shelf is the collection and goes
    underneath, where there is room for it (BIB-61).

    «Por comprar» is the name this system uses inside; a visitor is told «los
    que me faltan», which needs nothing explained (ADR-003).
--}}
<x-showcase.layout :meta="$meta">
    <header class="flex max-w-prose flex-col gap-5">
        <svg viewBox="0 0 68 76" class="h-10 w-auto" style="fill:#c2783a" aria-hidden="true">
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
        <h1 class="text-4xl font-bold tracking-tight md:text-5xl">{{ config('app.name') }}</h1>
        <p class="text-lg text-ink-muted">Aquí viven tus libros.</p>
    </header>

    {{--
        «En mi lista» merges what I am reading and what I have not bought yet
        into one section (BIB-63). Each card carries a small label — «Leyendo»
        or «Por comprar» — so the two groups are told apart without two headings.

        Books being read are shown first; the Wanted follow. Both are also
        counted together in the heading count, the same way a real list works.
    --}}
    @php $myList = $beingRead->merge($wanted); @endphp
    @if ($myList->isNotEmpty())
        <section class="flex flex-col gap-8" data-test="my-list">
            <div class="flex flex-col gap-2 border-t border-divider pt-8">
                <p class="type-section-heading text-ink-muted" data-test="my-list-count">
                    {{ $myList->count() }} {{ $myList->count() === 1 ? 'libro' : 'libros' }}
                </p>

                <h2 class="text-2xl font-bold tracking-tight">En mi lista</h2>
            </div>

            <x-showcase.grid :books="$myList" :show-status="true" />
        </section>
    @endif

    <section class="flex flex-col gap-8" data-test="shelf">
        <div class="flex flex-col gap-2 border-t border-divider pt-8">
            @if ($onShelf->isNotEmpty())
                <p class="type-section-heading text-ink-muted" data-test="shelf-count">
                    {{ $onShelf->count() }} {{ $onShelf->count() === 1 ? 'libro' : 'libros' }}
                </p>
            @endif

            <h2 class="text-2xl font-bold tracking-tight">Los que tengo</h2>
        </div>

        @if ($onShelf->isEmpty())
            <p class="text-ink-muted" data-test="shelf-empty">Todavía no hay ningún libro aquí.</p>
        @else
            {{-- The Shelf opens on its first rows, in an order drawn again on
                 every load (BIB-60). The two sections above are short lists and
                 are printed whole. --}}
            <x-showcase.first-rows :rows="3">
                <x-showcase.grid :books="$onShelf" />
            </x-showcase.first-rows>
        @endif
    </section>
</x-showcase.layout>
