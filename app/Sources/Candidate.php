<?php

namespace App\Sources;

use App\Domain\Isbn;
use App\Enums;

/**
 * Each edition the Source returns in a TitleLookup.
 *
 * It carries Cover, authors, publisher and year because without those there is
 * no way to tell one edition from another: a Book is an edition (ADR-005), and
 * any given title has the paperback, the hardcover, the 1998 one and the 2015
 * one, all with the same name on the list.
 *
 * A Candidate **without an ISBN is valid**: it can be registered anyway and the
 * Book is left without an ISBN. Shelf uniqueness applies to the ones that do
 * have one.
 */
final readonly class Candidate
{
    /**
     * @param  list<string>  $authors  in the order the Source credits them
     * @param  string|null  $reference  the identifier the catalog gives to this
     *                                  edition. It is the only thing telling two
     *                                  Candidates apart when neither the ISBN nor
     *                                  the publisher nor the year does.
     */
    public function __construct(
        public ?Isbn $isbn,
        public string $title,
        public array $authors,
        public ?string $publisher,
        public ?int $year,
        public ?string $edition,
        public ?string $coverUrl,
        public ?string $description,
        public Enums\Source $source,
        public ?string $reference = null,
    ) {}

    public function hasIsbn(): bool
    {
        return $this->isbn instanceof Isbn;
    }

    /**
     * With which origin the Description of this Candidate is stored, or null
     * when it brought none (ADR-007). It is the same rule as in the Record: what
     * arrived from the catalog is stored as coming from the Source.
     */
    public function descriptionOrigin(): ?Enums\Origin
    {
        return $this->description !== null && trim($this->description) !== ''
            ? Enums\Origin::Source
            : null;
    }

    /**
     * What this Candidate is pointed at with from the screen that showed it. It
     * is stable between one lookup and the next as long as the catalog does not
     * change its identifiers.
     */
    public function key(): string
    {
        return $this->source->value.':'.($this->reference
            ?? $this->isbn?->value()
            ?? md5($this->title.'|'.implode(',', $this->authors).'|'.$this->publisher.'|'.$this->year));
    }

    /**
     * The plain data of the Candidate, so the screen that showed it can remember
     * it between one lookup and the next.
     *
     * Asking the Source again at the moment of choosing does not work: it
     * answers something else, or does not answer, and what would be registered
     * would not be the edition seen on the list.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'isbn' => $this->isbn?->value(),
            'title' => $this->title,
            'authors' => $this->authors,
            'publisher' => $this->publisher,
            'year' => $this->year,
            'edition' => $this->edition,
            'coverUrl' => $this->coverUrl,
            'description' => $this->description,
            'source' => $this->source->value,
            'reference' => $this->reference,
        ];
    }

    /**
     * The Candidate that was stored with `toArray`.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $isbn = $data['isbn'] ?? null;
        $authors = $data['authors'] ?? [];

        return new self(
            isbn: is_string($isbn) && $isbn !== '' ? Isbn::from($isbn) : null,
            title: (string) ($data['title'] ?? ''),
            authors: is_array($authors) ? array_values(array_map(strval(...), $authors)) : [],
            publisher: self::textOrNull($data['publisher'] ?? null),
            year: isset($data['year']) ? (int) $data['year'] : null,
            edition: self::textOrNull($data['edition'] ?? null),
            coverUrl: self::textOrNull($data['coverUrl'] ?? null),
            description: self::textOrNull($data['description'] ?? null),
            source: Enums\Source::from((string) ($data['source'] ?? '')),
            reference: self::textOrNull($data['reference'] ?? null),
        );
    }

    private static function textOrNull(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * The Record of this Candidate, or null when it has no ISBN: the Record is
     * built out of the ISBN and without one there is none to build.
     */
    public function toRecord(): ?Record
    {
        if (! $this->isbn instanceof Isbn) {
            return null;
        }

        return new Record(
            isbn: $this->isbn,
            title: $this->title,
            authors: $this->authors,
            publisher: $this->publisher,
            year: $this->year,
            edition: $this->edition,
            coverUrl: $this->coverUrl,
            description: $this->description,
            source: $this->source,
        );
    }
}
