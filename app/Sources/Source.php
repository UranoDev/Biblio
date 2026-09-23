<?php

namespace App\Sources;

use App\Domain\Isbn;
use App\Enums;

/**
 * The public catalog that gets asked an ISBN or a title to build the Record.
 *
 * This is the seam of ADR-002: there is one implementation per catalog and a
 * resolver that queries them in order. Changing the order, adding a catalog or
 * paying for one touches no screen.
 *
 * There are two operations because there are two surfaces: the Scan asks for an
 * ISBN and expects a Record, the TitleLookup asks for a title and expects
 * Candidates to choose from. **The cascade order need not be the same in both**
 * — it lives in `config/sources.php`, along with the evidence of why.
 */
interface Source
{
    /** Which Source this is, so it stays on record who answered for each Book. */
    public function identifier(): Enums\Source;

    public function byIsbn(Isbn $isbn): LookupResult;

    /**
     * The editions the catalog has of that title, for the TitleLookup. The
     * author is optional and serves to narrow it down.
     */
    public function byTitle(string $title, ?string $author = null): Candidates;
}
