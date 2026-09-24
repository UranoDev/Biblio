<?php

namespace App\Http\Controllers;

use App\Enums\ShareFormat;
use App\Showcase\Showcase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hands over the vertical image of a Quote as a file (ADR-009).
 *
 * It exists because Instagram reads no tags: there a link brings nothing, and
 * the image has to be uploaded by hand. So it goes in the proportion of a
 * story, and it arrives with a name that says which Quote it is — the file
 * lands in a folder next to twenty others, and an identifier says nothing
 * there.
 *
 * There is no address for an image that is not drawn yet: the page says so in
 * words instead of offering a button that hands over a broken file.
 */
final class DownloadQuoteImage extends Controller
{
    public function __invoke(int $quote): StreamedResponse
    {
        $found = Showcase::quote($quote);

        $path = $found->imagePathFor(ShareFormat::downloaded());

        if ($path === null || ! Storage::disk()->exists($path)) {
            abort(404);
        }

        return Storage::disk()->download($path, $found->downloadName());
    }
}
