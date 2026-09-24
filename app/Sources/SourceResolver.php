<?php

namespace App\Sources;

use App\Domain\Isbn;

/**
 * Queries the Sources in order and returns the first thing that shows up.
 *
 * The Record is the first one to show up; its Cover need not be. When the
 * catalog that answered has no image of that edition, the next ones are asked
 * for that single field.
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

        foreach ($this->sources as $position => $source) {
            $result = $source->byIsbn($isbn);

            if ($result->hasRecord()) {
                return $this->withBorrowedCover($result, $isbn, $position);
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
     * The Record of the Source that won, with the Cover of the next one that
     * has any.
     *
     * A catalog answering the ISBN and having no image of that edition is the
     * normal case and not the rare one —Open Library does it often—, and a
     * small Cover beats none. Only `coverUrl` is borrowed: title, authors,
     * publisher and year stay the ones that already answered, and which Source
     * they came from stays on record. Which Source wins the whole cascade is a
     * separate matter, decided in the configuration.
     *
     * Only the Sources **after** the winner get asked: the earlier ones either
     * did not know the ISBN —so they have no Cover of it either— or could not
     * answer at all.
     *
     * A Book whose Source brought a Cover returns without spending anything
     * extra, so the cost is one request per Book that has none. Google Books is
     * queried with a key of our own (ADR-002), so that quota is ours to spend,
     * and this is exactly what it is for.
     *
     * @param  int  $position  where in the order the Source that answered is
     */
    private function withBorrowedCover(LookupResult $result, Isbn $isbn, int $position): LookupResult
    {
        $record = $result->record;

        if (! $record instanceof Record || $record->coverUrl !== null) {
            return $result;
        }

        foreach (array_slice($this->sources, $position + 1) as $source) {
            $coverUrl = $source->byIsbn($isbn)->record?->coverUrl;

            if ($coverUrl !== null) {
                return LookupResult::withRecord($record->withCover($coverUrl));
            }
        }

        return $result;
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
