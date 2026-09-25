@use('App\Enums\Origin')
@use('App\Enums\Status')

{{--
    The page of one Book in the Showcase.

    What it shows is the global rule of ADR-003 and nothing else: the Record,
    the Cover, the Description, the mark of Reading, the Categories and every
    Quote. The read date, the Note and the «prestado a» are not left out of the
    layout — they are not in the page at all, which is what makes the rule
    checkable from the served HTML.
--}}
{{-- The sheet of a Book carries no global list of Categories (BIB-43): it
     names its own, further down, which are one to three and are the useful
     link from here. Repeating the whole navigation on every sheet printed
     fifty names above the title of the book. The way back to the whole library
     is the name of the site, at the foot. --}}
<x-showcase.layout :meta="$meta" :browse="false">
    <article class="flex flex-col gap-14">
        <header class="flex flex-col gap-10 md:flex-row md:items-start md:gap-14">
            <div class="w-36 shrink-0 md:w-56">
                {{-- The stamp of the Reading goes inside the box that leans, so
                     the word stays parallel to the Cover (BIB-38). --}}
                <div class="relative rotate-(--cover-tilt)">
                    {{-- The heading beside it says the title and the Authors,
                         which is everything the Cover would have announced. --}}
                    <x-cover :book="$book" size="full" decorative class="shadow-(--shadow-cover)" />

                </div>
            </div>

            <div class="flex min-w-0 flex-col gap-5">
                {{-- The Reading goes above the title, close to it, and out
                     here without the year: the read date does not leave the
                     back office (ADR-003). --}}
                <div class="flex flex-col gap-2">
                    @if ($book->status !== Status::Wanted)
                        <x-reading-mark :book="$book" showcase />
                    @endif

                    <h1 class="text-3xl leading-tight font-bold tracking-tight md:text-4xl">{{ $book->title }}</h1>
                </div>

                @if (filled($book->authors))
                    <p class="type-authors text-ink-muted" data-test="authors">
                        {{ implode(', ', $book->authors) }}
                    </p>
                @endif

                {{-- The Record reads as data, so it is set in the mono face:
                     the edition, the year and the ISBN are looked at, not
                     read. --}}
                @php
                    $record = array_filter([$book->publisher, $book->edition, $book->year, $book->isbn]);
                @endphp

                @if ($record !== [])
                    <p class="font-mono text-sm text-ink-muted" data-test="record">
                        {{ implode(' · ', $record) }}
                    </p>
                @endif

                @if ($book->status === Status::Wanted)
                    <span class="type-section-heading text-ink-muted" data-test="wanted-mark">
                        Todavía no lo tengo
                    </span>
                @endif
            </div>
        </header>

        @if ($book->hasDescription())
            <section class="flex max-w-prose flex-col gap-4" data-test="description">
                <p class="text-lg leading-relaxed whitespace-pre-line">{{ $book->description }}{{-- 
                    --}}@if ($book->description_origin === Origin::Ai)
                        {{-- Inline, al final del último renglón: un resumen generado
                             puede confundir dos libros del mismo título o inventar una
                             trama, y quien lo lee tiene que saber que no es la
                             contraportada (ADR-007). --}}
                        <x-ai-badge label="Descripción generada por IA" text="IA" data-test="description-by-ai" class="align-middle" />
                    @elseif ($book->description_origin === Origin::Translated)
                        {{-- La otra marca, y dice otra cosa: el contenido es el del
                             catálogo y el español lo escribió una máquina. Una
                             traducción puede errarle al matiz, no al hecho, y quien
                             lee debería poder notar la diferencia (BIB-45). --}}
                        <x-ai-badge
                            label="Descripción del catálogo, traducida al español por una IA"
                            text="Traducción de IA"
                            data-test="description-translated"
                            class="align-middle"
                        />
                    @endif</p>
            </section>
        @endif

        @if ($book->categories->isNotEmpty())
            <section class="flex flex-col gap-3" data-test="categories">
                <h2 class="type-section-heading text-ink-muted">Categorías</h2>

                {{-- Its own, and no others: what this Book is about is part
                     of what the Book is, and each one leads to the others of
                     the same subject (BIB-19). Every Category of a visible Book
                     has at least this Book in it, so none of these links lands
                     on a 404. --}}
                <ul class="flex flex-wrap items-baseline gap-x-5 gap-y-2 text-lg">
                    @foreach ($book->categories as $category)
                        <li>
                            <a href="{{ route('showcase.category', $category) }}" class="text-link">
                                {{ $category->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Every Quote of the Book, with no ceiling and nothing folded away:
             a Book with forty shows the forty (decisión 19). Each card is drawn
             at home —the Cover, the title and the Authors are right above— so
             what it shows is the words and the page (BIB-41). --}}
        @if ($book->quotes->isNotEmpty())
            <section class="flex flex-col gap-8" data-test="quotes">
                <h2 class="type-section-heading text-ink-muted">
                    {{ $book->quotes->count() === 1 ? 'Una cita' : $book->quotes->count().' citas' }}
                </h2>

                <div class="flex flex-col gap-10">
                    @foreach ($book->quotes as $quote)
                        {{-- La página de la Quote existe (BIB-31) y hasta hoy nada
                             llevaba a ella: la tarjeta se dibujaba sin puerta. --}}
                        <a href="{{ route('showcase.quote', $quote) }}" class="group">
                            <x-quote-card :quote="$quote" :standalone="false" />
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </article>
</x-showcase.layout>
