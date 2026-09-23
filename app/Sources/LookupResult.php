<?php

namespace App\Sources;

/**
 * What a Source —or the resolver— answers when asked for an ISBN.
 *
 * They are three outcomes, and the difference between the last two is the whole
 * reason this class exists: "I do not know it" leads to ManualEntry, "I could
 * not answer" asks to retry. Mixing them up registers by hand a Book the Source
 * did have, and whoever is capturing has no way to notice (ADR-002).
 */
final readonly class LookupResult
{
    private function __construct(
        public ?Record $record,
        private bool $answered,
        public ?string $reason,
    ) {}

    public static function withRecord(Record $record): self
    {
        return new self($record, true, null);
    }

    /** The Source answered and does not know that ISBN. */
    public static function withoutRecord(): self
    {
        return new self(null, true, null);
    }

    /** The Source could not answer: quota exhausted, network error or ugly HTTP. */
    public static function unavailable(string $reason): self
    {
        return new self(null, false, $reason);
    }

    public function hasRecord(): bool
    {
        return $this->record instanceof Record;
    }

    /** Whether the Source got to answer, whether it knows the ISBN or not. */
    public function answered(): bool
    {
        return $this->answered;
    }

    /** It answered it does not know it: the case that offers ManualEntry. */
    public function isbnUnknown(): bool
    {
        return $this->answered && ! $this->hasRecord();
    }
}
