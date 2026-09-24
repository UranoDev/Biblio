<?php

namespace App\Sources;

use App\Domain\Isbn;
use App\Enums;

/**
 * The bibliographic data that arrives from a Source given the ISBN.
 *
 * Its shape does not depend on which catalog answered (ADR-002): adding,
 * removing or reordering Sources changes nothing above this point. Who answered
 * is kept in `source`, which is what is needed the day some piece of data comes
 * out sloppy.
 */
final readonly class Record
{
    /**
     * @param  list<string>  $authors  in the order the Source credits them
     */
    public function __construct(
        public Isbn $isbn,
        public string $title,
        public array $authors,
        public ?string $publisher,
        public ?int $year,
        public ?string $edition,
        public ?string $coverUrl,
        public ?string $description,
        public Enums\Source $source,
    ) {}

    /**
     * The same Record with the Cover of another Source, for when the one that
     * answered brought none.
     *
     * `source` does not move: it says which catalog the Record came from, and
     * that keeps being this one. The Cover is the only borrowed field.
     */
    public function withCover(string $coverUrl): self
    {
        return new self(
            isbn: $this->isbn,
            title: $this->title,
            authors: $this->authors,
            publisher: $this->publisher,
            year: $this->year,
            edition: $this->edition,
            coverUrl: $coverUrl,
            description: $this->description,
            source: $this->source,
        );
    }

    /**
     * A Record without a Description is valid: the Book is registered anyway and
     * left for the AI to write it later (ADR-007).
     */
    public function hasDescription(): bool
    {
        return $this->description !== null && trim($this->description) !== '';
    }

    /**
     * With which origin the Description of this Record is stored, or null when
     * it brought none (ADR-007).
     */
    public function descriptionOrigin(): ?Enums\Origin
    {
        return $this->hasDescription() ? Enums\Origin::Source : null;
    }
}
