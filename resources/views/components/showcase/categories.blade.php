@props(['categories', 'current' => null])

{{--
    The navigation by Category of the Showcase: the names, each with how many
    visible Books it holds.

    It closes the page and it is not printed before the collection (BIB-43). The
    list has no ceiling —it grows with the library, and the instruction given to
    the AI is to reuse before inventing, so it grows slowly but it does not stop
    growing—, and a list with no ceiling cannot be printed whole above what the
    page is about. At the foot it wraps over as many lines as it needs, because
    down there the space competes with nothing.

    It is a list of links and nothing else — no menu that opens, no script. This
    is the surface used standing up in a bookshop, on a phone, and a row of
    names that wraps needs no width to work; anything that has to open first can
    fail to open. Only Categories with a visible Book are named, which
    `App\Showcase\Showcase` decides, not this file (ADR-003).

    The way back to the whole library is not an item here: the name of the site,
    right under this list, is the link that leads there on every page.

    There is no accent colour in this system —the accent are the Covers—, so
    where you are is said with weight and with the ink, not with a colour.
--}}
@if ($categories->isNotEmpty())
    <nav aria-label="Categorías" data-test="category-nav">
        <ul class="flex flex-wrap items-baseline gap-x-5 gap-y-2">
            @foreach ($categories as $category)
                <li>
                    <a
                        href="{{ route('showcase.category', $category) }}"
                        @class([
                            'type-section-heading',
                            'font-bold text-ink' => $current?->is($category),
                            'text-link' => ! $current?->is($category),
                        ])
                        @if ($current?->is($category)) aria-current="page" @endif
                        data-test="category-nav-{{ $category->slug }}"
                    >{{ $category->name }} <span class="text-ink-muted">{{ $category->books_count }}</span></a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
