<?php

namespace App\Http\Controllers;

use App\Showcase\Meta;
use App\Showcase\Showcase;
use Illuminate\Contracts\View\View;

/**
 * The page of one Book in the Showcase: the Record, the Description, the
 * Categories and every Quote of it.
 *
 * The identifier arrives as a number and not as a Book: implicit binding would
 * hand over any Book, Hidden included, and the check that it is on display
 * would be one more thing every screen has to remember. Going through
 * `Showcase` there is nothing to remember — a Hidden Book is not found.
 */
final class ShowShowcaseBook extends Controller
{
    public function __invoke(int $book): View
    {
        $found = Showcase::book($book);

        return view('showcase.book', [
            'book' => $found,
            'meta' => Meta::book($found),
        ]);
    }
}
