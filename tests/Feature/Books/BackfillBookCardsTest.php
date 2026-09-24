<?php

use App\Books\MissingCards;
use App\Jobs\RenderBookImage;
use App\Models\Book;
use App\Quotes\Credits;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * Los libros que ya estaban cuando se escribió el enganche que dibuja la
 * tarjeta (BIB-50). Nada en la aplicación iba a volver por ellos: no hay
 * trabajo en la cola, no hay fila en `failed_jobs`, y el enlace sale con la
 * tarjeta chica.
 */

beforeEach(function () {
    Storage::fake();
});

it('encuentra los libros con Portada que nunca pasaron por la cola', function () {
    Queue::fake();

    $waiting = Book::factory()->create();
    $waiting->forceFill(['share_image_queued_at' => null])->saveQuietly();

    $withoutCover = Book::factory()->coverGenerated()->create();
    $withoutCover->forceFill(['share_image_queued_at' => null])->saveQuietly();

    expect(MissingCards::books()->pluck('id')->all())->toBe([$waiting->id]);
});

it('deja fuera los Ocultos, que no se muestran en ninguna parte', function () {
    Queue::fake();

    $hidden = Book::factory()->create(['is_hidden' => true]);
    $hidden->forceFill(['share_image_queued_at' => null])->saveQuietly();

    expect(MissingCards::count())->toBe(0);
});

it('deja fuera el que ya tiene tarjeta y el que ya está esperando', function () {
    Queue::fake();

    $drawn = Book::factory()->create();
    $drawn->forceFill([
        'share_image_path' => 'books/1/card-abc.png',
        'share_image_fingerprint' => str_repeat('a', 32),
        'share_image_queued_at' => null,
    ])->saveQuietly();

    $queued = Book::factory()->create();
    $queued->forceFill(['share_image_queued_at' => now()])->saveQuietly();

    expect(MissingCards::count())->toBe(0);
});

it('los encola y no los encola dos veces', function () {
    Queue::fake();

    $book = Book::factory()->create();
    $book->forceFill(['share_image_queued_at' => null])->saveQuietly();

    expect(MissingCards::queue())->toBe(1);

    Queue::assertPushed(RenderBookImage::class, 1);

    expect(MissingCards::count())->toBe(0);
});

it('el comando dice cuántos dibujos va a costar y con --pretend no encola nada', function () {
    Queue::fake();

    $book = Book::factory()->create(['title' => 'Pedro Páramo']);
    $book->forceFill(['share_image_queued_at' => null])->saveQuietly();

    $this->artisan('books:backfill-cards', ['--pretend' => true])
        ->expectsOutputToContain('1 libro quedó sin pedir su tarjeta: son 1 dibujo.')
        ->expectsOutputToContain('Pedro Páramo')
        ->assertSuccessful();

    Queue::assertNotPushed(RenderBookImage::class);

    expect($book->fresh()->share_image_queued_at)->toBeNull();
});

it('el comando avisa que la cola está detenida por falta de créditos', function () {
    Queue::fake();

    Credits::ranOut('No credits remaining this month.');

    $book = Book::factory()->create();
    $book->forceFill(['share_image_queued_at' => null])->saveQuietly();

    $this->artisan('books:backfill-cards')
        ->expectsOutputToContain('No credits remaining this month.')
        ->assertSuccessful();

    expect($book->fresh()->share_image_queued_at)->not->toBeNull();
});

it('no dice nada raro cuando no quedó ninguno', function () {
    $this->artisan('books:backfill-cards')
        ->expectsOutputToContain('Ningún libro quedó sin pedir su tarjeta.')
        ->assertSuccessful();
});
