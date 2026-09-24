<?php

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
