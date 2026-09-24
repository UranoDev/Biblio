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
<x-showcase.layout :title="$book->title">
    <a href="{{ route('showcase') }}" class="type-section-heading text-link" data-test="back">
        Toda la biblioteca
    </a>

    <article class="flex flex-col gap-14">
        <header class="flex flex-col gap-10 md:flex-row md:items-start md:gap-14">
            <div class="w-36 shrink-0 md:w-56">
                {{-- The heading beside it says the title and the Authors,
                     which is everything the Cover would have announced. --}}
                <x-cover
                    :book="$book"
                    size="full"
                    decorative
                    class="rotate-(--cover-tilt) shadow-(--shadow-cover)"
                />
            </div>

            <div class="flex min-w-0 flex-col gap-5">
                <h1 class="text-3xl leading-tight font-bold tracking-tight md:text-4xl">{{ $book->title }}</h1>

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

                <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                    <x-reading-mark :status="$book->readingStatus()" showcase />

                    @if ($book->status === Status::Wanted)
                        <span class="type-section-heading text-ink-muted" data-test="wanted-mark">
                            Todavía no lo tengo
                        </span>
                    @endif
                </div>
            </div>
        </header>

        @if ($book->hasDescription())
            <section class="flex max-w-prose flex-col gap-4" data-test="description">
                <p class="text-lg leading-relaxed whitespace-pre-line">{{ $book->description }}</p>

                {{-- A generated summary can mix up two books of the same title
                     or invent a plot, so whoever reads it is told that it is
                     not the back cover (ADR-007). --}}
                @if ($book->description_origin === Origin::Ai)
                    <p class="type-section-heading text-ink-muted" data-test="description-by-ai">
                        Descripción escrita por una inteligencia artificial; puede equivocarse
                    </p>
                @endif
            </section>
        @endif

        @if ($book->categories->isNotEmpty())
            <section class="flex flex-col gap-3" data-test="categories">
                <h2 class="type-section-heading text-ink-muted">Categorías</h2>

                <p class="text-lg">{{ $book->categories->pluck('name')->join(' · ') }}</p>
            </section>
        @endif

        {{-- Every Quote of the Book, with no ceiling and nothing folded away:
             a Book with forty shows the forty (decisión 19). --}}
        @if ($book->quotes->isNotEmpty())
            <section class="flex flex-col gap-8" data-test="quotes">
                <h2 class="type-section-heading text-ink-muted">
                    {{ $book->quotes->count() === 1 ? 'Una cita' : $book->quotes->count().' citas' }}
                </h2>

                <div class="flex flex-col gap-10">
                    @foreach ($book->quotes as $quote)
                        <x-quote-card :quote="$quote" />
                    @endforeach
                </div>
            </section>
        @endif
    </article>
</x-showcase.layout>
