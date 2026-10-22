<?php

namespace App\Http\Controllers;

use App\Models\CoverDraft;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the photograph of a CoverDraft (BIB-74), behind the login: the disk
 * is private, the same as for the Covers (BIB-32), and a draft is not on the
 * Showcase.
 */
final class ServeCoverDraftPhoto extends Controller
{
    public function __invoke(CoverDraft $draft): StreamedResponse
    {
        if ($draft->photo_path === null || ! Storage::disk()->exists($draft->photo_path)) {
            abort(404);
        }

        $response = Storage::disk()->response($draft->photo_path);

        $response->headers->set('Cache-Control', 'private, max-age=3600');

        return $response;
    }
}
