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

        if ($ten === null) {
            return null;
        }

        $address = self::address($this->url, $ten);

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

    /** Where the large image of that ISBN-10 is served. */
    public static function address(string $url, string $ten): string
    {
        return rtrim($url, '/')."/images/P/{$ten}.01.LZZZZZZZ.jpg";
    }
}
