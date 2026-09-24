<?php

use App\Jobs\FindCover;
use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
 * La pantalla de las portadas que faltan (BIB-57). Existe por un número
 * —cuántos Libros muestran la pieza generada y todavía se les puede preguntar
 * por una imagen— y por el botón que hace lo mismo que
 * `books:backfill-covers`.
 */

beforeEach(function () {
    Queue::fake();

    $this->actingAs(User::factory()->create());
});

it('dice cuántos libros faltan y cuántas peticiones son', function () {
    Book::factory()->coverGenerated()->count(3)->create();

    Livewire::test('pages::settings.covers')
        ->assertSee('3 libros muestran la portada generada: son hasta 6 peticiones.');
});

it('sin ninguno pendiente lo dice en lugar de mostrar un cero', function () {
    Book::factory()->count(2)->create();

    Livewire::test('pages::settings.covers')
        ->assertSee('Ningún libro quedó sin portada y con dónde buscarla.')
        ->assertDontSee('0 libros');
});

it('cuenta aparte a los que ya se les bajó una imagen que no servía', function () {
    Book::factory()->coverGenerated()->create();
    Book::factory()->coverDownloadFailed()->count(2)->create();

    Livewire::test('pages::settings.covers')
        ->assertSee('A 2 de ellos ya se les había bajado una imagen que no servía.');
});

it('el botón los encola y lo dice', function () {
    Book::factory()->coverGenerated()->count(2)->create();

    Livewire::test('pages::settings.covers')
        ->call('backfill')
        ->assertSee('2 libros entraron a la cola.');

    Queue::assertPushed(FindCover::class, 2);
});

it('sin nada pendiente no ofrece el botón', function () {
    Book::factory()->count(2)->create();

    Livewire::test('pages::settings.covers')
        ->assertDontSeeHtml('wire:click="backfill"');
});

it('la pantalla pide sesión', function () {
    auth()->logout();

    $this->get('/settings/covers')->assertRedirect('/login');
});

it('con un solo libro lo dice en singular', function () {
    Book::factory()->coverGenerated()->create();

    Livewire::test('pages::settings.covers')
        ->assertSee('Un libro muestra la portada generada: son hasta 2 peticiones.')
        ->assertSee('Buscar esa portada');
});
