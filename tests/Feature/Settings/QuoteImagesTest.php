<?php

use App\Jobs\RenderQuoteImages;
use App\Models\Book;
use App\Models\Quote;
use App\Models\User;
use App\Quotes\Credits;
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

it('sin llave del servicio lo dice, en vez de prometer imágenes que nadie va a dibujar', function () {
    Livewire::test('pages::settings.quote-images')
        ->assertSee('No hay con qué dibujarlas');
});

it('la pantalla está detrás del login', function () {
    auth()->logout();

    $this->get(route('quote-images'))->assertRedirect(route('login'));
});
