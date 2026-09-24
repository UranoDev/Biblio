{{--
    The Showcase, at the root of the site.

    With the collection indexed, most people do not come in through here: they
    land on a Quote from a search and walk back. So this page says whose library
    this is and lets it be walked, instead of introducing a product.

    «Por comprar» is the name this system uses inside; a visitor is told «los
    que me faltan», which needs nothing explained (ADR-003).
--}}
<x-showcase.layout>
    <header class="flex max-w-prose flex-col gap-5">
        <h1 class="text-4xl font-bold tracking-tight md:text-5xl">{{ config('app.name') }}</h1>

        <p class="text-lg leading-relaxed text-ink-muted">
            Los libros que tengo, los que quiero tener y las citas que guardé de ellos.
        </p>
    </header>

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
            <x-showcase.grid :books="$onShelf" />
        @endif
    </section>

    {{--
        The section of what is Wanted only exists when there is something in it.
        An empty one would be a heading over nothing, and a visitor reads it as
        a list that failed to load.
    --}}
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
</x-showcase.layout>
