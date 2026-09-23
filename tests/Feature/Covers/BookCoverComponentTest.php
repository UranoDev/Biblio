<?php

use App\Jobs\DownloadCover;
use App\Models\Book;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake();
});

it('sube una Portada a mano', function () {
    $book = Book::factory()->withoutCover()->create();

    Livewire::test('book-cover', ['book' => $book])
        ->set('file', UploadedFile::fake()->image('portada.jpg', 400, 600))
        ->call('save')
        ->assertHasNoErrors();

    $book->refresh();

    expect($book->hasCover())->toBeTrue();

    Storage::disk()->assertExists($book->cover_path);
    Storage::disk()->assertExists($book->cover_thumbnail_path);
});

it('reemplaza la Portada que ya tenía', function () {
    $book = Book::factory()->withoutCover()->create();

    Livewire::test('book-cover', ['book' => $book])
        ->set('file', UploadedFile::fake()->image('primera.jpg', 400, 600))
        ->call('save');

    $first = $book->fresh()->cover_path;

    Livewire::test('book-cover', ['book' => $book->fresh()])
        ->set('file', UploadedFile::fake()->image('segunda.jpg', 500, 750))
        ->call('save');

    expect($book->fresh()->cover_path)->not->toBe($first);

    Storage::disk()->assertMissing($first);
});

it('no acepta un archivo que no es imagen', function () {
    $book = Book::factory()->withoutCover()->create();

    Livewire::test('book-cover', ['book' => $book])
        ->set('file', UploadedFile::fake()->create('contrato.pdf', 12, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('file');

    expect($book->fresh()->hasCover())->toBeFalse();
});

it('pone la descarga en cola otra vez cuando falló', function () {
    Queue::fake();

    $book = Book::factory()->coverPending()->create();

    Livewire::test('book-cover', ['book' => $book])
        ->call('retry')
        ->assertSet('notice', 'La descarga de la Portada quedó en cola.');

    Queue::assertPushed(DownloadCover::class);
});

it('avisa que no hay de dónde bajar la Portada', function () {
    Queue::fake();

    $book = Book::factory()->withoutCover()->create();

    Livewire::test('book-cover', ['book' => $book])
        ->call('retry')
        ->assertSet('notice', 'Este Libro no tiene de dónde bajar la Portada.');

    Queue::assertNothingPushed();
});

it('ofrece reintentar sólo cuando la descarga puede repetirse', function () {
    Livewire::test('book-cover', ['book' => Book::factory()->coverPending()->create()])
        ->assertSee('Reintentar la descarga')
        ->assertSee('Subir una Portada');

    Livewire::test('book-cover', ['book' => Book::factory()->withoutCover()->create()])
        ->assertDontSee('Reintentar la descarga')
        ->assertSee('Este Libro no tiene Portada');
});

it('muestra el título del Libro en lugar de una imagen rota', function () {
    $book = Book::factory()->withoutCover()->create(['title' => 'El coronel no tiene quien le escriba']);

    $this->blade('<x-cover :book="$book" />', ['book' => $book])
        ->assertSee('El coronel no tiene quien le escriba')
        ->assertDontSee('<img', false);
});

it('muestra la miniatura cuando el Libro tiene Portada', function () {
    $book = Book::factory()->create([
        'cover_path' => 'covers/9/abc.jpg',
        'cover_thumbnail_path' => 'covers/9/abc-thumbnail.jpg',
    ]);

    $this->blade('<x-cover :book="$book" />', ['book' => $book])
        ->assertSee('abc-thumbnail.jpg', false);

    $this->blade('<x-cover :book="$book" size="full" />', ['book' => $book])
        ->assertSee('covers/9/abc.jpg', false);
});
