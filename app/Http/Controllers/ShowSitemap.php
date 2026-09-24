<?php

namespace App\Http\Controllers;

use App\Showcase\Sitemap;
use Illuminate\Http\Response;

/**
 * Serves `sitemap.xml` (BIB-37).
 *
 * It is generated on every request and not written to a file: two hundred Books
 * and their Categories are one query each, and a file would have to be rebuilt
 * by whoever hides a Book — which is the one moment nobody is thinking about
 * the sitemap.
 */
final class ShowSitemap extends Controller
{
    public function __invoke(): Response
    {
        return response()
            ->view('sitemap', ['entries' => Sitemap::entries()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
