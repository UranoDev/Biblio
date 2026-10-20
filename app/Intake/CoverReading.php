<?php

namespace App\Intake;

use App\Domain\Isbn;
use App\Sources\Record;

/**
 * What the photograph of a Cover gave for the form (BIB-73): read off the
 * photograph by the AI, or the Record of a Source when the photograph carried
 * an ISBN some catalog knows.
 */
final readonly class CoverReading
{
    /**
     * @param  list<string>  $authors
     */
    public function __construct(
        public ?string $title,
        public array $authors,
        public ?string $publisher,
        public ?int $year,
        public ?Isbn $isbn,
        public bool $fromSource = false,
    ) {}

    public static function fromRecord(Record $record): self
    {
        return new self(
            title: $record->title,
            authors: $record->authors,
            publisher: $record->publisher,
            year: $record->year,
            isbn: $record->isbn,
            fromSource: true,
        );
    }
}
