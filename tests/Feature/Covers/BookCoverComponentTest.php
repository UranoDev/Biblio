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

it('sube un Cover a mano', function () {
    $book = Book::factory()->withoutCover()->create();

    Livewire::test('book-cover', ['book' => $book])
        ->set('file', UploadedFile::fake()->createWithContent('portada.jpg', testImage(400, 600)))
        ->call('save')
        ->assertHasNoErrors();

    $book->refresh();

    expect($book->hasCover())->toBeTrue();

    Storage::disk()->assertExists($book->cover_path);
    Storage::disk()->assertExists($book->cover_thumbnail_path);
});

it('reemplazal Cover que ya tenía', function () {
    $book = Book::factory()->withoutCover()->create();

    Livewire::test('book-cover', ['book' => $book])
        ->set('file', UploadedFile::fake()->createWithContent('primera.jpg', testImage(400, 600)))
        ->call('save');

    $first = $book->fresh()->cover_path;

    Livewire::test('book-cover', ['book' => $book->fresh()])
        ->set('file', UploadedFile::fake()->createWithContent('segunda.jpg', testImage(500, 750)))
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

it('avisa que no hay de dónde bajar el Cover', function () {
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

it('muestral título del Book en lugar de una imagen rota', function () {
    $book = Book::factory()->withoutCover()->create(['title' => 'El coronel no tiene quien le escriba']);

    $this->blade('<x-cover :book="$book" />', ['book' => $book])
        ->assertSee('El coronel no tiene quien le escriba')
        ->assertDontSee('<img', false);
});

it('muestra la miniatura cuando el Book tiene Cover', function () {
    $book = Book::factory()->create([
        'cover_path' => 'covers/9/abc.jpg',
        'cover_thumbnail_path' => 'covers/9/abc-thumbnail.jpg',
    ]);

    $this->blade('<x-cover :book="$book" />', ['book' => $book])
        ->assertSee('abc-thumbnail.jpg', false);

    $this->blade('<x-cover :book="$book" size="full" />', ['book' => $book])
        ->assertSee('covers/9/abc.jpg', false);
});

it('no acepta a mano el marcador que devuelve un catálogo', function () {
    $book = Book::factory()->withoutCover()->create();

    Livewire::test('book-cover', ['book' => $book])
        ->set('file', UploadedFile::fake()->createWithContent('portada.png', recordedCover('google-books-not-available.png')))
        ->call('save')
        ->assertHasErrors('file');

    expect($book->fresh()->hasCover())->toBeFalse();
});

it('dice por qué no sirve la imagen, en vez de decir que no se pudo leer', function () {
    $book = Book::factory()->withoutCover()->create();

    Livewire::test('book-cover', ['book' => $book])
        ->set('file', UploadedFile::fake()->createWithContent('miniatura.jpg', testImage(162, 198)))
        ->call('save')
        ->assertHasErrors(['file' => 'La imagen mide 162 px de lado corto y el mínimo es 300.']);
});
