<?php

namespace App\Quotes;

use RuntimeException;

/**
 * The two typefaces of the direction, written into the page the renderer draws
 * (ADR-009).
 *
 * They are inlined as data and not linked, and that is the whole reason this
 * class exists: if the renderer goes looking for them over the network and does
 * not get them, the image comes out in a fallback face and **nobody finds out**
 * — it does not fail, it just looks like somebody else's design. Inlined there
 * is no request to lose.
 *
 * The files live in `resources/fonts` and not in `public/build`, where Vite
 * leaves its own copies. The build is an artifact: it is not in the repository,
 * its names carry a hash that changes on every build, and a queue worker that
 * draws an image is not the place to find out that nobody ran `npm run build`.
 * These five are part of the application, like the stylesheet is.
 *
 * Both faces are Open Font License, which is what makes carrying them here a
 * decision about deployment and not about licensing.
 */
final class Fonts
{
    /**
     * The files, by family and weight. They are the same weights the site
     * serves, so the image is set in what the page is set in — including the
     * bold of a Book title, which is synthesized from 600 in the browser and in
     * the renderer alike.
     *
     * @var array<string, array<int, string>>
     */
    private const FILES = [
        'Instrument Sans' => [
            400 => 'instrument-sans-400.woff2',
            500 => 'instrument-sans-500.woff2',
            600 => 'instrument-sans-600.woff2',
        ],
        'IBM Plex Mono' => [
            400 => 'ibm-plex-mono-400.woff2',
            500 => 'ibm-plex-mono-500.woff2',
        ],
    ];

    /**
     * The `@font-face` rules, each one carrying its own file.
     *
     * `font-display` is `block` and not `swap`: a renderer that takes the
     * picture while the fallback is on screen has produced exactly the image
     * this class exists to avoid. There is nothing to wait for anyway — the
     * bytes are right there in the rule.
     *
     * @throws RuntimeException when a file is missing, so that the image is
     *                          never drawn quietly in another typeface
     */
    public static function faces(): string
    {
        $rules = [];

        foreach (self::FILES as $family => $weights) {
            foreach ($weights as $weight => $file) {
                $bytes = self::encoded($file);

                $rules[] = <<<CSS
                    @font-face {
                        font-family: "{$family}";
                        font-style: normal;
                        font-weight: {$weight};
                        font-display: block;
                        src: url(data:font/woff2;base64,{$bytes}) format("woff2");
                    }
                    CSS;
            }
        }

        return implode("\n", $rules);
    }

    /** Where the files are, so a test can say out loud which ones it expects. */
    public static function path(string $file): string
    {
        return resource_path('fonts/'.$file);
    }

    /**
     * @return list<string>
     */
    public static function files(): array
    {
        return array_merge(...array_map(array_values(...), array_values(self::FILES)));
    }

    /**
     * @throws RuntimeException
     */
    private static function encoded(string $file): string
    {
        $path = self::path($file);

        $bytes = is_readable($path) ? file_get_contents($path) : false;

        if ($bytes === false || $bytes === '') {
            throw new RuntimeException(
                "The typeface {$file} is missing from resources/fonts; "
                .'the image would be drawn in a fallback face and nothing would say so.'
            );
        }

        return base64_encode($bytes);
    }
}
