<?php

namespace App\Http\Controllers;

use App\Showcase\Meta;
use App\Showcase\Showcase;
use Illuminate\Contracts\View\View;

/**
 * The Showcase: the public face, without a session and read only (ADR-003).
 *
 * It is a controller and a Blade view, and not a Livewire component, for two
 * reasons that are really one. A Livewire component answers POSTs to
 * `livewire/update` naming its own public methods, and this is the surface that
 * has to stay unwritable by a request put together by hand. And it serializes
 * the models it holds into the payload it prints, which would put the Note and
 * the «prestado a» of every Book into the HTML served to anybody — and those
 * are the two things that must not be in it.
 */
final class ShowShowcase extends Controller
{
    public function __invoke(): View
    {
        $onShelf = Showcase::onShelf();
        $wanted = Showcase::wanted();

        return view('showcase.index', [
            'onShelf' => $onShelf,
            'wanted' => $wanted,
            'meta' => Meta::showcase(
                books: $onShelf->count() + $wanted->count(),
                quotes: Showcase::quoteCount(),
            ),
        ]);
    }
}
