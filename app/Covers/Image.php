<?php

namespace App\Covers;

/**
 * The image of a Cover, already validated, with the little that needs to be
 * known about it: whether it can be read, what format it comes in and how to
 * get a scaled-down version out of it.
 *
 * It is the only place in the project that talks to GD.
 */
final readonly class Image
{
    /** The accepted formats, with the extension they are stored with. */
    private const FORMATS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_AVIF => 'avif',
    ];

    /** The grid the flatness measurement samples the image on. */
    private const SAMPLES = 32;

    /** How far from the middle color a sample can be and still count as it. */
    private const FLAT_TOLERANCE = 12;

    /** The share of samples that has to be that color for it to be flat. */
    private const FLAT_SHARE = 0.90;

    /**
     * How many colors the image can be made of and still be flat.
     *
     * The markers are drawings: a background, the lettering over it and the
     * odd blurred edge. Measured on 2026-09-24 they are made of between 1 and
     * 9 colors, while a dozen real Covers —the most minimal ones among them,
     * the white sheet with the title and nothing else— do not go below 20.
     */
    private const FLAT_COLORS = 12;

    private function __construct(
        public string $binary,
        private int $format,
        public int $width,
        public int $height,
    ) {}

    /**
     * @throws InvalidImage
     */
    public static function from(string $binary): self
    {
        $measurements = @getimagesizefromstring($binary);

        if ($measurements === false) {
            throw InvalidImage::notAnImage();
        }

        [$width, $height] = $measurements;
        $format = $measurements[2];

        if (! array_key_exists($format, self::FORMATS)) {
            throw InvalidImage::unsupportedFormat((string) image_type_to_mime_type($format));
        }

        if ($width < 1 || $height < 1) {
            throw InvalidImage::notAnImage();
        }

        return new self($binary, $format, $width, $height);
    }

    /** The extension the original is stored with, without the dot. */
    public function extension(): string
    {
        return self::FORMATS[$this->format];
    }

    /**
     * The short side, which is the one that decides whether the image is big
     * enough to be a Cover: a thumbnail stretched across the grid looks
     * pixelated no matter how tall it is.
     */
    public function shortestSide(): int
    {
        return min($this->width, $this->height);
    }

    /**
     * Whether the image is one of those a catalog draws in place of a Cover it
     * does not have: a flat color across almost its whole surface, made of the
     * handful of colors it takes to draw a background and some lettering.
     *
     * The share on its own is not enough. A minimal Cover —a white sheet, the
     * title at the top, the author at the bottom, one figure in the middle— is
     * mostly background too, and on the 32×32 grid it measures 0.915, above
     * the 0.93 of a marker rather than below the 0.60 that the sample of
     * 2026-09-23 found among Covers (BIB-53). What does tell them apart is how
     * many colors each one is made of, and there the two do not even touch.
     *
     * It is the backup for a marker whose bytes are not in the list yet, so it
     * is deliberately strict: it would rather let one through than throw out a
     * Cover that is only very plain.
     */
    public function isFlat(): bool
    {
        $samples = $this->samples();

        if ($samples === []) {
            return false;
        }

        return $this->alikeShare($samples) >= self::FLAT_SHARE
            && $this->colorCount($samples) <= self::FLAT_COLORS;
    }

    /**
     * The scaled-down version for the grids, in JPEG and with a white
     * background: a transparent thumbnail over a dark theme looks like a
     * cut-out.
     *
     * An image narrower than the requested width is not enlarged; it is
     * re-encoded at the same size so the thumbnail always exists.
     *
     * @throws InvalidImage
     */
    public function scaled(int $maximumWidth): string
    {
        $original = @imagecreatefromstring($this->binary);

        if ($original === false) {
            throw InvalidImage::notAnImage();
        }

        $width = max(1, min($maximumWidth, $this->width));
        $height = max(1, (int) round($this->height * ($width / $this->width)));

        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            imagedestroy($original);

            throw InvalidImage::notAnImage();
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);

        if ($white !== false) {
            imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        }

        imagecopyresampled($canvas, $original, 0, 0, 0, 0, $width, $height, $this->width, $this->height);

        ob_start();
        imagejpeg($canvas, null, 82);
        $scaled = (string) ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($original);

        return $scaled;
    }

    /**
     * The colors the image shows on a grid of SAMPLES × SAMPLES points, read
     * from the middle of each cell. Empty when GD cannot open the binary.
     *
     * @return list<array{int, int, int}>
     */
    private function samples(): array
    {
        $canvas = @imagecreatefromstring($this->binary);

        if ($canvas === false) {
            return [];
        }

        if (! imageistruecolor($canvas)) {
            imagepalettetotruecolor($canvas);
        }

        $samples = [];

        for ($column = 0; $column < self::SAMPLES; $column++) {
            for ($row = 0; $row < self::SAMPLES; $row++) {
                $x = (int) (($column + 0.5) * $this->width / self::SAMPLES);
                $y = (int) (($row + 0.5) * $this->height / self::SAMPLES);

                $color = imagecolorat($canvas, $x, $y);

                $samples[] = [($color >> 16) & 255, ($color >> 8) & 255, $color & 255];
            }
        }

        imagedestroy($canvas);

        return $samples;
    }

    /**
     * The share of the samples that is the middle color, give or take the
     * tolerance.
     *
     * @param  non-empty-list<array{int, int, int}>  $samples
     */
    private function alikeShare(array $samples): float
    {
        $middle = [];

        foreach ([0, 1, 2] as $channel) {
            $values = array_column($samples, $channel);
            sort($values);
            $middle[$channel] = $values[intdiv(count($values), 2)];
        }

        /** @var array{int, int, int} $middle */
        $alike = 0;

        foreach ($samples as $sample) {
            if (self::alike($sample, $middle)) {
                $alike++;
            }
        }

        return $alike / count($samples);
    }

    /**
     * How many colors the samples are made of, counting as one every group of
     * them that falls within the tolerance of the first of the group.
     *
     * @param  list<array{int, int, int}>  $samples
     */
    private function colorCount(array $samples): int
    {
        /** @var list<array{int, int, int}> $colors */
        $colors = [];

        foreach ($samples as $sample) {
            foreach ($colors as $color) {
                if (self::alike($sample, $color)) {
                    continue 2;
                }
            }

            $colors[] = $sample;
        }

        return count($colors);
    }

    /**
     * Whether two colors are close enough to count as the same one.
     *
     * @param  array{int, int, int}  $color
     * @param  array{int, int, int}  $other
     */
    private static function alike(array $color, array $other): bool
    {
        foreach ([0, 1, 2] as $channel) {
            if (abs($color[$channel] - $other[$channel]) > self::FLAT_TOLERANCE) {
                return false;
            }
        }

        return true;
    }
}
