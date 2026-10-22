<?php

namespace App\Intake;

use App\Ai\CoverReader;
use App\Domain\Isbn;
use App\Sources\Record;
use App\Sources\SourceResolver;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Files\Base64Image;
use Throwable;

/**
 * Turns the photograph of a Cover into the data of the form (BIB-73).
 *
 * Two steps, and the second one is the reason this is not just the agent: what
 * the AI read off the photograph fills the form, unless the photograph carries
 * an ISBN some Source knows. Then the Record of the catalog wins, because a
 * catalog does not misread a title.
 *
 * It answers synchronously, while the owner waits on the form: the photograph
 * is the whole input of the screen, so there is nothing to do in the meantime.
 */
final class ReadCover
{
    public function __construct(private readonly SourceResolver $sources) {}

    /**
     * @throws CoverUnreadable when the AI could not be asked or answered nothing usable
     */
    public function read(UploadedFile $photo): CoverReading
    {
        // The type read off its bytes: a file Livewire keeps between requests
        // arrives without the type the browser gave it.
        return $this->readImage((string) $photo->get(), (string) $photo->getMimeType());
    }

    /**
     * The same reading, out of the bytes of the photograph: the ones that
     * arrive from the Drive folder are not uploads (BIB-74).
     *
     * @throws CoverUnreadable when the AI could not be asked or answered nothing usable
     */
    public function readImage(string $binary, string $mimeType): CoverReading
    {
        try {
            // Sent as base64 with its type: the SDK takes an image without a
            // type for a document.
            $image = new Base64Image(base64_encode($binary), $mimeType);

            $answer = (new CoverReader)->prompt(
                'Read this book cover.',
                [$image],
                provider: (string) config('intake.cover_reader.provider'),
                model: (string) config('intake.cover_reader.model'),
                timeout: (int) config('intake.cover_reader.timeout', 60),
            );
        } catch (Throwable $failure) {
            throw CoverUnreadable::because($failure->getMessage(), $failure);
        }

        $reading = new CoverReading(
            title: $this->text($answer['title'] ?? null),
            authors: $this->names($answer['authors'] ?? null),
            publisher: $this->text($answer['publisher'] ?? null),
            year: null,
            isbn: $this->isbn($answer['isbn'] ?? null),
        );

        if ($reading->title === null && $reading->isbn === null) {
            throw CoverUnreadable::because('The AI read neither a title nor an ISBN off the photograph.');
        }

        return $this->withTheSource($reading);
    }

    /** The Record of a Source, when the ISBN on the photograph has one. */
    private function withTheSource(CoverReading $reading): CoverReading
    {
        if ($reading->isbn === null) {
            return $reading;
        }

        $record = $this->sources->byIsbn($reading->isbn)->record;

        return $record instanceof Record
            ? CoverReading::fromRecord($record)
            : $reading;
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim($value);

        return $clean === '' ? null : $clean;
    }

    /** @return list<string> */
    private function names(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $name): ?string => $this->text($name), $value),
            fn (?string $name): bool => $name !== null,
        ));
    }

    /** An ISBN only when its digits add up: a misread one identifies nothing. */
    private function isbn(mixed $value): ?Isbn
    {
        $text = $this->text($value);

        return $text !== null && Isbn::isValid($text) ? Isbn::from($text) : null;
    }
}
