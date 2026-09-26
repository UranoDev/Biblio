<?php

namespace App\Sources;

use App\Domain\InvalidIsbn;
use App\Domain\Isbn;
use App\Enums;
use Illuminate\Support\Facades\Http;

/**
 * Reads a public Amazon wishlist by URL and returns one Candidate per book it
 * finds there.
 *
 * Amazon does not have a public API for wishlists. The page is fetched as HTML
 * and parsed with the patterns the wishlist format publishes for sharing and
 * SEO. The format can change without notice; when it does, the job that calls
 * this class will start returning an empty list and the user will notice that
 * no new books are arriving.
 *
 * Only items whose ASIN is a valid ISBN-10 are imported as books. Non-book
 * products on the list (toys, electronics, anything whose ASIN is not a
 * numeric ISBN-10) are silently skipped.
 */
final class AmazonWishlist
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /**
     * @return list<Candidate>
     *
     * @throws SourceUnavailable when Amazon does not answer or the request fails.
     */
    public function candidates(string $url): array
    {
        $html = $this->fetch($url);

        return $this->parse($html);
    }

    /**
     * @throws SourceUnavailable
     */
    private function fetch(string $url): string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'es-MX,es;q=0.9,en;q=0.8',
            ])
                ->timeout(30)
                ->get($url);
        } catch (\Throwable $e) {
            throw new SourceUnavailable('Amazon no respondió: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new SourceUnavailable("Amazon respondió HTTP {$response->status()}.");
        }

        return $response->body();
    }

    /**
     * @return list<Candidate>
     */
    private function parse(string $html): array
    {
        $candidates = [];

        foreach ($this->items($html) as $asin => $item) {
            $isbn = $this->isbn($asin);

            // Non-book ASINs (alphanumeric, or numeric but not a valid ISBN-10)
            // are not imported.
            if ($isbn === null) {
                continue;
            }

            $candidates[] = new Candidate(
                isbn: $isbn,
                title: $item['title'],
                authors: $item['authors'],
                publisher: null,
                year: null,
                edition: null,
                coverUrl: $item['coverUrl'],
                description: null,
                source: Enums\Source::Amazon,
                reference: $asin,
            );
        }

        return $candidates;
    }

    /**
     * Extracts ASIN → [title, authors, coverUrl] triples from the wishlist HTML.
     *
     * Wishlist item anchors carry `id="itemName_COLIID"` where COLIID is an
     * internal wishlist item ID — not the ASIN. The real ASIN lives in the
     * anchor's href under the `/dp/ASIN/` path segment. Both are needed: the
     * ASIN for the ISBN conversion and the COLIID to find the author credit and
     * the item image in the surrounding HTML.
     *
     * @return array<string, array{title: string, authors: list<string>, coverUrl: ?string}>
     */
    private function items(string $html): array
    {
        $items = [];

        // Step 1: collect every wishlist item anchor as a raw HTML string.
        if (preg_match_all(
            '/<a\b[^>]*\bid="itemName_[^"]+"[^>]*>.*?<\/a>/si',
            $html,
            $anchors
        ) === false) {
            return [];
        }

        foreach ($anchors[0] as $anchor) {
            // The ASIN is in the href: /dp/ASIN/ — not in the id.
            if (! preg_match('/\bhref="[^"]*\/dp\/([A-Z0-9]{10})\/[^"]*"/i', $anchor, $hrefMatch)) {
                continue;
            }

            $asin = $hrefMatch[1];

            // The wishlist item ID (coliid) is used to locate the author credit
            // and the item image.
            preg_match('/\bid="itemName_([^"]+)"/i', $anchor, $coliidMatch);
            $coliid = $coliidMatch[1] ?? '';

            $title = '';

            if (preg_match('/\btitle="([^"]*)"/i', $anchor, $titleMatch) && $titleMatch[1] !== '') {
                $title = html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5);
            } else {
                $title = html_entity_decode(strip_tags($anchor), ENT_QUOTES | ENT_HTML5);
            }

            $title = trim($title);

            if ($title !== '') {
                $items[$asin] = [
                    'title' => $title,
                    'authors' => $this->authors($html, $coliid),
                    'coverUrl' => $this->coverUrl($html, $coliid),
                ];
            }
        }

        return $items;
    }

    /**
     * Looks for an author credit near the item's wishlist entry in the HTML.
     *
     * Amazon wishlist pages include "by AUTHOR" text close to each item name
     * anchor. The lookup uses the wishlist item ID (coliid), which is what the
     * anchor's id attribute carries — not the ASIN.
     *
     * This extraction is best-effort: if the pattern is not found, the book is
     * still imported without author attribution.
     *
     * @return list<string>
     */
    private function authors(string $html, string $coliid): array
    {
        if ($coliid === '') {
            return [];
        }

        $pos = strpos($html, "itemName_{$coliid}");

        if ($pos === false) {
            return [];
        }

        $window = substr($html, $pos, 2000);

        if (preg_match(
            '/\bby\s+(?:<[^>]+>)*\s*([^<\n\r]{2,80}?)\s*(?:<\/a>|<\/span>|<br|<div|by\s)/i',
            $window,
            $m
        ) === 1) {
            $name = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5));

            if ($name !== '') {
                return [$name];
            }
        }

        return [];
    }

    /**
     * Extracts the cover image URL for a wishlist item, or null when none is
     * found.
     *
     * Amazon places an `<img id="itemImage_{coliid}">` element near each item
     * anchor. The src attribute holds the thumbnail URL; the size modifier
     * (e.g. `._SX300_SY300_QL70_`) is replaced with `._SL500_` to get a cover
     * at a resolution suitable for the Shelf without an extra round-trip.
     */
    private function coverUrl(string $html, string $coliid): ?string
    {
        if ($coliid === '') {
            return null;
        }

        // Find the element whose id matches this wishlist item. In the current
        // Amazon HTML this is a <div id="itemImage_COLIID"> that wraps the <img>;
        // older and test formats place the id directly on the <img>. Either way,
        // walk back to the opening tag and scan forward for the first <img src>.
        $pos = strpos($html, 'id="itemImage_' . $coliid . '"');

        if ($pos === false) {
            return null;
        }

        $tagStart = strrpos(substr($html, 0, $pos), '<');

        if ($tagStart === false) {
            return null;
        }

        $window = substr($html, $tagStart, 1200);

        if (preg_match('/<img\b[^>]*\bsrc="(https?:[^"]+)"/i', $window, $m) !== 1) {
            return null;
        }

        return $this->normalizeImageUrl($m[1]);
    }

    /**
     * Replaces Amazon's thumbnail size modifier with a 500 px version.
     *
     * Amazon image URLs carry one or more size tokens between dots
     * (e.g. `._SX200_SY200_QL70_`). Swapping that block for `._SL500_`
     * yields a 500 px image in the same request, without an extra redirect.
     */
    private function normalizeImageUrl(string $url): string
    {
        // Match the modifier block: one dot, underscore, uppercase letter,
        // any uppercase/digit/comma/underscore chars, trailing underscore, dot.
        return preg_replace('/\._[A-Z][A-Z0-9,_]*_(?=\.[a-z]+$)/i', '._SL500_', $url) ?? $url;
    }

    /**
     * Converts a numeric ASIN to an ISBN-13, or returns null when the ASIN is
     * not a book ISBN-10 (alphanumeric ASINs, wrong check digit, etc.).
     */
    private function isbn(string $asin): ?Isbn
    {
        if (! preg_match('/^\d{10}$/', $asin)) {
            return null;
        }

        try {
            return Isbn::from($asin);
        } catch (InvalidIsbn) {
            return null;
        }
    }
}
