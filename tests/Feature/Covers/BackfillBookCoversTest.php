<?php

use App\Enums\CoverOrigin;
use App\Jobs\FindCover;
use App\Models\Book;
use Illuminate\Support\Facades\Queue;

/*
 * El comando que vuelve a pedirles a las Fuentes las portadas que faltan
 * (BIB-57), con el mismo trato que `quotes:backfill-images` y
 * `books:backfill-cards`: dice cuánto va a costar antes de gastarlo, y
 * `--pretend` lo dice sin gastar nada.
 */

beforeEach(function () {
    Queue::fake();
});

it('encola a los que les falta la portada', function () {
    Book::factory()->coverGenerated()->count(2)->create();

    $this->artisan('books:backfill-covers')
        ->expectsOutputToContain('2 libros quedaron sin portada: son entre 2 y 4 peticiones.')
        ->expectsOutputToContain('2 libros entraron a la cola.')
        ->assertSuccessful();

    Queue::assertPushed(FindCover::class, 2);
});

it('con --pretend los cuenta y no encola nada', function () {
    Book::factory()->coverGenerated()->count(2)->create();

    $this->artisan('books:backfill-covers', ['--pretend' => true])
        ->expectsOutputToContain('2 libros quedaron sin portada: son entre 2 y 4 peticiones.')
        ->expectsOutputToContain('No se encoló nada')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('sin ninguno pendiente lo dice en lugar de encolar un cero', function () {
    Book::factory()->count(2)->create();

    $this->artisan('books:backfill-covers')
        ->expectsOutputToContain('Ningún libro quedó sin portada y con dónde buscarla.')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('corrido dos veces, la segunda no vuelve a encolar a los que ya tienen portada', function () {
    $books = Book::factory()->coverGenerated()->count(2)->create();

    $this->artisan('books:backfill-covers')->assertSuccessful();

    // Lo que la primera vuelta consiguió: la descarga llegó y se guardó.
    $books->each(fn (Book $book) => $book->forceFill([
        'cover_path' => 'covers/'.$book->id.'.jpg',
        'cover_origin' => CoverOrigin::Source,
    ])->saveQuietly());

    $this->artisan('books:backfill-covers')
        ->expectsOutputToContain('Ningún libro quedó sin portada y con dónde buscarla.')
        ->assertSuccessful();

    Queue::assertPushed(FindCover::class, 2);
});
