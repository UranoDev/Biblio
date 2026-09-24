{{--
    The Books of one Category in the Showcase.

    The two sections of the front page come back here, with the same words: what
    is on the Shelf and what is missing. Putting them in one grid would show a
    book I do not own yet with nothing saying so, and that is half of what this
    face is for (ADR-003). Either one is left out when it has nothing — a
    heading over nothing reads as a list that failed to load.
--}}
<x-showcase.layout :meta="$meta" :category="$category">
    {{-- The name of the Category and nothing under it: how many there are is
         written on each of the two sections, and saying it up here as well
         would be the same number twice. --}}
    <header class="flex max-w-prose flex-col gap-4">
        <h1 class="text-3xl font-bold tracking-tight md:text-4xl">{{ $category->name }}</h1>
    </header>

    @if ($onShelf->isNotEmpty())
        <section class="flex flex-col gap-8" data-test="owned">
            <div class="flex flex-col gap-2 border-t border-divider pt-8">
                <p class="type-section-heading text-ink-muted" data-test="owned-count">
                    {{ $onShelf->count() }} {{ $onShelf->count() === 1 ? 'libro' : 'libros' }}
                </p>

                <h2 class="text-2xl font-bold tracking-tight">Los que tengo</h2>
            </div>

            <x-showcase.grid :books="$onShelf" />
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
</x-showcase.layout>
