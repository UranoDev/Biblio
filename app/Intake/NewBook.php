<?php

namespace App\Intake;

use App\Domain\Isbn;
use App\Enums\Status;

/**
 * The data of a Book typed out by hand, because no Source knows it.
 *
 * Only the title is required: of an old book without a barcode the year and the
 * edition may be unknown, and a piece of data that is not known is better left
 * blank than made up.
 */
final readonly class NewBook
{
    /**
     * @param  list<string>  $authors  in the order they are credited on the cover
     */
    public function __construct(
        public string $title,
        public array $authors = [],
        public ?string $publisher = null,
        public ?int $year = null,
        public ?string $edition = null,
        public ?Isbn $isbn = null,
        public Status $status = Status::Owned,
    ) {}
}
