<?php

namespace App\Sources;

use App\Domain\Isbn;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The images Amazon serves for a book by its ten-digit ISBN.
 *
 * It is the Cover source of last resort, for the editions the two catalogs know
 * without an image of. Checked on 2026-10-19 against real books: of three
 * Mexican editions Google Books answered without a Cover, Amazon had two
 * («La estrella de Ratner», 9786070725364, at 295×500).
 *
 * Three things to know about it:
 *
 * - It is addressed by ISBN-10, so the ones starting with 979 —which have no
 *   ten-digit form— never get asked.
 * - It is not an API: it is the address of the image itself, and it is not
 *   documented. If Amazon changes it, this starts answering nothing and the
 *   Books keep their generated Cover, which is where they were before.
 * - It always answers 200. When it has no image it hands back a transparent
 *   1×1 GIF of 43 bytes, so the image is looked at here, before giving the
 *   address out: an address that is known to be the empty pixel would be a
 *   download that fails on purpose and a mark on the Book.
 */
final class AmazonCovers implements CoverSource
{
    /** Below this, what came back is the empty pixel and not a Cover. */
    private const MIN_SIDE = 100;

    public function __construct(
        private readonly string $url,
        private readonly int $timeout,
    ) {}

    public function coverUrl(Isbn $isbn): ?string
    {
        $ten = $isbn->asTen();

        return $ten === null ? null : $this->checked(self::address($this->url, $ten));
    }

    /**
     * The Cover of the product an Amazon link points at.
     *
     * It is the way in for the editions with an ISBN that starts with 979: they
     * have no ten-digit form, and the code Amazon gives them can only be read
     * off the page of the product. Searching Amazon for it from the server gets
     * a captcha (amazon.com.mx) or a 503 (amazon.com) —checked on 2026-10-20—
     * so the owner, who did find it in the browser, pastes the link.
     */
    public function coverUrlForLink(string $link): ?string
    {
        $asin = self::asinOf($link);

        return $asin === null ? null : $this->checked(self::address($this->url, $asin));
    }

    /**
     * The product code of an Amazon link, or of the code pasted on its own.
     *
     * The links Amazon hands out carry it after `/dp/`, `/gp/product/` or
     * `/product/`, followed by a slash, a `?` or nothing. Ten characters, letters
     * and digits.
     */
    public static function asinOf(string $link): ?string
    {
        $link = trim($link);

        if (preg_match('/^[A-Z0-9]{10}$/i', $link) === 1) {
            return strtoupper($link);
        }

        if (preg_match('#/(?:dp|gp/product|product)/([A-Z0-9]{10})(?:[/?\#]|$)#i', $link, $match) === 1) {
            return strtoupper($match[1]);
        }

        return null;
    }

    /** The address, when what it serves is an image the size of a Cover. */
    private function checked(string $address): ?string
    {
        try {
            $response = Http::timeout($this->timeout)->get($address);
        } catch (ConnectionException) {
            // A Cover source is never the reason a Book cannot be registered:
            // not answering is the same as not having one.
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $size = @getimagesizefromstring($response->body());

        if ($size === false || min($size[0], $size[1]) < self::MIN_SIDE) {
            return null;
        }

        return $address;
    }

    /**
     * Where the large image of a product is served: by its ASIN, which for a
     * book with an ISBN starting with 978 is its ISBN-10.
     */
    public static function address(string $url, string $code): string
    {
        return rtrim($url, '/')."/images/P/{$code}.01.LZZZZZZZ.jpg";
    }
}
