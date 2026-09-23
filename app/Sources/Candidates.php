<?php

namespace App\Sources;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * What a Source —or the resolver— answers in a TitleLookup.
 *
 * They are the same three outcomes as in the ISBN lookup, and for the same
 * reason: **an empty list and a Source that could not answer are not the same
 * thing**. The first says there is no such edition and offers ManualEntry; the
 * second asks to retry. Mixing them up sends the person to type out by hand a
 * Book the catalog did have (ADR-002).
 *
 * @implements IteratorAggregate<int, Candidate>
 */
final readonly class Candidates implements Countable, IteratorAggregate
{
    /** @param  list<Candidate>  $candidates */
    private function __construct(
        public array $candidates,
        private bool $answered,
        public ?string $reason,
    ) {}

    /**
     * `array_values` is not redundant: filtering a list of Candidates leaves
     * holes in the keys, and from here upwards it is walked by position.
     *
     * @param  array<array-key, Candidate>  $candidates
     */
    public static function of(array $candidates): self
    {
        return new self(array_values($candidates), true, null);
    }

    /** The Source answered and has no edition of that title. */
    public static function none(): self
    {
        return new self([], true, null);
    }

    /** The Source could not answer: quota exhausted, network error or ugly HTTP. */
    public static function unavailable(string $reason): self
    {
        return new self([], false, $reason);
    }

    public function hasCandidates(): bool
    {
        return $this->candidates !== [];
    }

    /** Whether the Source got to answer, whether it has the title or not. */
    public function answered(): bool
    {
        return $this->answered;
    }

    /** It answered it does not have that title: the case that offers ManualEntry. */
    public function titleUnknown(): bool
    {
        return $this->answered && ! $this->hasCandidates();
    }

    public function count(): int
    {
        return count($this->candidates);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->candidates);
    }
}
