<?php

use App\Jobs\RenderBookImage;
use App\Jobs\RenderQuoteImages;
use App\Models\Book;
use App\Models\Quote;
use App\Models\User;
use App\Quotes\Credits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
 * La pantalla de las imágenes con que se comparte una cita (BIB-31).
 *
 * Existe por un número —cuántas faltan, que son dos dibujos cada una— y por un
 * aviso: cuando se acaban los créditos la cola se detiene, y detenerse es lo
 * correcto y también lo silencioso.
 */

beforeEach(function () {
    Queue::fake();

    $this->actingAs(User::factory()->create());
});

it('dice cuántas imágenes están pendientes, contando los dos formatos', function () {
    Book::factory()->create()->addQuote('Vine a Comala.');
    Book::factory()->create()->addQuote('Nada es para siempre.');

    Livewire::test('pages::settings.quote-images')
        ->assertSee('2 citas esperan sus imágenes: son 4 dibujos.');
});

it('sin nada pendiente lo dice en lugar de mostrar un cero', function () {
    Quote::query()->delete();

    Livewire::test('pages::settings.quote-images')
        ->assertSee('Todas las citas tienen sus dos imágenes.')
        ->assertDontSee('0 citas');
});

it('la cola detenida por falta de créditos queda a la vista', function () {
    Credits::ranOut('No credits remaining this month.');

    Livewire::test('pages::settings.quote-images')
        ->assertSee('La cola está detenida')
        ->assertSee('No credits remaining this month.');
});

it('retomar la cola devuelve a ella las citas que esperaban', function () {
    Credits::ranOut('No credits remaining this month.');

    Book::factory()->create()->addQuote('Vine a Comala.');

    Livewire::test('pages::settings.quote-images')
        ->call('resume')
        ->assertSee('Una cita volvió a la cola.');

    expect(Credits::areOut())->toBeFalse();

    Queue::assertPushed(RenderQuoteImages::class);
});

/*
 * Las citas que nadie encoló nunca (BIB-48). No están esperando —no hay nada
 * en camino para ellas—, así que se cuentan aparte de la cola y se piden desde
 * acá.
 */

it('cuenta aparte las citas a las que nunca se les pidió la imagen', function () {
    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    DB::table('quotes')->where('id', $quote->id)->update(['images_queued_at' => null]);

    Livewire::test('pages::settings.quote-images')
        ->assertSee('Una cita se guardó antes de que nada encolara su imagen')
        ->assertSee('Pedir esa imagen: son 2 dibujos')
        // Y la cola queda dicha por lo que tiene, que es nada.
        ->assertSee('Ninguna cita está esperando en la cola.')
        ->assertDontSee('Todas las citas tienen sus dos imágenes.');
});

it('pedirlas desde la pantalla las manda a la cola', function () {
    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    DB::table('quotes')->where('id', $quote->id)->update(['images_queued_at' => null]);

    Livewire::test('pages::settings.quote-images')
        ->call('backfill')
        ->assertSee('Una cita entró a la cola.')
        // Ya no queda ninguna sin pedir: el bloque se va.
        ->assertDontSee('Nunca se les pidió la imagen');

    expect($quote->fresh()->images_queued_at)->not->toBeNull();

    Queue::assertPushed(RenderQuoteImages::class);
});

it('sin llave del servicio lo dice, en vez de prometer imágenes que nadie va a dibujar', function () {
    Livewire::test('pages::settings.quote-images')
        ->assertSee('No hay con qué dibujarlas');
});

it('la pantalla está detrás del login', function () {
    auth()->logout();

    $this->get(route('quote-images'))->assertRedirect(route('login'));
});

/*
 * Los libros que se quedaron a medias (BIB-54). Se les pidió la tarjeta, la
 * cola se detuvo por falta de créditos y nadie volvió por ellos: el botón de
 * retomar es lo único que los alcanza.
 */

it('cuenta los libros que quedaron esperando su tarjeta', function () {
    Book::factory()->count(2)->create()
        ->each(fn (Book $book) => $book->forceFill(['share_image_queued_at' => now()])->saveQuietly());

    Livewire::test('pages::settings.quote-images')
        ->assertSee('2 libros esperan su tarjeta: son 2 dibujos.');
});

it('retomar la cola devuelve a ella los libros que esperaban, no solo las citas', function () {
    Credits::ranOut('No credits remaining this month.');

    $book = Book::factory()->create();
    $book->forceFill(['share_image_queued_at' => now()])->saveQuietly();

    $quote = Book::factory()->create()->addQuote('Vine a Comala.');
    $quote->book->forceFill(['share_image_queued_at' => null])->saveQuietly();

    Livewire::test('pages::settings.quote-images')
        ->call('resume')
        ->assertSee('Una cita y un libro volvieron a la cola.');

    expect(Credits::areOut())->toBeFalse()
        ->and($book->fresh()->share_image_queued_at)->not->toBeNull();

    Queue::assertPushed(RenderQuoteImages::class);
    Queue::assertPushed(RenderBookImage::class);
});

it('retomar sin nada esperando no promete que algo volvió', function () {
    Credits::ranOut('No credits remaining this month.');

    Quote::query()->delete();

    Livewire::test('pages::settings.quote-images')
        ->call('resume')
        ->assertSee('No había nada esperando en la cola.');

    Queue::assertNotPushed(RenderBookImage::class);
});

it('el libro que la cola rechazó no vuelve con el que solo esperaba', function () {
    Credits::ranOut('No credits remaining this month.');

    Quote::query()->delete();

    $refused = Book::factory()->create();
    $refused->forceFill([
        'share_image_queued_at' => now(),
        'share_image_failed_at' => now(),
        'share_image_failure' => 'La llave no sirve.',
    ])->saveQuietly();

    Livewire::test('pages::settings.quote-images')
        ->call('resume')
        ->assertSee('No había nada esperando en la cola.');

    Queue::assertNotPushed(RenderBookImage::class);
});

it('el libro que nunca pasó por la cola no se cuenta con los que esperan', function () {
    Quote::query()->delete();

    // Sin la marca de la cola es de MissingCards, no de acá (BIB-50).
    Book::factory()->create()
        ->forceFill(['share_image_queued_at' => null])->saveQuietly();

    Livewire::test('pages::settings.quote-images')
        ->assertDontSee('espera su tarjeta')
        ->assertDontSee('esperan su tarjeta');
});

it('el libro oculto no gasta un dibujo al retomar', function () {
    Credits::ranOut('No credits remaining this month.');

    Quote::query()->delete();

    Book::factory()->hidden()->create()
        ->forceFill(['share_image_queued_at' => now()])->saveQuietly();

    Livewire::test('pages::settings.quote-images')
        ->call('resume')
        ->assertSee('No había nada esperando en la cola.');

    Queue::assertNotPushed(RenderBookImage::class);
});
