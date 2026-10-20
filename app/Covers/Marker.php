<?php

namespace App\Covers;

/**
 * The image a Source hands back when it has no Cover for the ISBN.
 *
 * It is not a failed download: it arrives with status 200, it is a valid image
 * and it weighs a plausible amount, so nothing upstream stops it. What gives it
 * away is that it is always the very same file, which is why it is recognised
 * by the hash of its bytes.
 *
 * Adding the marker of another catalog is one line in the list.
 */
final readonly class Marker
{
    /**
     * The known markers, by the sha256 of their bytes. Every one of them was
     * downloaded from the live catalog on 2026-09-23 and is recorded in
     * `tests/Fixtures/covers`, where the tests read it from.
     *
     * @var array<string, string>
     */
    private const KNOWN = [
        // Google Books, `zoom=0` and `zoom=3`: the 575×750 sheet that reads
        // «image not available». Byte for byte the same file for every volume.
        '3efa8c43e5b4348f303a528c81adf435f0111ea752fe9f0f6241478b60987fa6' => 'Google Books, la hoja que dice «image not available», 575×750',
        // Google Books, `zoom=1` —the `thumbnail` link the Record carries—: a
        // flat red tile. This one comes in the shape of the book, so there is
        // one file per aspect ratio and the list only covers the ones seen.
        'f0e7c08bfba3982cc518c739984e902e6a957878a7aa41569a18501e20bf99b0' => 'Google Books, el mosaico rojo de 128×198',
        'e3f8c414b288cbdf4e6d1e00eb6d3826157d10a5b5628b9318f726ea490eca12' => 'Google Books, el mosaico rojo de 128×170',
        // Open Library, ISBN with no Cover: a 1×1 transparent pixel, 43 bytes.
        // The minimum side rejects it too; it is listed so the log says which
        // of the two it was.
        '546c5cf136073615afda5cab173feff341171a26a848cf7ce09bb8bd8b07ce89' => 'Open Library, el pixel de 1×1',
        // Internet Archive, which is where Open Library serves its Covers from:
        // when the file is gone it hands back its own logo —a white temple on
        // black— with a 200. Landscape, so the minimum side refuses it too; it
        // is listed so the log says which of the two it was, and so a bigger
        // variant does not slip through.
        '542210da157d122a7dcbd39438d61e3b8570b6b03787a5c7ee02405a38d5e389' => 'Internet Archive, el templete blanco sobre negro, 320×220',
        // Amazon, ISBN with no image: its own 1×1 GIF, 43 bytes, not the same
        // file as Open Library's. AmazonCovers already refuses it before giving
        // the address out; it is listed so a download of it says whose it was.
        'cf4724b2f736ed1a0ae6bc28f1ead963d9cd2c1fd87b6ef32e7799fc1c5c8bda' => 'Amazon, el pixel de 1×1',
    ];

    /**
     * Which marker this image is, or null when it is not one of the known ones.
     */
    public static function of(string $binary): ?string
    {
        return self::KNOWN[hash('sha256', $binary)] ?? null;
    }
}
