<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Showcase\Meta;
use App\Showcase\Showcase;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The Books of one Category in the Showcase: the way the collection is walked
 * from outside.
 *
 * The slug arrives as a string and not as a Category, for the same reason the
 * page of a Book takes a number: implicit binding would hand over any Category,
 * including one whose Books are all Hidden, and the check would be one more
 * thing to remember. `Showcase::category()` answers with nothing for a Category
 * a visitor may not reach, and nothing is a 404.
 */
final class ShowShowcaseCategory extends Controller
{
    public function __invoke(string $category): View|RedirectResponse
    {
        $found = Showcase::category($category);

        if (! $found instanceof Category) {
            abort(404);
        }

        // The address answered to before a rename or a merge lands on the
        // Category that holds the Books now (BIB-17). It is permanent and says
        // so: these addresses get shared and indexed, and a 302 would leave the
        // old one in the index forever.
        if ($found->slug !== $category) {
            return redirect()->route('showcase.category', $found, 301);
        }

        $onShelf = Showcase::onShelf($found);
        $wanted = Showcase::wanted($found);

        return view('showcase.category', [
            'category' => $found,
            'onShelf' => $onShelf,
            'wanted' => $wanted,
            'meta' => Meta::category($found, $onShelf->count() + $wanted->count()),
        ]);
    }
}
