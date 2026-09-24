<?php

namespace App\Covers;

use App\Models\Book;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores the Cover of a Book and replaces it when another one arrives
 * (ADR-004).
 *
 * It always writes to the default disk, whichever FILESYSTEM_DISK says, and
 * never names it: the day S3 gets turned on the driver is installed and the
 * `.env` is changed. No file path is built outside of here.
 */
final readonly class CoverStore
{
    public function __construct(
        private string $directory,
        private int $thumbnailWidth,
        private int $maximumWeight,
        private int $minimumSide,
    ) {}

    /**
     * Stores the image as the Cover of the Book: the original as it arrived and
     * the scaled-down version for the grids. If the Book already had a Cover,
     * the previous one is deleted only once the new one is written.
     *
     * @throws InvalidImage|CoverNotStored
     */
    public function store(Book $book, string $binary): void
    {
        $weight = strlen($binary);

        if ($weight > $this->maximumWeight) {
            throw InvalidImage::tooHeavy($weight, $this->maximumWeight);
        }

        $image = Image::from($binary);

        $this->refuseWhatIsNotACover($binary, $image);

        $previous = array_filter([$book->cover_path, $book->cover_thumbnail_path]);

        $name = Str::lower((string) Str::ulid());
        $path = $this->path($book, "{$name}.{$image->extension()}");
        $thumbnail = $this->path($book, "{$name}-thumbnail.jpg");

        $this->write($path, $image->binary);
        $this->write($thumbnail, $image->scaled($this->thumbnailWidth));

        $book->forceFill([
            'cover_path' => $path,
            'cover_thumbnail_path' => $thumbnail,
        ])->save();

        $this->deleteFiles($previous);
    }

    /**
     * The Cover uploaded by hand. It replaces whatever there was: ManualEntry
     * brings none and some Sources return useless thumbnails.
     *
     * @throws InvalidImage|CoverNotStored
     */
    public function storeUpload(Book $book, UploadedFile $file): void
    {
        $binary = @file_get_contents($file->getRealPath());

        if ($binary === false) {
            throw InvalidImage::notAnImage();
        }

        $this->store($book, $binary);
    }

    /** Leaves the Book without a Cover and deletes its files. */
    public function delete(Book $book): void
    {
        $files = array_filter([$book->cover_path, $book->cover_thumbnail_path]);

        $book->forceFill([
            'cover_path' => null,
            'cover_thumbnail_path' => null,
        ])->save();

        $this->deleteFiles($files);
    }

    /**
     * A readable image is not yet a Cover. The catalogs answer an ISBN they
     * have no Cover for with an image that is valid in every other way, and
     * stored as it comes it looks like a bad Cover instead of like the mistake
     * it is (BIB-28).
     *
     * @throws InvalidImage
     */
    private function refuseWhatIsNotACover(string $binary, Image $image): void
    {
        $marker = Marker::of($binary);

        if ($marker !== null) {
            throw InvalidImage::isAMarker($marker);
        }

        if ($image->shortestSide() < $this->minimumSide) {
            throw InvalidImage::tooSmall($image->shortestSide(), $this->minimumSide);
        }

        if ($image->isFlat()) {
            throw InvalidImage::isFlat();
        }
    }

    private function path(Book $book, string $file): string
    {
        return "{$this->directory}/{$book->getKey()}/{$file}";
    }

    /**
     * @throws CoverNotStored
     */
    private function write(string $path, string $contents): void
    {
        if (Storage::disk()->put($path, $contents) === false) {
            throw CoverNotStored::atPath($path);
        }
    }

    /** @param  array<int, string>  $files */
    private function deleteFiles(array $files): void
    {
        foreach ($files as $file) {
            Storage::disk()->delete($file);
        }
    }
}
