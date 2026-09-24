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
     * Whether the image is a single flat color across almost its whole surface.
     *
     * That is what the markers of the catalogs look like —the white sheet with
     * grey lettering, the red tile— and what no real Cover looks like: measured
     * against both on 2026-09-23, the markers sit at 0.93 and the covers do not
     * reach 0.60.
     *
     * It is the backup for a marker whose bytes are not in the list yet, so it
     * is deliberately strict: it would rather let one through than throw out a
     * Cover that is only very plain.
     */
    public function isFlat(): bool
    {
        $canvas = @imagecreatefromstring($this->binary);

        if ($canvas === false) {
            return false;
        }

        if (! imageistruecolor($canvas)) {
            imagepalettetotruecolor($canvas);
        }

        /** @var list<array{int, int, int}> $samples */
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

        $middle = [];

        foreach ([0, 1, 2] as $channel) {
            $values = array_column($samples, $channel);
            sort($values);
            $middle[$channel] = $values[intdiv(count($values), 2)];
        }

        $alike = 0;

        foreach ($samples as $sample) {
            foreach ([0, 1, 2] as $channel) {
                if (abs($sample[$channel] - $middle[$channel]) > self::FLAT_TOLERANCE) {
                    continue 2;
                }
            }

            $alike++;
        }

        return $alike / count($samples) >= self::FLAT_SHARE;
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
}
