<?php

use App\Covers\CoverStore;
use App\Jobs\DownloadCover;
use App\Models\Book;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

it('deja anotada la URL del Cover y pone la descarga en cola', function () {
    Queue::fake();

    $book = Book::factory()->withoutCover()->create();

    DownloadCover::forBook($book, 'https://covers.openlibrary.org/b/id/42-L.jpg');

    expect($book->fresh()->cover_source_url)->toBe('https://covers.openlibrary.org/b/id/42-L.jpg');

    Queue::assertPushed(DownloadCover::class);
});

it('no pone nada en cola cuando la Source no trajo Cover', function () {
    Queue::fake();

    $book = Book::factory()->withoutCover()->create();

    DownloadCover::forBook($book, null);

    expect($book->fresh()->cover_source_url)->toBeNull();

    Queue::assertNothingPushed();
});

it('descargal Cover y la guarda en el disco configurado', function () {
    Http::fake(['covers.openlibrary.org/*' => Http::response(testImage(800, 1200), 200)]);

    $book = Book::factory()->coverPending()->create([
        'cover_source_url' => 'https://covers.openlibrary.org/b/id/42-L.jpg',
    ]);

    download($book);

    expect($book->fresh()->hasCover())->toBeTrue();

    Storage::disk()->assertExists($book->fresh()->cover_path);
    Storage::disk()->assertExists($book->fresh()->cover_thumbnail_path);
});

it('dejal Book sin Cover cuando la imagen ya no está, y conserva de dónde bajarla', function () {
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

it('dejal Book sin Cover cuando lo que llega no es una imagen', function () {
    Http::fake(['covers.openlibrary.org/*' => Http::response('<html>quota exceeded</html>', 200)]);

    $book = Book::factory()->coverPending()->create([
        'cover_source_url' => 'https://covers.openlibrary.org/b/id/42-L.jpg',
    ]);

    download($book);

    expect($book->fresh()->hasCover())->toBeFalse();

    expect(Storage::disk()->allFiles())->toBeEmpty();
});

it('falla para que la cola reintente cuando la Source se cae', function () {
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

it('reemplazal Cover que ya teníal Book', function () {
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

it('no reintenta un Book que no tiene de dónde bajar el Cover', function () {
    Queue::fake();

    $book = Book::factory()->withoutCover()->create();

    expect(DownloadCover::retry($book))->toBeFalse();

    Queue::assertNothingPushed();
});

it('dejal Book sin Cover cuando Open Library devuelve su pixel de 1×1', function () {
    Http::fake([
        'covers.openlibrary.org/*' => Http::response(recordedCover('open-library-no-cover.gif'), 200, ['Content-Type' => 'image/gif']),
    ]);

    $book = Book::factory()->coverPending()->create([
        'cover_source_url' => 'https://covers.openlibrary.org/b/isbn/9788437604176-L.jpg',
    ]);

    download($book);

    $book->refresh();

    expect($book->hasCover())->toBeFalse()
        ->and($book->cover_source_url)->toBe('https://covers.openlibrary.org/b/isbn/9788437604176-L.jpg')
        ->and($book->canRetryCover())->toBeTrue()
        ->and(Storage::disk()->allFiles())->toBeEmpty();
});

it('dejal Book sin Cover cuando Google Books devuelve su «image not available»', function (string $file) {
    Http::fake(['books.google.com/*' => Http::response(recordedCover($file), 200, ['Content-Type' => 'image/png'])]);

    $book = Book::factory()->coverPending()->create([
        'cover_source_url' => 'https://books.google.com/books/content?id=pEjU0QEACAAJ&printsec=frontcover&img=1&zoom=1',
    ]);

    download($book);

    $book->refresh();

    expect($book->hasCover())->toBeFalse()
        ->and($book->cover_source_url)->toContain('books.google.com')
        ->and($book->canRetryCover())->toBeTrue()
        ->and(Storage::disk()->allFiles())->toBeEmpty();
})->with([
    'google-books-not-available.png',
    'google-books-not-available-128x198.jpg',
    'google-books-not-available-128x170.png',
]);

it('distingue en la bitácora el marcador de una descarga que falló', function () {
    Log::spy();

    Http::fake(['books.google.com/*' => Http::response(recordedCover('google-books-not-available.png'), 200)]);

    $book = Book::factory()->coverPending()->create([
        'cover_source_url' => 'https://books.google.com/books/content?id=pEjU0QEACAAJ',
    ]);

    download($book);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'What the Source returned is not a Cover.'
            && $context['book'] === $book->getKey()
            && str_contains($context['reason'], 'image not available'));
});

it('no confunde una descarga que falló con un marcador', function () {
    Log::spy();

    Http::fake(['covers.openlibrary.org/*' => Http::response('', 404)]);

    $book = Book::factory()->coverPending()->create([
        'cover_source_url' => 'https://covers.openlibrary.org/b/id/42-L.jpg',
    ]);

    download($book);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message): bool => $message === 'The Cover could not be downloaded.');
});
