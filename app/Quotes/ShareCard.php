<?php

namespace App\Quotes;

use App\Enums\CoverSize;
use App\Enums\ShareFormat;
use App\Models\Quote;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Throwable;

/**
 * The page the renderer is handed: the card of a Quote, standing on its own
 * (ADR-009).
 *
 * It is the same card the Showcase draws —the same Blade component, the same
 * stylesheet— and that is the decision the ADR is about: there is no second
 * design for the networks, so there are not two of them to drift apart.
 *
 * What it adds is everything a page normally asks the network for: the
 * stylesheet written in, the typefaces written in as data, and the Cover
 * written in as data. The renderer runs somewhere else and it may not be able
 * to reach this site at all — the Covers are served by a route of the
 * application, behind a check of visibility, and in development the site is
 * `localhost`. A page that has nothing to fetch cannot come out half drawn.
 */
final readonly class ShareCard
{
    /**
     * The HTML of one Quote in one format.
     */
    public static function of(Quote $quote, ShareFormat $format): string
    {
        return view('quotes.share', [
            'quote' => $quote,
            'format' => $format,
            'styles' => self::styles(),
            'cover' => self::cover($quote),
        ])->render();
    }

    /**
     * The stylesheet, inlined: the typefaces first and the compiled sheet after
     * them.
     *
     * The compiled sheet is a build artifact, and without a build it comes back
     * empty. That is the one degradation allowed here, because it is the loud
     * one: an unstyled image is wrong at a glance. The typefaces are the
     * opposite —a fallback face is a silent wrong— so they never depend on the
     * build and a missing file throws.
     */
    private static function styles(): string
    {
        return Fonts::faces()."\n".self::compiled();
    }

    private static function compiled(): string
    {
        try {
            return (string) Vite::content('resources/css/app.css');
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * The stored Cover as data, or null when the Book shows the generated piece
     * — that one is drawn in the page itself and has nothing to fetch (ADR-008).
     */
    private static function cover(Quote $quote): ?string
    {
        $path = $quote->book->coverPathFor(CoverSize::Full);

        if ($path === null || ! Storage::disk()->exists($path)) {
            return null;
        }

        $bytes = Storage::disk()->get($path);

        if ($bytes === null || $bytes === '') {
            return null;
        }

        $type = Storage::disk()->mimeType($path);

        return 'data:'.($type === false ? 'image/jpeg' : $type).';base64,'.base64_encode($bytes);
    }
}
