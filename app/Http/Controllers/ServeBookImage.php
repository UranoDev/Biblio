<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the shareable card of a Book (BIB-50).
 *
 * It goes through the application and not straight at the disk, and that is the
 * point: a static file survives its Book being Hidden. The card of a Hidden
 * Book must stop answering the moment the switch is thrown, and only the
 * application knows that (ADR-003). It costs speed and it buys correctness.
 *
 * The address carries the signature of what the card was drawn from, and one
 * that does not match is not found: the page always prints the current address,
 * so an old one is a network asking for something that no longer exists.
 */
final class ServeBookImage extends Controller
{
    public function __invoke(Request $request, Book $book, string $fingerprint): StreamedResponse
    {
        if ($book->is_hidden && ! Auth::check()) {
            abort(404);
        }

        if ($book->share_image_fingerprint !== $fingerprint) {
            abort(404);
        }

        $path = $book->share_image_path;

        if ($path === null || ! Storage::disk()->exists($path)) {
            abort(404);
        }

        $response = Storage::disk()->response($path);

        // The address changes whenever the card does, so what is behind it
        // never changes: it can be kept for as long as whoever has it likes.
        // That is the whole reason the signature is in the address — the
        // networks cache `og:image` by URL and would otherwise have to be
        // argued with.
        $response->headers->set(
            'Cache-Control',
            $book->is_hidden ? 'private, no-store' : 'public, max-age=31536000, immutable',
        );

        $response->setEtag($fingerprint);
        $response->isNotModified($request);

        return $response;
    }
}
