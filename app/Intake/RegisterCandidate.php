<?php

namespace App\Intake;

use App\Enums\Status;
use App\Models\Book;
use App\Sources\Candidate;

/**
 * What happens when a Candidate is picked in the TitleLookup.
 *
 * It lives outside the screen for the same reason as RegisterScan: an ISBN is
 * unique on the Shelf (ADR-005), and that rule does not change depending on
 * which way the Book comes in.
 *
 * What does change is what to do with the one that was already there. The rescan
 * moves it from Wanted to the Shelf, because having the Book in hand is
 * precisely the signal that it was bought; here there is no Book in hand —an
 * edition is being picked from a list—, so it only reports which one is already
 * there.
 */
final class RegisterCandidate
{
    public function __construct(private readonly AddBook $add) {}

    public function register(Candidate $candidate, Status $status = Status::Wanted): IntakeResult
    {
        // A Candidate without an ISBN cannot collide with anything: uniqueness
        // reaches the ones that do have one.
        $registered = $candidate->hasIsbn()
            ? Book::query()->where('isbn', $candidate->isbn?->value())->first()
            : null;

        if ($registered instanceof Book) {
            return IntakeResult::wasAlreadyThere($registered);
        }

        return IntakeResult::registered($this->add->fromCandidate($candidate, $status));
    }
}
