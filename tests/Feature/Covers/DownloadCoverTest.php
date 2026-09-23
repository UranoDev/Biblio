<?php

use App\Covers\CoverStore;
use App\Jobs\DownloadCover;
use App\Models\Book;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Http::preventStrayRequests();
});

function download(Book $book): void
{
    (new DownloadCover($book))->handle(app(CoverStore::class));
}

it('deja anotada la URL de la Portada y pone la descarga en cola', function () {
    Queue::fake();

    $book = Book::factory()->withoutCover()->create();

    DownloadCover::forBook($book, 'https://covers.openlibrary.org/b/id/42-L.jpg');

    expect($book->fresh()->cover_source_url)->toBe('https://covers.openlibrary.org/b/id/42-L.jpg');

    Queue::assertPushed(DownloadCover::class);
});

it('no pone nada en cola cuando la Fuente no trajo Portada', function () {
    Queue::fake();

    $book = Book::factory()->withoutCover()->create();

    DownloadCover::forBook($book, null);

    expect($book->fresh()->cover_source_url)->toBeNull();

    Queue::assertNothingPushed();
});

it('descarga la Portada y la guarda en el disco configurado', function () {
    Http::fake(['covers.openlibrary.org/*' => Http::response(testImage(800, 1200), 200)]);

    $book = Book::factory()->coverPending()->create([
        'cover_source_url' => 'https://covers.openlibrary.org/b/id/42-L.jpg',
    ]);

    download($book);

    expect($book->fresh()->hasCover())->toBeTrue();

    Storage::disk()->assertExists($book->fresh()->cover_path);
    Storage::disk()->assertExists($book->fresh()->cover_thumbnail_path);
});

it('deja el Libro sin Portada cuando la imagen ya no está, y conserva de dónde bajarla', function () {
    Http::fake(['covers.openlibrary.org/*' => Http::response('', 404)]);

    $book = Book::factory()->coverPending()->create([
        'cover_source_url' => 'https://covers.openlibrary.org/b/id/42-L.jpg',
    ]);

    download($book);

    $book->refresh();

    expect($book->hasCover())->toBeFalse()
        ->and($book->cover_source_url)->toBe('https://covers.openlibrary.org/b/id/42-L.jpg')
        ->and($book->canRetryCover())->toBeTrue();
});

it('deja el Libro sin Portada cuando lo que llega no es una imagen', function () {
    Http::fake(['covers.openlibrary.org/*' => Http::response('<html>quota exceeded</html>', 200)]);

    $book = Book::factory()->coverPending()->create([
        'cover_source_url' => 'https://covers.openlibrary.org/b/id/42-L.jpg',
    ]);

    download($book);

    expect($book->fresh()->hasCover())->toBeFalse();

    expect(Storage::disk()->allFiles())->toBeEmpty();
});

it('falla para que la cola reintente cuando la Fuente se cae', function () {
    Http::fake(['covers.openlibrary.org/*' => Http::response('', 503)]);

    $book = Book::factory()->coverPending()->create([
        'cover_source_url' => 'https://covers.openlibrary.org/b/id/42-L.jpg',
    ]);

    expect(fn () => download($book))->toThrow(RuntimeException::class);
});

it('falla para que la cola reintente cuando la descarga tarda de más', function () {
    Http::fake(fn () => throw new ConnectionException('Operation timed out'));

    $book = Book::factory()->coverPending()->create([
        'cover_source_url' => 'https://covers.openlibrary.org/b/id/42-L.jpg',
    ]);

    expect(fn () => download($book))->toThrow(ConnectionException::class);

    expect($book->fresh()->canRetryCover())->toBeTrue();
});

it('reemplaza la Portada que ya tenía el Libro', function () {
    Http::fake(['covers.openlibrary.org/*' => Http::response(testImage(400, 600), 200)]);

    $book = Book::factory()->withoutCover()->create([
        'cover_source_url' => 'https://covers.openlibrary.org/b/id/42-L.jpg',
    ]);

    download($book);
    $first = $book->fresh()->cover_path;

    download($book->fresh());

    expect($book->fresh()->cover_path)->not->toBe($first);

    Storage::disk()->assertMissing($first);
});

it('se puede reintentar mientras quede de dónde bajarla', function () {
    Queue::fake();

    $book = Book::factory()->coverPending()->create();

    expect(DownloadCover::retry($book))->toBeTrue();

    Queue::assertPushed(DownloadCover::class);
});

it('no reintenta un Libro que no tiene de dónde bajar la Portada', function () {
    Queue::fake();

    $book = Book::factory()->withoutCover()->create();

    expect(DownloadCover::retry($book))->toBeFalse();

    Queue::assertNothingPushed();
});
