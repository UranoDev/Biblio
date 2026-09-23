<?php

namespace App\Sources;

use App\Domain\Isbn;

/**
 * Queries the Sources in order and returns the first thing that shows up.
 *
 * The order lives in the configuration, not here nor on the screens: that is
 * what allows adding, removing or reordering catalogs without touching anything
 * else (ADR-002). There is one order per operation, because they do not answer
 * equally well to both.
 */
final class SourceResolver
{
    /** @var list<Source> */
    private readonly array $titleOrder;

    /**
     * @param  list<Source>  $sources  in the order they get asked for an ISBN
     * @param  list<Source>|null  $titleOrder  the order for the TitleLookup; the one
     *                                         above when no other is given
     */
    public function __construct(
        private readonly array $sources,
        ?array $titleOrder = null,
    ) {
        $this->titleOrder = $titleOrder ?? $sources;
    }

    public function byIsbn(Isbn $isbn): LookupResult
    {
        $unanswered = [];

        foreach ($this->sources as $source) {
            $result = $source->byIsbn($isbn);

            if ($result->hasRecord()) {
                return $result;
            }

            if (! $result->answered()) {
                $unanswered[] = $source->identifier()->label().': '.$result->reason;
            }
        }

        // One Source failing to answer is enough for the outcome not to be
        // "no Record": sending this to ManualEntry would register by hand a
        // Book the Source did have.
        return $unanswered === []
            ? LookupResult::withoutRecord()
            : LookupResult::unavailable(implode(' · ', $unanswered));
    }

    /**
     * The Candidates of the first Source that has any.
     *
     * They are not merged across all of them: two catalogs return the same
     * editions with different data, and a list with the same edition repeated is
     * exactly what the TitleLookup cannot have — the screen exists to tell
     * editions apart.
     */
    public function byTitle(string $title, ?string $author = null): Candidates
    {
        $unanswered = [];

        foreach ($this->titleOrder as $source) {
            $candidates = $source->byTitle($title, $author);

            if ($candidates->hasCandidates()) {
                return $candidates;
            }

            if (! $candidates->answered()) {
                $unanswered[] = $source->identifier()->label().': '.$candidates->reason;
            }
        }

        // Same criterion as above, and it matters more here: the title search is
        // the one that exhausts a quota first, so the 429 is the normal case and
        // not the rare one.
        return $unanswered === []
            ? Candidates::none()
            : Candidates::unavailable(implode(' · ', $unanswered));
    }
}
