<?php

use App\Books\WaitingCards;
use App\Models\Quote;
use App\Quotes\Credits;
use App\Quotes\MissingImages;
use App\Quotes\ShareImages;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The screen of the images with which a Quote and a Book get shared (BIB-31,
 * BIB-54, ADR-009).
 *
 * It exists for one number: how many images are owed. Each Quote is two
 * renders, each Book is one, the credits of the renderer reset by the month,
 * and a day of photographing twenty books can invalidate a hundred Quotes — so
 * the cost of what is about to be asked for has to be visible **before** it is
 * asked for.
 *
 * And it is where the stopped queue shows. When the credits run out the queue
 * stops instead of retrying, which is the right thing and also the silent one:
 * without this screen, the images would simply stop appearing.
 *
 * Quotes and Books share the screen because they share the switch: what ran out
 * is the month, not one kind of drawing. Resuming for one and leaving the other
 * behind is what BIB-54 was.
 */
new #[Title('Imágenes para compartir')] class extends Component
{
    public ?string $notice = null;

    /** How many Quotes are still owed their two images. */
    #[Computed]
    public function pending(): int
    {
        return Quote::query()->awaitingImages()->count();
    }

    /**
     * How many Books are still owed the card they were queued for (BIB-54).
     *
     * Counted apart from the Quotes and not added to them: a Quote is two
     * drawings and a Book is one, so a single number would say the wrong cost.
     */
    #[Computed]
    public function pendingCards(): int
    {
        return WaitingCards::count();
    }

    /** The ones the renderer refused for a reason waiting does not fix. */
    #[Computed]
    public function failed(): int
    {
        return Quote::query()->withFailedImages()->count();
    }

    /**
     * The ones stored before anything queued a render for them: no image, no
     * job, and nothing that was ever going to come back for them (BIB-48).
     *
     * They are counted apart from what is pending because they are not waiting
     * for anything — and counting them with it would leave this screen saying
     * the queue is longer than it is.
     */
    #[Computed]
    public function neverAsked(): int
    {
        return MissingImages::count();
    }

    /** What the renderer said when it stopped, or nothing while it is running. */
    #[Computed]
    public function stopped(): ?string
    {
        return Credits::reason();
    }

    /** Whether there is a renderer with a key to draw with at all. */
    #[Computed]
    public function available(): bool
    {
        return app(ShareImages::class)->available();
    }

    /**
     * There is something to pay with again. Nothing here can find that out on
     * its own — asking costs a credit — so it is a person who says so.
     */
    public function resume(): void
    {
        Credits::restored();

        $quotes = Quote::query()->awaitingImages()->get();

        $quotes->each(fn (Quote $quote) => $quote->queueImages());

        // The Books stopped on the same switch and behind the same month. They
        // carry the mark of the queue with nothing drawn, and nothing else in
        // the application was ever going to come back for them (BIB-54).
        $books = WaitingCards::books();

        WaitingCards::queue($books);

        unset($this->pending, $this->pendingCards, $this->failed, $this->stopped);

        $this->notice = $this->resumed($quotes->count(), $books->count());
    }

    /** What the screen says about what just went back into the queue. */
    private function resumed(int $quotes, int $books): string
    {
        if ($quotes === 0 && $books === 0) {
            return 'No había nada esperando en la cola.';
        }

        $waiting = [];

        if ($quotes > 0) {
            $waiting[] = $quotes === 1 ? 'una cita' : $quotes.' citas';
        }

        if ($books > 0) {
            $waiting[] = $books === 1 ? 'un libro' : $books.' libros';
        }

        return Str::ucfirst(implode(' y ', $waiting))
            .($quotes + $books === 1 ? ' volvió' : ' volvieron')
            .' a la cola.';
    }

    /**
     * Puts the ones nobody ever asked about into the queue. It is the same
     * thing `quotes:backfill-images` does, reachable from the screen that
     * counts them: a screen that reports something and sends you to a terminal
     * to fix it is half a screen.
     */
    public function backfill(): void
    {
        $queued = MissingImages::queue();

        unset($this->pending, $this->neverAsked);

        $this->notice = match ($queued) {
            0 => 'No quedaba ninguna cita sin pedir sus imágenes.',
            1 => 'Una cita entró a la cola.',
            default => $queued.' citas entraron a la cola.',
        };
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout
        heading="Imágenes para compartir"
        subheading="Cada cita se dibuja en dos imágenes —la horizontal que arma la tarjeta del enlace y la vertical que se sube a mano— y cada libro, en una. Todas salen del mismo servicio y de los mismos créditos."
    >
        <div class="flex flex-col gap-8">
            @if (! $this->available)
                <flux:callout variant="warning" icon="photo" data-test="renderer-off">
                    <flux:callout.heading>No hay con qué dibujarlas</flux:callout.heading>

                    <flux:callout.text>
                        Falta la llave del servicio que convierte la tarjeta en imagen. Las citas y
                        los libros se guardan igual y quedan esperando en la cola.
                    </flux:callout.text>
                </flux:callout>
            @endif

            @if ($this->stopped)
                <flux:callout variant="warning" icon="photo" data-test="stopped">
                    <flux:callout.heading>La cola está detenida: se acabaron los créditos</flux:callout.heading>

                    <flux:callout.text>
                        El servicio dijo: {{ $this->stopped }}
                    </flux:callout.text>

                    <flux:callout.text>
                        Los créditos se reinician cada mes y no se acumulan. La cola no reintenta
                        sola, porque reintentar gastaría los del mes siguiente en el momento en
                        que entren.
                    </flux:callout.text>
                </flux:callout>

                <flux:button wire:click="resume" variant="primary" class="self-start" data-test="resume">
                    Ya hay créditos: retomar la cola
                </flux:button>
            @endif

            @if ($notice)
                <flux:text class="text-base" data-test="notice">{{ $notice }}</flux:text>
            @endif

            <div class="flex flex-col gap-2" data-test="pending">
                <flux:heading size="lg">Lo que falta</flux:heading>

                <flux:text data-test="pending-count">
                    {{ match (true) {
                        $this->pending === 0 && $this->neverAsked === 0 => 'Todas las citas tienen sus dos imágenes.',
                        $this->pending === 0 => 'Ninguna cita está esperando en la cola.',
                        $this->pending === 1 => 'Una cita espera sus imágenes: son dos dibujos.',
                        default => $this->pending.' citas esperan sus imágenes: son '.($this->pending * 2).' dibujos.',
                    } }}
                </flux:text>

                @if ($this->pendingCards)
                    <flux:text data-test="pending-cards-count">
                        {{ $this->pendingCards === 1
                            ? 'Un libro espera su tarjeta: es un dibujo.'
                            : $this->pendingCards.' libros esperan su tarjeta: son '.$this->pendingCards.' dibujos.' }}
                    </flux:text>
                @endif
            </div>

            @if ($this->neverAsked)
                <div class="flex flex-col gap-2" data-test="never-asked">
                    <flux:heading>Nunca se les pidió la imagen</flux:heading>

                    <flux:text data-test="never-asked-count">
                        {{ $this->neverAsked === 1
                            ? 'Una cita se guardó antes de que nada encolara su imagen. No hay nada en camino para ella.'
                            : $this->neverAsked.' citas se guardaron antes de que nada encolara sus imágenes. No hay nada en camino para ellas.' }}
                    </flux:text>

                    <flux:button wire:click="backfill" variant="primary" class="self-start" data-test="backfill">
                        {{ $this->neverAsked === 1
                            ? 'Pedir esa imagen: son 2 dibujos'
                            : 'Pedir esas imágenes: son '.($this->neverAsked * 2).' dibujos' }}
                    </flux:button>
                </div>
            @endif

            @if ($this->failed)
                <div class="flex flex-col gap-2" data-test="failed">
                    <flux:heading>La cola se rindió con algunas</flux:heading>

                    <flux:text data-test="failed-count">
                        {{ $this->failed === 1
                            ? 'Una cita quedó marcada con lo que dijo el servicio.'
                            : $this->failed.' citas quedaron marcadas con lo que dijo el servicio.' }}
                    </flux:text>
                </div>
            @endif
        </div>
    </x-pages::settings.layout>
</section>
