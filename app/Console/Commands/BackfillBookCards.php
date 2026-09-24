<?php

namespace App\Console\Commands;

use App\Books\MissingCards;
use App\Books\ShareImage;
use App\Quotes\Credits;
use Illuminate\Console\Command;

/**
 * Queues the card of the Books that never got asked for one (BIB-50).
 *
 * The render is dispatched from the `updated` hook of the Book, which means
 * every Book whose Cover was already in place when that hook was written is
 * owed a card with nothing behind it. It is run once over the Books that were
 * already there, and it stays for the day a batch gets missed again.
 */
final class BackfillBookCards extends Command
{
    protected $signature = 'books:backfill-cards
        {--pretend : cuenta los libros y el costo, sin encolar nada}';

    protected $description = 'Encola la tarjeta de los libros que nunca pasaron por la cola.';

    public function handle(): int
    {
        $books = MissingCards::books();

        if ($books->isEmpty()) {
            $this->components->info('Ningún libro quedó sin pedir su tarjeta.');

            return self::SUCCESS;
        }

        // Each Book is one drawing and the credits reset by the month
        // (ADR-009), so what this is about to cost gets said before it is
        // spent, and `--pretend` is there to say it without spending it.
        $this->components->info(sprintf(
            '%d %s sin pedir su tarjeta: son %d %s.',
            $books->count(),
            $books->count() === 1 ? 'libro quedó' : 'libros quedaron',
            $books->count(),
            $books->count() === 1 ? 'dibujo' : 'dibujos',
        ));

        foreach ($books as $book) {
            $this->line(sprintf('  %d — %s', $book->id, $book->title));
        }

        if ($this->option('pretend')) {
            $this->components->warn('No se encoló nada: corrió con --pretend.');

            return self::SUCCESS;
        }

        $queued = MissingCards::queue($books);

        $this->components->info(sprintf(
            '%d %s a la cola.',
            $queued,
            $queued === 1 ? 'libro entró' : 'libros entraron',
        ));

        $this->warnIfNothingWillDraw();

        return self::SUCCESS;
    }

    /**
     * Being in the queue is not the same as getting drawn. When the credits ran
     * out or there is no key, the job leaves without drawing and the Books stay
     * owed their card — which is right, and silent. The run that just put them
     * there is the moment to say so.
     */
    private function warnIfNothingWillDraw(): void
    {
        $stopped = Credits::reason();

        if ($stopped !== null) {
            $this->components->warn(
                'La cola está detenida por falta de créditos, así que nada se va a dibujar todavía. '
                .'El servicio dijo: '.$stopped
            );

            return;
        }

        if (! app(ShareImage::class)->available()) {
            $this->components->warn(
                'Falta la llave del servicio que dibuja las imágenes, así que los libros quedan esperando en la cola.'
            );
        }
    }
}
