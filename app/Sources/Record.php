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
