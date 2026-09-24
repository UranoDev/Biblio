<?php

use App\Enums\CoverSize;
use App\Enums\Status;
use App\Http\Controllers\ServeCover;
use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * A Book with its two versions of the Cover really written to the disk, and
 * the bytes of each one so the test can tell them apart.
 *
 * @return array{Book, string, string}
 */
function bookWithStoredCover(array $attributes = []): array
{
    $original = testImage(400, 600);
    $thumbnail = testImage(320, 480);

    $book = Book::factory()->create($attributes + [
        'cover_path' => 'covers/7/abc.jpg',
        'cover_thumbnail_path' => 'covers/7/abc-thumbnail.jpg',
    ]);

    Storage::disk()->put('covers/7/abc.jpg', $original);
    Storage::disk()->put('covers/7/abc-thumbnail.jpg', $thumbnail);

    return [$book, $original, $thumbnail];
}

beforeEach(function () {
    Storage::fake();
});

// This is the test that was missing: the whole bug was that the URL the screens
// drew had never been fetched by anything (BIB-32).
it('entrega la imagen del Cover cuando se le pide la URL por HTTP', function () {
    [$book, $original] = bookWithStoredCover();

    $response = $this->get($book->coverUrl());

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    expect($response->streamedContent())->toBe($original);
});

it('la miniatura y la original son dos direcciones y cada una entrega la suya', function () {
    [$book, $original, $thumbnail] = bookWithStoredCover();

    expect($book->coverUrl())->not->toBe($book->coverThumbnailUrl());

    expect($this->get($book->coverUrl())->streamedContent())->toBe($original)
        ->and($this->get($book->coverThumbnailUrl())->streamedContent())->toBe($thumbnail);
});

it('el Content-Type es el del archivo guardado y no siempre JPEG', function () {
    $book = Book::factory()->create(['cover_path' => 'covers/7/abc.png']);

    Storage::disk()->put('covers/7/abc.png', testImage(400, 600, 'png'));

    $this->get($book->coverUrlFor(CoverSize::Full))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('entrega la Portada de un Book visible sin sesión', function () {
    [$book] = bookWithStoredCover(['is_hidden' => false]);

    $this->assertGuest();

    $this->get($book->coverUrl())->assertOk();
    $this->get($book->coverThumbnailUrl())->assertOk();
});

it('entrega la Portada de un Book que está Por comprar, que también es pública', function () {
    [$book] = bookWithStoredCover();

    $book->forceFill(['status' => Status::Wanted])->save();

    $this->get($book->coverUrl())->assertOk();
});

// Un Libro Oculto se lleva su Portada consigo (ADR-003): servirla sería
// enseñar desde afuera justo lo que se sacó de la Vitrina.
it('no entrega la Portada de un Book Oculto a quien no ha iniciado sesión', function () {
    [$book] = bookWithStoredCover(['is_hidden' => true]);

    $this->get($book->coverUrl())->assertNotFound();
    $this->get($book->coverThumbnailUrl())->assertNotFound();
});

it('entrega la Portada de un Book Oculto a quien sí ha iniciado sesión', function () {
    [$book, $original] = bookWithStoredCover(['is_hidden' => true]);

    $this->actingAs(User::factory()->create());

    $response = $this->get($book->coverUrl());

    $response->assertOk();

    expect($response->streamedContent())->toBe($original);
});

it('cae en la original cuando no se guardó la miniatura', function () {
    $book = Book::factory()->create([
        'cover_path' => 'covers/7/abc.jpg',
        'cover_thumbnail_path' => null,
    ]);

    Storage::disk()->put('covers/7/abc.jpg', $original = testImage());

    expect($this->get($book->coverThumbnailUrl())->streamedContent())->toBe($original);
});

it('no tiene nada que servir del Book cuyo Cover es la pieza generada', function () {
    $book = Book::factory()->coverGenerated()->create();

    expect($book->coverUrl())->toBeNull();

    $this->get(route('cover', ['book' => $book, 'size' => CoverSize::Full]))->assertNotFound();
    $this->get(route('cover', ['book' => $book, 'size' => CoverSize::Thumbnail]))->assertNotFound();
});

it('contesta 404 cuando el archivo ya no está en el disco', function () {
    $book = Book::factory()->create(['cover_path' => 'covers/7/se-borro.jpg']);

    $this->get($book->coverUrlFor(CoverSize::Full))->assertNotFound();
});

it('contesta 404 a un tamaño que no existe', function () {
    [$book] = bookWithStoredCover();

    $this->get("/covers/{$book->getKey()}/enorme")->assertNotFound();
});

it('contesta 404 de un Book que no existe', function () {
    $this->get('/covers/9999/full')->assertNotFound();
});

// El manejo de estáticos de nginx se queda con todo lo que parece archivo antes
// de que llegue a PHP: por eso la dirección no lleva extensión.
it('arma una dirección sin extensión de archivo y fuera del disco', function () {
    [$book] = bookWithStoredCover();

    foreach ([$book->coverUrl(), $book->coverThumbnailUrl()] as $url) {
        $last = basename((string) parse_url($url, PHP_URL_PATH));

        expect($url)->not->toEndWith('.jpg')
            ->and($last)->not->toContain('.')
            ->and($url)->not->toContain('/storage/');
    }
});

// `/storage/{path}` la registra el framework al arrancar y desaparece con
// `route:cache`. Ésta vive en routes/web.php y se puede serializar, que es lo
// único que el cacheo exige.
it('registra la ruta de forma que sobreviva a que se cacheen las rutas', function () {
    $route = Route::getRoutes()->getByName('cover');

    expect($route)->not->toBeNull()
        ->and($route->getActionName())->toContain(ServeCover::class);

    $route->prepareForSerialization();

    expect(serialize($route))->toBeString();
});

it('deja que el navegador revalide en vez de quedarse con una Portada vieja', function () {
    [$book] = bookWithStoredCover();

    $first = $this->get($book->coverUrl());

    $first->assertOk()
        ->assertHeader('Cache-Control', 'no-cache, public');

    expect($first->headers->get('ETag'))->not->toBeNull();

    $this->withHeaders(['If-None-Match' => $first->headers->get('ETag')])
        ->get($book->coverUrl())
        ->assertStatus(304);
});

it('no deja que nada intermedio guarde la Portada de un Book Oculto', function () {
    [$book] = bookWithStoredCover(['is_hidden' => true]);

    $this->actingAs(User::factory()->create());

    $this->get($book->coverUrl())->assertHeader('Cache-Control', 'no-cache, private');
});
