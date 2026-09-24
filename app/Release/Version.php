<?php

namespace App\Release;

/**
 * Which version is running, read off the top of `CHANGELOG.md`.
 *
 * The deploy copies files; it does not clone, so there is no `.git` on the
 * server and the application cannot work its version out on its own. Somebody
 * has to write it down, and the writing has to travel through the repository
 * to arrive with the deploy.
 *
 * `scripts/release.ps1` already writes it: it regenerates the changelog with
 * the new heading and commits it inside the release commit. So the changelog is
 * the one source there is, and there is no second one that could drift out of
 * step with it — if the heading says a date, that is exactly what is deployed.
 *
 * With no changelog the answer is «nothing has been cut yet», which is true and
 * is not the same as a `0.0.0` that would read like a version.
 */
final class Version
{
    /** The heading of a version, as `changelog.ps1` writes it: `## [2026.09.24] - 2026 sep 24`. */
    private const HEADING = '/^##\s+\[([^\]]+)\]/m';

    /** The bucket for what is resolved but not cut yet. It is not a version. */
    private const UNRELEASED = 'unreleased';

    private static bool $read = false;

    private static ?string $version = null;

    /**
     * The version deployed, or null while none has been cut.
     *
     * It is read once and kept: the version is printed twice on every page —the
     * sidebar and the `<meta>`— and neither of them is worth a second open of
     * the file. It is not put in the application cache on purpose: the store is
     * the database, and a query per request to save a four-kilobyte read is the
     * worse trade of the two.
     */
    public static function current(): ?string
    {
        if (self::$read) {
            return self::$version;
        }

        self::$read = true;
        self::$version = self::readFromChangelog();

        return self::$version;
    }

    /** What the back office prints, under the name of the library. */
    public static function label(): string
    {
        $version = self::current();

        return $version === null
            ? 'Sin versión cortada'
            : "V. {$version}";
    }

    /** Reset between tests; nothing in the application calls it. */
    public static function forget(): void
    {
        self::$read = false;
        self::$version = null;
    }

    /**
     * The most recent heading of the changelog that names a version.
     *
     * `[Unreleased]` is skipped rather than shown: the generator writes it for
     * whatever is resolved after the last tag, and that is the state of the
     * backlog, not of what is running.
     */
    private static function readFromChangelog(): ?string
    {
        $file = (string) config('release.changelog', base_path('CHANGELOG.md'));

        if (! is_file($file)) {
            return null;
        }

        $contents = @file_get_contents($file);

        if ($contents === false) {
            return null;
        }

        preg_match_all(self::HEADING, $contents, $headings);

        foreach ($headings[1] as $heading) {
            $version = trim($heading);

            if ($version !== '' && mb_strtolower($version) !== self::UNRELEASED) {
                return $version;
            }
        }

        return null;
    }
}
