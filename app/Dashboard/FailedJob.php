<?php

namespace App\Dashboard;

use Illuminate\Support\Carbon;

/**
 * One job the queue gave up on, as the screen that lists them reads it.
 *
 * The row of `failed_jobs` holds the whole serialised job and the whole stack
 * trace, and neither of those is an answer to «¿qué se rompió?». What is kept
 * is the three things that are: which job it was, when it stopped, and the line
 * of the error.
 */
final readonly class FailedJob
{
    public function __construct(
        public string $name,
        public ?Carbon $failedAt,
        public string $reason,
    ) {}
}
