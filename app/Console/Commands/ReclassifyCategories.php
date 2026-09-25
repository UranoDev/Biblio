<?php

namespace App\Console\Commands;

use App\Ai\Proposals;
use App\Enums\RegenerationTarget;
use App\Models\Book;
use Illuminate\Console\Command;

/**
 * One-time migration: re-queues every book for category regeneration against
 * the canonical list of 30 (BIB-68).
 *
 * Run once after deploying the CanonicalCategories change. Books are queued
 * for categories only — descriptions are not touched. The job runs at whatever
 * pace the queue workers allow, so it is safe to run on production without
 * downtime.
 */
final class ReclassifyCategories extends Command
{
    protected $signature = 'books:reclassify-categories
        {--pretend : muestra cuántos libros se encolarían, sin encolar nada}';

    protected $description = 'Única vez: re-categoriza todos los libros contra la lista canónica (BIB-68).';

    public function handle(Proposals $proposals): int
    {
        $books = Book::query()
            ->whereNull('is_hidden')
            ->orWhere('is_hidden', false)
            ->get();

        if ($books->isEmpty()) {
            $this->components->info('No hay libros visibles que reclasificar.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            '%d %s a la cola (sólo categorías).',
            $books->count(),
            $books->count() === 1 ? 'libro entrará' : 'libros entrarán',
        ));

        if ($this->option('pretend')) {
            $this->components->warn('No se encoló nada: corrió con --pretend.');

            return self::SUCCESS;
        }

        $queued = 0;
        $skipped = 0;

        foreach ($books as $book) {
            if ($proposals->queueFor($book, RegenerationTarget::Categories)) {
                $queued++;
            } else {
                $skipped++;
            }
        }

        $this->components->info(sprintf('%d encolados.', $queued));

        if ($skipped > 0) {
            $this->components->warn(sprintf(
                '%d omitidos: no hay ningún modelo de IA configurado.',
                $skipped,
            ));
        }

        return self::SUCCESS;
    }
}
