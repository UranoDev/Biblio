<?php

namespace App\Intake;

use App\Domain\InvalidIsbn;
use App\Domain\Isbn;
use App\Enums\Status;
use App\Models\Book;
use App\Sources\SourceResolver;

/**
 * What happens when an ISBN comes in through the Scanner.
 *
 * It lives outside the screen because these are the rules of ADR-005 —an ISBN is
 * unique on the Shelf and the rescan has three outcomes—, not decisions of the
 * capture screen: the TitleLookup and ManualEntry share the not-duplicating one.
 */
final class RegisterScan
{
    public function __construct(
        private readonly SourceResolver $sources,
        private readonly AddBook $add,
    ) {}

    public function register(string $typed): ScanResult
    {
        $typed = trim($typed);

        try {
            $isbn = Isbn::from($typed);
        } catch (InvalidIsbn) {
            return ScanResult::invalidIsbn($typed);
        }

        $registered = Book::query()->where('isbn', $isbn->value())->first();

        if ($registered instanceof Book) {
            return $this->rescan($registered, $isbn, $typed);
        }

        $result = $this->sources->byIsbn($isbn);

        if (! $result->hasRecord()) {
            // No Source knowing it leads to ManualEntry; one of them failing to
            // answer does not: that would register by hand a Book the Source did
            // have (ADR-002).
            return $result->isbnUnknown()
                ? ScanResult::unknownIsbn($isbn, $typed)
                : ScanResult::sourceUnavailable($isbn, $typed, $result->reason);
        }

        return ScanResult::registered(
            $this->add->fromRecord($result->record),
            $isbn,
            $typed,
        );
    }

    /**
     * The two outcomes of an ISBN that was already there: if it is Wanted, it
     * moves to the Shelf on its own —that is the normal path for "I already
     * bought it"— and if it was already on the Shelf, it is only reported
     * (ADR-005).
     */
    private function rescan(Book $book, Isbn $isbn, string $typed): ScanResult
    {
        if ($book->status === Status::Wanted) {
            $book->moveToShelf();

            return ScanResult::movedToShelf($book, $isbn, $typed);
        }

        return ScanResult::alreadyOnShelf($book, $isbn, $typed);
    }
}
