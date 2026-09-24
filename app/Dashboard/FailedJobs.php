<?php

namespace App\Dashboard;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The jobs the queue gave up on (BIB-40).
 *
 * A queue that stopped with nobody looking is exactly what leaves two hundred
 * books half done: the Categories, the Description and the images of the Quotes
 * all arrive through it, and every one of them fails in silence. So the number
 * is on the Dashboard and the list behind it is a screen of its own.
 *
 * The table is read directly and not through a model: it belongs to the
 * framework, its rows are written by the worker, and nothing in this
 * application ever creates one.
 */
final class FailedJobs
{
    /** How many the queue has given up on. */
    public static function count(): int
    {
        return self::table()?->count() ?? 0;
    }

    /**
     * The most recent ones, newest first.
     *
     * @return list<FailedJob>
     */
    public static function recent(int $limit = 50): array
    {
        $rows = self::table()
            ?->orderByDesc('failed_at')
            ->limit($limit)
            ->get(['payload', 'exception', 'failed_at']);

        $jobs = [];

        foreach ($rows ?? [] as $row) {
            $jobs[] = new FailedJob(
                name: self::nameOf($row->payload ?? null),
                failedAt: self::momentOf($row->failed_at ?? null),
                reason: self::reasonOf($row->exception ?? null),
            );
        }

        return $jobs;
    }

    /**
     * The table, or nothing at all when the failures are not being written to a
     * database. The driver is configurable and this screen is the only reader:
     * with a file driver there is no table to open, and asking would be an
     * error and not an empty list.
     */
    private static function table(): ?Builder
    {
        $driver = config('queue.failed.driver');

        if (! is_string($driver) || ! str_starts_with($driver, 'database')) {
            return null;
        }

        $connection = config('queue.failed.database');
        $table = config('queue.failed.table', 'failed_jobs');

        return DB::connection(is_string($connection) ? $connection : null)
            ->table(is_string($table) ? $table : 'failed_jobs');
    }

    /** Which job it was: the name the queue itself gives it in the payload. */
    private static function nameOf(mixed $payload): string
    {
        if (! is_string($payload)) {
            return 'Un trabajo';
        }

        $decoded = json_decode($payload, true);

        $name = is_array($decoded) ? ($decoded['displayName'] ?? null) : null;

        return is_string($name) && $name !== '' ? class_basename($name) : 'Un trabajo';
    }

    private static function momentOf(mixed $failedAt): ?Carbon
    {
        if (! is_string($failedAt) && ! $failedAt instanceof Carbon) {
            return null;
        }

        try {
            return Carbon::parse($failedAt);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The line of the error.
     *
     * A stack trace is forty lines and the first one is the only one that says
     * what happened; the rest is for a place where the code can be read.
     */
    private static function reasonOf(mixed $exception): string
    {
        if (! is_string($exception) || trim($exception) === '') {
            return 'Sin detalle.';
        }

        $first = (string) Str::of($exception)->explode("\n")->first();

        return Str::limit(Str::squish($first), 200);
    }
}
