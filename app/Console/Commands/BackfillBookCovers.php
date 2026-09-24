<?php

namespace App\Console\Commands;

use App\Covers\MissingCovers;
use Illuminate\Console\Command;

/**
 * Asks the Sources again for the Covers that are missing (BIB-57).
 *
 * Open Library answers many an ISBN with no image of that edition, so the Book
 * got registered showing the generated piece and with nowhere to download one
 * from. BIB-55 asks the next catalog at the moment of the intake; this is the
 * same question asked backwards, over the Books that were already in the Shelf
 * when that was written.
 */
final class BackfillBookCovers extends Command
{
    protected $signature = 'books:backfill-covers
        {--pretend : cuenta los libros y las peticiones, sin encolar nada}';

    protected $description = 'Vuelve a pedirles a las fuentes las portadas que faltan.';

    public function handle(): int
    {
        $books = MissingCovers::books();

        if ($books->isEmpty()) {
            $this->components->info('Ningún libro quedó sin portada y con dónde buscarla.');

            return self::SUCCESS;
        }

        // Each Book is one request, and two when the first catalog has no image
        // of that edition — which is the case this exists for. What it is about
        // to spend gets said before it is spent, and `--pretend` is there to
        // say it without spending it.
        $this->components->info(sprintf(
            '%d %s sin portada: son entre %d y %d peticiones.',
            $books->count(),
            $books->count() === 1 ? 'libro quedó' : 'libros quedaron',
            $books->count(),
            $books->count() * count((array) config('sources.order', [])),
        ));

        foreach ($books as $book) {
            $this->line(sprintf('  %d — %s', $book->id, $book->title));
        }

        if ($this->option('pretend')) {
            $this->components->warn('No se encoló nada: corrió con --pretend.');

            return self::SUCCESS;
        }

        $queued = MissingCovers::queue($books);

        $this->components->info(sprintf(
            '%d %s a la cola.',
            $queued,
            $queued === 1 ? 'libro entró' : 'libros entraron',
        ));

        $this->components->warn(
            'Los que ninguna fuente tenga se quedan con su portada generada: no es un error, '
            .'es que esa edición no está ilustrada en ningún catálogo.'
        );

        return self::SUCCESS;
    }
}
