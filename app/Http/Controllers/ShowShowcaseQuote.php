<?php

namespace App\Http\Controllers;

use App\Showcase\Meta;
use App\Showcase\Showcase;
use Illuminate\Contracts\View\View;

/**
 * The page of one Quote (BIB-31): the words big on the paper, the Book they
 * come from, and the image to download.
 *
 * It is not one more screen of the Showcase — it is the main tool for sharing,
 * and what somebody receiving the link opens.
 *
 * The identifier arrives as a number and not as a Quote, for the same reason
 * the Book page does it: implicit binding would hand over any Quote, including
 * the one of a Hidden Book, and remembering to check would be one more thing
 * every screen has to remember.
 */
final class ShowShowcaseQuote extends Controller
{
    public function __invoke(int $quote): View
    {
        $found = Showcase::quote($quote);

        return view('showcase.quote', [
            'quote' => $found,
            'book' => $found->book,
            'meta' => Meta::quote($found),
        ]);
    }
}
