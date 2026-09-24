<?php

namespace App\Console\Commands;

use App\Quotes\Credits;
use App\Quotes\MissingImages;
use App\Quotes\ShareImages;
use Illuminate\Console\Command;

/**
 * Queues the images of the Quotes that never got asked for one (BIB-48).
 *
 * The render is dispatched from the `saved` hook of the Quote (ADR-009), which
 * means every Quote typed in before that hook existed is owed two images with
 * nothing behind them: no job in the queue, no row in `failed_jobs`, and a
 * public page saying the image is on its way. Saving each one by hand is what
 * this replaces.
 *
 * It is run once over the Quotes that were already there, and it stays for the
 * day a batch gets missed again.
 */
final class BackfillQuoteImages extends Command
{
    protected $signature = 'quotes:backfill-images
        {--pretend : cuenta las citas y el costo, sin encolar nada}';

    protected $description = 'Encola las imágenes de las citas que nunca pasaron por la cola.';

    public function handle(): int
    {
        $quotes = MissingImages::quotes();

        if ($quotes->isEmpty()) {
            $this->components->info('Ninguna cita quedó sin pedir sus imágenes.');

            return self::SUCCESS;
        }

        // Each Quote is two drawings and the credits reset by the month
        // (ADR-009), so what this is about to cost gets said before it is
        // spent, and `--pretend` is there to say it without spending it.
        $this->components->info(sprintf(
            '%d %s sin pedir sus imágenes: son %d dibujos.',
            $quotes->count(),
            $quotes->count() === 1 ? 'cita quedó' : 'citas quedaron',
            $quotes->count() * 2,
        ));

        foreach ($quotes as $quote) {
            $this->line(sprintf('  %d — %s', $quote->id, $quote->book->title));
        }

        if ($this->option('pretend')) {
            $this->components->warn('No se encoló nada: corrió con --pretend.');

            return self::SUCCESS;
        }

        $queued = MissingImages::queue($quotes);

        $this->components->info(sprintf(
            '%d %s a la cola.',
            $queued,
            $queued === 1 ? 'cita entró' : 'citas entraron',
        ));

        $this->warnIfNothingWillDraw();

        return self::SUCCESS;
    }

    /**
     * Being in the queue is not the same as getting drawn. When the credits ran
     * out or there is no key, the job leaves without drawing and the Quotes
     * stay owed their images — which is right, and silent. The run that just
     * put them there is the moment to say so.
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

        if (! app(ShareImages::class)->available()) {
            $this->components->warn(
                'Falta la llave del servicio que dibuja las imágenes, así que las citas quedan esperando en la cola.'
            );
        }
    }
}
