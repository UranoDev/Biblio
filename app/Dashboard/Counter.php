<?php

namespace App\Dashboard;

/**
 * One number of the Dashboard, together with the list it opens.
 *
 * The address is a constructor argument and not something the screen works out
 * afterwards: a counter that cannot be opened is a curiosity, and one that
 * opens the Shelf already narrowed is the beginning of a task (BIB-40). Built
 * this way there is no counter without somewhere to lead.
 */
final readonly class Counter
{
    /**
     * @param  string  $key  what this counter is called in the code, and what
     *                       the tests reach for it by.
     * @param  string  $label  what the number is called on screen.
     * @param  int  $total  how many there are.
     * @param  string  $url  the list this number opens.
     * @param  string|null  $done  what the tile says at zero, when zero means a
     *                             finished piece of work and not an empty library.
     */
    public function __construct(
        public string $key,
        public string $label,
        public int $total,
        public string $url,
        public ?string $done = null,
    ) {}

    /**
     * Whether this number is work that is finished.
     *
     * Only the counters of pending work carry a sentence for zero. A library
     * with no Books yet is at zero too, and there is nothing finished about it.
     */
    public function isDone(): bool
    {
        return $this->total === 0 && $this->done !== null;
    }
}
