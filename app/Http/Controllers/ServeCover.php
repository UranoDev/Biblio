<?php

namespace App\Http\Controllers;

use App\Enums\CoverSize;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the stored image of a Cover.
 *
 * The Covers live on the disk `FILESYSTEM_DISK` names and that disk is private
 * (ADR-004), so its own URL is not an address anybody can open: `/storage/…`
 * goes out unsigned and the framework answers 403 —404 in production— to every
 * single one of them (BIB-32). The application serves them itself instead.
 *
 * Going through the application is also the only way the Hidden switch can
 * reach the image: a Book taken out of the Showcase takes its Cover with it
 * (ADR-003), and a URL pointing straight at the disk —a public bucket the day
 * S3 gets turned on— would have no way of knowing that.
 */
final class ServeCover extends Controller
{
    public function __invoke(Request $request, Book $book, CoverSize $size): StreamedResponse
    {
        if ($book->is_hidden && ! Auth::check()) {
            abort(404);
        }

        $path = $book->coverPathFor($size);

        if ($path === null || ! Storage::disk()->exists($path)) {
            abort(404);
        }

        $response = Storage::disk()->response($path);

        // The address of a Cover does not change when the Cover does: it is
        // built out of the Book and the size, and the file behind it is
        // replaced by uploading another one. So the browser is told to ask
        // every time, and the name of the stored file —a fresh ULID on every
        // store— is what tells it whether what it has is still good.
        $response->setEtag(md5($path));
        $response->headers->set(
            'Cache-Control',
            ($book->is_hidden ? 'private' : 'public').', no-cache',
        );

        $response->isNotModified($request);

        return $response;
    }
}
