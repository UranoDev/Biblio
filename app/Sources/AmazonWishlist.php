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
            if ($isbn === null && $item['title'] === '') {
                continue;
            }

            $candidates[] = new Candidate(
                isbn: $isbn,
                title: $item['title'],
                authors: $item['authors'],
                publisher: null,
                year: null,
                edition: null,
                coverUrl: null,
                description: null,
                source: Enums\Source::Amazon,
                reference: $asin,
            );
        }

        return $candidates;
    }

    /**
     * Extracts ASIN → [title, authors] pairs from the wishlist HTML.
     *
     * The anchor tags with `id="itemName_ASIN"` are the canonical item links
     * that Amazon publishes for sharing; they are present on every public
     * wishlist page. Author lines follow the pattern "by AUTHOR" inside a
     * span near the item name.
     *
     * @return array<string, array{title: string, authors: list<string>}>
     */
    private function items(string $html): array
    {
        $items = [];

        // Each wishlist item anchor: id="itemName_ASIN" title="Book Title"
        if (preg_match_all(
            '/<a\b[^>]*\bid="itemName_([A-Z0-9]{10})"[^>]*(?:\btitle="([^"]*)")?[^>]*>(.*?)<\/a>/si',
            $html,
            $matches,
            PREG_SET_ORDER
        ) !== false) {
            foreach ($matches as $m) {
                $asin = $m[1];

                $title = $m[2] !== ''
                    ? html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5)
                    : html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5);

                $title = trim($title);

                if ($title !== '') {
                    $items[$asin] = [
                        'title' => $title,
                        'authors' => $this->authors($html, $asin),
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * Looks for an author credit near the item's ASIN in the surrounding HTML.
     *
     * Amazon wishlist pages include "by AUTHOR" text close to each item name.
     * This extraction is best-effort: if the pattern is not found, the book is
     * still imported without author attribution.
     *
     * @return list<string>
     */
    private function authors(string $html, string $asin): array
    {
        // Look for the block that contains this ASIN's item name, then find
        // an author credit within the next ~2000 characters.
        $pos = strpos($html, "itemName_{$asin}");

        if ($pos === false) {
            return [];
        }

        $window = substr($html, $pos, 2000);

        // "by AUTHOR" pattern, where AUTHOR may be a plain span or an anchor.
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
