<?php

namespace App\Quotes;

use App\Enums\ShareFormat;
use App\Models\Quote;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Draws the two images of a Quote and keeps them (ADR-009).
 *
 * The renderer is a generator and not a server: what it hands back is drawn
 * here into files of our own, on the configured disk (ADR-004), and served by a
 * route of the application. That is what makes the decision reversible — the
 * day the service closes, the images already made keep working and only who
 * draws the next one changes.
 *
 * Both formats are written in one go, or neither is. Half a Quote — the wide
 * one from today and the tall one from a month ago — is the kind of thing
 * nobody notices until it is shared.
 */
final readonly class ShareImages
{
    public function __construct(
        private ImageRenderer $renderer,
        private string $directory,
    ) {}

    /** Whether there is anything to draw with at all. */
    public function available(): bool
    {
        return $this->renderer->available();
    }

    /**
     * Draws the Quote in both formats and stores them, replacing whatever was
     * there.
     *
     * @throws OutOfCredits|RenderFailed
     */
    public function renderFor(Quote $quote): void
    {
        $fingerprint = $quote->imagesFingerprint();

        $drawn = [];

        foreach (ShareFormat::cases() as $format) {
            $drawn[$format->value] = $this->renderer->render(
                ShareCard::of($quote, $format),
                $format,
            );
        }

        $previous = $quote->imageFiles();

        $paths = [];

        foreach ($drawn as $format => $bytes) {
            $paths[$format] = $this->write($quote, ShareFormat::from($format), $fingerprint, $bytes);
        }

        $quote->imagesStored(
            card: $paths[ShareFormat::Card->value],
            story: $paths[ShareFormat::Story->value],
            fingerprint: $fingerprint,
        );

        // The old files go once the new ones are written and written down, the
        // same order the Covers are replaced in: a crash in between leaves two
        // files on the disk, which costs space, and the other order leaves a
        // Quote pointing at nothing.
        foreach ($previous as $file) {
            Storage::disk()->delete($file);
        }
    }

    /**
     * Writes one file. Its name carries the fingerprint, so a render of the
     * same Quote never lands on the name of the one it is replacing — the disk
     * may be a bucket in front of a CDN the day S3 is turned on.
     */
    private function write(Quote $quote, ShareFormat $format, string $fingerprint, string $bytes): string
    {
        $path = sprintf(
            '%s/%d/%s-%s-%s.png',
            $this->directory,
            $quote->getKey(),
            $format->value,
            $fingerprint,
            Str::lower((string) Str::ulid()),
        );

        Storage::disk()->put($path, $bytes);

        return $path;
    }
}
