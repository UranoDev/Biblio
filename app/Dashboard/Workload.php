<?php

namespace App\Dashboard;

use App\Enums\CoverOrigin;
use App\Enums\GenerationStatus;
use App\Enums\ReadingStatus;
use App\Enums\Status;
use App\Models\Book;
use App\Models\Quote;

/**
 * What the Dashboard knows: where the pending work is, and what shape the
 * library has (BIB-40).
 *
 * Everything the screen counts is asked here and not in the view, for the same
 * reason the Showcase has a class of its own: the counters are one question
 * each, they all lead to the Shelf, and the address each one opens has to be
 * written next to the number it opens — not two files away.
 *
 * The whole screen costs three queries, and the three of them cost the same
 * with ten Books as with two hundred: one grouped row for everything about the
 * Books, one count of Quotes, and one of the jobs the queue gave up on.
 */
final class Workload
{
    /**
     * @param  array<string, int>  $books  the grouped row, one key per counter.
     */
    private function __construct(
        private readonly array $books,
        private readonly int $quotes,
        private readonly int $failedJobs,
    ) {}

    public static function read(): self
    {
        return new self(
            books: self::countBooks(),
            quotes: Quote::query()->count(),
            failedJobs: FailedJobs::count(),
        );
    }

    /**
     * What is left to do. Each of these is a queue somebody has to work
     * through, and at zero each one is a finished thing and says so.
     *
     * @return list<Counter>
     */
    public function pending(): array
    {
        return [
            new Counter(
                key: 'cover-generated',
                label: 'Portadas por fotografiar',
                total: $this->book('cover_generated'),
                url: route('shelf', ['cover' => CoverOrigin::Generated->value]),
                done: 'Ninguna portada quedó generada.',
            ),
            new Counter(
                key: 'generation-queued',
                label: 'Esperando a la IA',
                total: $this->book('generation_queued'),
                url: route('shelf', ['generation' => GenerationStatus::Pending->value]),
                done: 'No hay nada en la cola.',
            ),
            new Counter(
                key: 'generation-failed',
                label: 'Generaciones que fallaron',
                total: $this->book('generation_failed'),
                url: route('shelf', ['generation' => GenerationStatus::Failed->value]),
                done: 'Ninguna generación falló.',
            ),
            new Counter(
                key: 'failed-jobs',
                label: 'Trabajos fallidos',
                total: $this->failedJobs,
                url: route('failed-jobs'),
                done: 'Ningún trabajo falló.',
            ),
            new Counter(
                key: 'without-quotes',
                label: 'Libros sin ninguna cita',
                total: $this->book('without_quotes'),
                url: route('shelf', ['without-quotes' => 'true']),
                done: 'Todos los libros tienen al menos una cita.',
            ),
        ];
    }

    /**
     * What there is. These numbers have no zero worth celebrating —a library
     * without Books is not a finished job— so none of them carries a sentence
     * for it.
     *
     * @return list<Counter>
     */
    public function library(): array
    {
        return [
            new Counter(
                key: 'owned',
                label: 'En el acervo',
                total: $this->book('owned'),
                url: route('shelf', ['status' => Status::Owned->value]),
            ),
            new Counter(
                key: 'wanted',
                label: 'Por comprar',
                total: $this->book('wanted'),
                url: route('shelf', ['status' => Status::Wanted->value]),
            ),
            new Counter(
                key: 'unread',
                label: 'Por leer',
                total: $this->book('unread'),
                url: route('shelf', ['reading' => ReadingStatus::Unread->value]),
            ),
            new Counter(
                key: 'finished',
                label: 'Leídos',
                total: $this->book('finished'),
                url: route('shelf', ['reading' => ReadingStatus::ANY_READ]),
            ),
            new Counter(
                key: 'quotes',
                label: 'Citas escritas',
                total: $this->quotes,
                url: route('quote-search'),
            ),
        ];
    }

    /** Whether there is anything registered at all. */
    public function empty(): bool
    {
        return $this->book('total') === 0;
    }

    private function book(string $key): int
    {
        return $this->books[$key] ?? 0;
    }

    /**
     * Every number about the Books, in one row.
     *
     * Nine counters are nine questions and they are asked together: one query
     * that reads the table once, and not nine that read it nine times. It is
     * the same reason the tabs of the Shelf count grouped.
     *
     * «Por leer» and «Leídos» are counted inside the Acervo: what is Wanted is
     * not read nor pending to read, it is not bought (ADR-005). Neither of the
     * two counts the Book being read right now, because neither of the two
     * filters they open shows it (BIB-59): a number that does not match the
     * list it leads to is worse than no number.
     *
     * @return array<string, int>
     */
    private static function countBooks(): array
    {
        // A mark of the queue older than the cap is not a wait any more but a
        // request that did not get through, and each of the two counters has to
        // read it the way the sheet does (BIB-46).
        $expires = Book::queueMarkExpiresBefore();

        $row = Book::query()->toBase()->selectRaw(<<<'SQL'
            count(*) as total,
            sum(case when status = ? then 1 else 0 end) as owned,
            sum(case when status = ? then 1 else 0 end) as wanted,
            sum(case when status = ? and is_read = 0 and is_reading = 0 then 1 else 0 end) as unread,
            sum(case when status = ? and is_read = 1 and is_reading = 0 then 1 else 0 end) as finished,
            sum(case when cover_origin = ? then 1 else 0 end) as cover_generated,
            sum(case when generation_queued_at is not null and generation_queued_at >= ? then 1 else 0 end) as generation_queued,
            sum(case when generation_failed_at is not null or generation_queued_at < ? then 1 else 0 end) as generation_failed,
            sum(case when not exists (
                select 1 from quotes where quotes.book_id = books.id
            ) then 1 else 0 end) as without_quotes
        SQL, [
            Status::Owned->value,
            Status::Wanted->value,
            Status::Owned->value,
            Status::Owned->value,
            CoverOrigin::Generated->value,
            $expires,
            $expires,
        ])->first();

        if ($row === null) {
            return [];
        }

        return array_map(static fn (mixed $value): int => (int) $value, (array) $row);
    }
}
