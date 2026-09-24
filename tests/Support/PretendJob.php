<?php

namespace Tests\Support;

use Illuminate\Contracts\Queue\Job;
use Throwable;

/**
 * The queue's side of a job, close enough to run one and see what it decided.
 *
 * What a job does with a failure —go back in line with a delay, or fail on the
 * spot— is not visible in the Book or in the database: it is a call to
 * `release()` or to `fail()`. On the `sync` connection those calls go nowhere,
 * so the tests that are about BIB-33 run the job against this instead.
 */
final class PretendJob implements Job
{
    public ?int $releasedFor = null;

    public ?Throwable $failure = null;

    private bool $released = false;

    private bool $deleted = false;

    private bool $failed = false;

    public function __construct(
        private readonly object $command,
        private readonly int $attempts = 1,
        private readonly string $connection = 'database',
    ) {}

    public function release($delay = 0): void
    {
        $this->released = true;
        $this->releasedFor = (int) $delay;
    }

    /**
     * The same thing the real job does: it marks it, and the command gets to
     * hear about it through failed().
     */
    public function fail($e = null): void
    {
        if ($this->failed) {
            return;
        }

        $this->failed = true;
        $this->failure = $e;
        $this->deleted = true;

        if (method_exists($this->command, 'failed')) {
            $this->command->failed($e);
        }
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function isReleased(): bool
    {
        return $this->released;
    }

    public function hasFailed(): bool
    {
        return $this->failed;
    }

    public function markAsFailed(): void
    {
        $this->failed = true;
    }

    public function delete(): void
    {
        $this->deleted = true;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function isDeletedOrReleased(): bool
    {
        return $this->deleted || $this->released;
    }

    public function getConnectionName(): string
    {
        return $this->connection;
    }

    public function uuid(): string
    {
        return 'pretend';
    }

    public function getJobId(): string
    {
        return 'pretend';
    }

    public function getQueue(): string
    {
        return 'default';
    }

    public function getName(): string
    {
        return $this->command::class;
    }

    public function resolveName(): string
    {
        return $this->command::class;
    }

    public function resolveQueuedJobClass(): string
    {
        return $this->command::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [];
    }

    public function getRawBody(): string
    {
        return '';
    }

    public function fire(): void
    {
        //
    }

    public function maxTries(): ?int
    {
        return null;
    }

    public function maxExceptions(): ?int
    {
        return null;
    }

    public function timeout(): ?int
    {
        return null;
    }

    public function retryUntil(): ?int
    {
        return null;
    }
}
