<?php

use App\Enums\ShareFormat;
use App\Jobs\RenderQuoteImages;
use App\Models\Book;
use App\Models\Quote;
use App\Quotes\Credits;
use App\Quotes\MissingImages;
use App\Quotes\ShareImages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * El respaldo de lo que la cola nunca recibió (BIB-48).
 *
 * El render se despacha desde el hook `saved` de la Cita (ADR-009), así que
 * toda Cita escrita antes de que ese hook existiera quedó debiendo dos
 * imágenes sin nada detrás: ninguna fila en `jobs`, ninguna en `failed_jobs`,
 * y una página pública diciendo que la imagen viene en camino.
 */

beforeEach(function () {
    Storage::fake();
    Queue::fake();
});

/**
 * Una Cita como quedaron las tres del acervo: guardada, y con la marca de la
 * cola borrada a mano porque el código que la escribe todavía no existía.
 */
function quoteStoredBeforeTheQueue(?Book $book = null, string $text = 'Vine a Comala.'): Quote
{
    $quote = ($book ?? Book::factory()->create())->addQuote($text);

    DB::table('quotes')->where('id', $quote->id)->update([
        'images_queued_at' => null,
        'images_failed_at' => null,
        'images_failure' => null,
    ]);

    // Guardarla despachó un job, que es exactamente lo que no había pasado
    // entonces. La cola vuelve a empezar vacía, como estaba el día que alguien
    // abrió la página y leyó que la imagen venía en camino.
    Queue::fake();

    return $quote->fresh();
}

it('encola las imágenes de una cita que nunca pasó por la cola', function () {
    $quote = quoteStoredBeforeTheQueue();

    expect($quote->images_queued_at)->toBeNull();

    $this->artisan('quotes:backfill-images')
        ->expectsOutputToContain('1 cita quedó sin pedir sus imágenes: son 2 dibujos.')
        ->assertSuccessful();

    expect($quote->fresh()->images_queued_at)->not->toBeNull();

    Queue::assertPushed(RenderQuoteImages::class, 1);
});

it('correrlo dos veces no encola dos jobs para la misma cita', function () {
    quoteStoredBeforeTheQueue();

    $this->artisan('quotes:backfill-images')->assertSuccessful();

    Queue::assertPushed(RenderQuoteImages::class, 1);

    $this->artisan('quotes:backfill-images')
        ->expectsOutputToContain('Ninguna cita quedó sin pedir sus imágenes.')
        ->assertSuccessful();

    // La marca que acaba de escribir es justo lo que la deja fuera de la
    // segunda corrida: no hay un segundo dibujo que pagar.
    Queue::assertPushed(RenderQuoteImages::class, 1);
});

it('no toca la que sí está esperando en la cola', function () {
    // Guardada con el hook puesto: lleva su marca y tiene un job detrás.
    Book::factory()->create()->addQuote('Nada es para siempre.');

    Queue::assertPushed(RenderQuoteImages::class, 1);

    $this->artisan('quotes:backfill-images')
        ->expectsOutputToContain('Ninguna cita quedó sin pedir sus imágenes.')
        ->assertSuccessful();

    Queue::assertPushed(RenderQuoteImages::class, 1);
});

it('no toca la que el renderizador rechazó: eso se retoma desde la pantalla', function () {
    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    $quote->imagesFailed('The renderer has no API key.');

    $this->artisan('quotes:backfill-images')
        ->expectsOutputToContain('Ninguna cita quedó sin pedir sus imágenes.')
        ->assertSuccessful();

    expect($quote->fresh()->images_failed_at)->not->toBeNull();
});

it('deja fuera las citas de un libro oculto: dos dibujos que no se ven en ningún lado', function () {
    quoteStoredBeforeTheQueue(Book::factory()->hidden()->create());

    $this->artisan('quotes:backfill-images')
        ->expectsOutputToContain('Ninguna cita quedó sin pedir sus imágenes.')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('deja fuera la cita que no cabe en una imagen: no hay nada que dibujar', function () {
    quoteStoredBeforeTheQueue(
        text: str_repeat('La biblioteca de Babel es ilimitada y periódica. ', 20),
    );

    $this->artisan('quotes:backfill-images')
        ->expectsOutputToContain('Ninguna cita quedó sin pedir sus imágenes.')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('con --pretend dice el costo y no encola nada', function () {
    $book = Book::factory()->create(['title' => 'Pedro Páramo']);
    $quote = quoteStoredBeforeTheQueue($book);

    $this->artisan('quotes:backfill-images --pretend')
        ->expectsOutputToContain('1 cita quedó sin pedir sus imágenes: son 2 dibujos.')
        ->expectsOutputToContain('Pedro Páramo')
        ->expectsOutputToContain('No se encoló nada')
        ->assertSuccessful();

    expect($quote->fresh()->images_queued_at)->toBeNull();

    Queue::assertNothingPushed();
});

it('avisa que la cola está detenida, para que encolar no se lea como dibujar', function () {
    Credits::ranOut('No credits remaining this month.');

    quoteStoredBeforeTheQueue();

    $this->artisan('quotes:backfill-images')
        ->expectsOutputToContain('La cola está detenida por falta de créditos')
        ->assertSuccessful();
});

it('avisa que falta la llave del servicio', function () {
    // El renderizador de la suite no tiene llave: es el estado por omisión.
    quoteStoredBeforeTheQueue();

    $this->artisan('quotes:backfill-images')
        ->expectsOutputToContain('Falta la llave del servicio')
        ->assertSuccessful();
});

it('las tres citas del acervo terminan con su imagen al correr el comando', function () {
    $renderer = rendererDraws();

    $quotes = collect(['Vine a Comala.', 'Nada es para siempre.', 'El tiempo es un río.'])
        ->map(fn (string $text) => quoteStoredBeforeTheQueue(text: $text));

    // Nada hay en la cola y nada hubo nunca: es el estado del acervo existente.
    expect(MissingImages::count())->toBe(3);

    // La cola de la suite está falsa, así que el comando encola y los jobs se
    // corren acá, que es lo que pasa en producción con el worker andando.
    Queue::assertNothingPushed();

    $this->artisan('quotes:backfill-images')->assertSuccessful();

    Quote::query()->awaitingImages()->get()
        ->each(fn (Quote $quote) => (new RenderQuoteImages($quote))->handle(app(ShareImages::class)));

    expect($renderer->formats())->not->toBeEmpty();

    $quotes->each(function (Quote $quote): void {
        expect($quote->fresh()->hasImageFor(ShareFormat::downloaded()))->toBeTrue()
            ->and($quote->fresh()->images_queued_at)->toBeNull();
    });

    expect(MissingImages::count())->toBe(0);
});
