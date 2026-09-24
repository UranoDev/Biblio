<?php

namespace App\Ai;

use App\Enums\RegenerationTarget;

/**
 * What a mass regeneration would do, counted before launching it (BIB-21).
 *
 * Two hundred requests to a provider are paid for, so the screen says how many
 * Books the run reaches and how many it leaves out because their Description is
 * the one from the Source or the one I wrote (ADR-007).
 */
final readonly class RegenerationPlan
{
    /**
     * @param  int  $books  how many Books the run would ask the AI about
     * @param  int  $kept  how many Descriptions it would leave untouched because of their Origin
     * @param  int  $translated  how many of them it would translate instead of rewrite (BIB-45)
     */
    public function __construct(
        public RegenerationTarget $target,
        public bool $onlyPrevious,
        public int $books,
        public int $kept,
        public int $translated = 0,
    ) {}

    /** Whether there is anything to ask for. */
    public function reachesAnything(): bool
    {
        return $this->books > 0;
    }
}
