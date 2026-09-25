{{--
    The Showcase, at the root of the site.

    With the collection indexed, most people do not come in through here: they
    land on a Quote from a search and walk back. So this page says whose library
    this is and lets it be walked, instead of introducing a product.

    Three sections, in this order: what I am reading, what I am missing, what I
    have. The first two are short and answer what is going on right now; the
    Shelf is the collection and goes underneath, where there is room for it
    (BIB-61).

    «Por comprar» is the name this system uses inside; a visitor is told «los
    que me faltan», which needs nothing explained (ADR-003).
--}}
<x-showcase.layout :meta="$meta">
    <header class="flex max-w-prose flex-col gap-5">
        <h1 class="text-4xl font-bold tracking-tight md:text-5xl">{{ config('app.name') }}</h1>
    </header>

    {{--
        The section of what is being read only exists when there is something in
        it, the same as the one below: a heading over nothing reads as a list
        that failed to load.

        These Books are on the Shelf too and are printed there again. This
        section says which ones I have open right now, and the one below says
        how many books there are — two different questions, and the second one
        answers wrong if a Book leaves the collection while it is being read.
    --}}
    @if ($beingRead->isNotEmpty())
        <section class="flex flex-col gap-8" data-test="being-read">
            <div class="flex flex-col gap-2 border-t border-divider pt-8">
                <p class="type-section-heading text-ink-muted" data-test="being-read-count">
                    {{ $beingRead->count() }} {{ $beingRead->count() === 1 ? 'libro' : 'libros' }}
                </p>

                <h2 class="text-2xl font-bold tracking-tight">Lo que estoy leyendo</h2>
            </div>

            <x-showcase.grid :books="$beingRead" />
        </section>
    @endif

    @if ($wanted->isNotEmpty())
        <section class="flex flex-col gap-8" data-test="wanted">
            <div class="flex flex-col gap-2 border-t border-divider pt-8">
                <p class="type-section-heading text-ink-muted" data-test="wanted-count">
                    {{ $wanted->count() }} {{ $wanted->count() === 1 ? 'libro' : 'libros' }}
                </p>

                <h2 class="text-2xl font-bold tracking-tight">Los que me faltan</h2>

                <p class="max-w-prose text-ink-muted">Los quiero y todavía no los tengo.</p>
            </div>

            <x-showcase.grid :books="$wanted" />
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
