<?php

namespace App\Covers;

use App\Enums\CoverOrigin;
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
     * The Origin travels with the image because this is the single door every
     * Cover comes in through, and it is here that the replacement rule of
     * ADR-008 is enforced: what a Source brings replaces a generated Cover and
     * never the photograph the owner took.
     *
     * @throws InvalidImage|CoverNotStored
     */
    public function store(Book $book, string $binary, CoverOrigin $origin = CoverOrigin::Source): void
    {
        if (! $origin->mayReplace($book->coverOrigin())) {
            return;
        }

        $weight = strlen($binary);

        if ($weight > $this->maximumWeight) {
            throw InvalidImage::tooHeavy($weight, $this->maximumWeight);
        }

        $image = Image::from($binary);

        $this->refuseWhatIsNotACover($binary, $image);

        $previous = array_filter([$book->cover_path, $book->cover_thumbnail_path]);

        $name = Str::lower((string) Str::ulid());
        $path = $this->path($book, "{$name}.{$image->extension()}");
        $thumbnail = $this->path($book, "{$name}-thumbnail.webp");

        $this->write($path, $image->binary);
        $this->write($thumbnail, $image->scaled($this->thumbnailWidth));

        $book->forceFill([
            'cover_path' => $path,
            'cover_thumbnail_path' => $thumbnail,
            'cover_origin' => $origin,
            // An image got through, so whatever the last download gave up on is
            // off the record (BIB-52). It is cleared here and not in the job
            // because this is the single door every Cover comes in through: the
            // one uploaded by hand answers the same failed download.
            'cover_failed_at' => null,
            'cover_failure' => null,
        ])->save();

        $this->deleteFiles($previous);
    }

    /**
     * The Cover uploaded by hand: my own photograph of the book. It replaces
     * whatever there was —a generated piece, or an image from a Source— and
     * from then on no retry against the Sources touches it (ADR-008).
     *
     * @throws InvalidImage|CoverNotStored
     */
    public function storeUpload(Book $book, UploadedFile $file): void
    {
        $binary = @file_get_contents($file->getRealPath());

        if ($binary === false) {
            throw InvalidImage::notAnImage();
        }

        $this->store($book, $binary, CoverOrigin::Owner);
    }

    /**
     * Deletes the stored image and its files. The Book is not left without a
     * Cover —there is no such state (ADR-008)—: it goes back to the generated
     * piece, and back into the queue of what is left to photograph.
     */
    public function delete(Book $book): void
    {
        $files = array_filter([$book->cover_path, $book->cover_thumbnail_path]);

        $book->forceFill([
            'cover_path' => null,
            'cover_thumbnail_path' => null,
            'cover_origin' => CoverOrigin::Generated,
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
