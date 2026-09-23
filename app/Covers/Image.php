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
