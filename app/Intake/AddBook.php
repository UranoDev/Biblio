<?php

namespace App\Intake;

use App\Ai\Proposals;
use App\Enums\Source;
use App\Enums\Status;
use App\Jobs\DownloadCover;
use App\Models\Book;
use App\Sources\Candidate;
use App\Sources\Record;

/**
 * The only place where a Book is born, wherever it comes from.
 *
 * The three paths —the Scan, the TitleLookup and ManualEntry— differ in where
 * the data comes from, not in what happens after registering it: the Cover is
 * downloaded in the queue (ADR-004) and the Categories are requested in the
 * queue, never during capture (ADR-006). With a single place, adding a path
 * cannot forget either of the two.
 */
final class AddBook
{
    public function __construct(private readonly Proposals $proposals) {}

    /** With the Record a Source brought from the ISBN: the Scan. */
    public function fromRecord(Record $record, Status $status = Status::Owned): Book
    {
        return $this->born([
            'isbn' => $record->isbn->value(),
            'title' => $record->title,
            'authors' => $record->authors,
            'publisher' => $record->publisher,
            'year' => $record->year,
            'edition' => $record->edition,
            'description' => $record->description,
            'description_origin' => $record->descriptionOrigin(),
            'source' => $record->source,
            'status' => $status,
        ], $record->coverUrl);
    }

    /**
     * With the edition picked in a TitleLookup.
     *
     * A Candidate with an ISBN brings exactly the Record the Scan would have
     * brought, so it enters through there. One **without** an ISBN is registered
     * anyway: the Book is left without an ISBN, and Shelf uniqueness only
     * reaches the ones that do have one (ADR-005). What does not change is that
     * the catalog gave the Cover, so it is downloaded and stored like any other
     * (ADR-004).
     */
    public function fromCandidate(Candidate $candidate, Status $status = Status::Owned): Book
    {
        $record = $candidate->toRecord();

        if ($record instanceof Record) {
            return $this->fromRecord($record, $status);
        }

        return $this->born([
            'isbn' => null,
            'title' => $candidate->title,
            'authors' => $candidate->authors,
            'publisher' => $candidate->publisher,
            'year' => $candidate->year,
            'edition' => $candidate->edition,
            'description' => $candidate->description,
            'description_origin' => $candidate->descriptionOrigin(),
            'source' => $candidate->source,
            'status' => $status,
        ], $candidate->coverUrl);
    }

    /**
     * With the data typed out by hand. The Book is marked with the Source
     * "ManualEntry", which is what makes it possible to know later that its
     * Record came from no catalog and that nobody is going to correct it on its
     * own.
     *
     * No Cover is queued for it: there is no URL to download one from. The image
     * is uploaded by hand, with the pieces that already exist (ADR-004).
     */
    public function byHand(NewBook $data): Book
    {
        return $this->born([
            'isbn' => $data->isbn?->value(),
            'title' => $data->title,
            'authors' => $data->authors,
            'publisher' => $data->publisher,
            'year' => $data->year,
            'edition' => $data->edition,
            'source' => Source::Manual,
            'status' => $data->status,
        ], null);
    }

    /**
     * Where the Book is born, whichever the path: it gets registered, its Cover
     * is queued when there is somewhere to download it from, and its Categories
     * are queued.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function born(array $attributes, ?string $coverUrl): Book
    {
        $book = Book::create($attributes);

        DownloadCover::forBook($book, $coverUrl);

        $this->queueTheCategories($book);

        return $book;
    }

    /**
     * The Categories are written by an AI out of the Record, in the queue and
     * never during capture (ADR-006); so is the Description, when no Source
     * brought one (ADR-007). At this point the Book is already registered,
     * which is the only thing the queue needs.
     *
     * Nothing is queued while no AiModel has been chosen, and the Book is
     * registered all the same: choosing one is a screen somebody may not have
     * visited yet.
     */
    private function queueTheCategories(Book $book): void
    {
        $this->proposals->queueFor($book);
    }
}
