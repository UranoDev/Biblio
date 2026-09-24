@props(['categories', 'current' => null, 'root' => false])

{{--
    The navigation of the Showcase: the Categories, and the way back to the
    whole library.

    It is a list of links and nothing else — no menu that opens, no script. This
    is the surface used standing up in a bookshop, on a phone, and a row of
    names that wraps needs no width to work; anything that has to open first can
    fail to open. Only Categories with a visible Book are named, which
    `App\Showcase\Showcase` decides, not this file (ADR-003).

    The way back is not listed on the front page, because that is where it
    leads. On the other pages it is the first item, and it is the only way back
    they have: written again inside the page it would be the same words twice.

    There is no accent colour in this system —the accent are the Covers—, so
    where you are is said with weight and with the ink, not with a colour.
--}}
@if (! $root || $categories->isNotEmpty())
    <nav aria-label="Categorías" data-test="category-nav">
        <ul class="flex flex-wrap items-baseline gap-x-5 gap-y-2">
            @unless ($root)
                <li>
                    <a
                        href="{{ route('showcase') }}"
                        class="type-section-heading text-link"
                        data-test="category-nav-all"
                    >Toda la biblioteca</a>
                </li>
            @endunless

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
