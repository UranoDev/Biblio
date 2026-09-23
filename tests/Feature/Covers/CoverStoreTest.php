<?php

use App\Covers\CoverStore;
use App\Covers\InvalidImage;
use App\Models\Book;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();

    $this->store = app(CoverStore::class);
});

/** The store is built with the current configuration, so it is rebuilt on changing it. */
function coverStore(): CoverStore
{
    app()->forgetInstance(CoverStore::class);

    return app(CoverStore::class);
}

it('guarda la original y la versión reducida, y el Book apunta a las dos', function () {
    $book = Book::factory()->withoutCover()->create();

    $this->store->store($book, testImage(800, 1200));

    expect($book->cover_path)->toStartWith('covers/'.$book->getKey().'/')
        ->and($book->cover_path)->toEndWith('.jpg')
        ->and($book->cover_thumbnail_path)->toEndWith('-thumbnail.jpg')
        ->and($book->hasCover())->toBeTrue();

    Storage::disk()->assertExists($book->cover_path);
    Storage::disk()->assertExists($book->cover_thumbnail_path);

    expect($book->fresh()->cover_path)->toBe($book->cover_path);
});

it('reduce la miniatura al ancho configurado y deja la original como llegó', function () {
    config()->set('covers.thumbnail_width', 300);

    $book = Book::factory()->withoutCover()->create();

    coverStore()->store($book, testImage(900, 1350));

    $original = getimagesizefromstring(Storage::disk()->get($book->cover_path));
    $thumbnail = getimagesizefromstring(Storage::disk()->get($book->cover_thumbnail_path));

    expect($original[0])->toBe(900)
        ->and($thumbnail[0])->toBe(300)
        ->and($thumbnail[1])->toBe(450);
});

it('guarda la original en el formato en el que llegó', function () {
    $book = Book::factory()->withoutCover()->create();

    $this->store->store($book, testImage(300, 450, 'png'));

    expect($book->cover_path)->toEndWith('.png')
        ->and($book->cover_thumbnail_path)->toEndWith('-thumbnail.jpg');
});

it('reemplazal Cover anterior y borra sus archivos', function () {
    $book = Book::factory()->withoutCover()->create();

    $this->store->store($book, testImage(400, 600));

    $previous = $book->cover_path;
    $previousThumbnail = $book->cover_thumbnail_path;

    $this->store->store($book, testImage(500, 750, 'png'));

    expect($book->cover_path)->not->toBe($previous);

    Storage::disk()->assertMissing($previous);
    Storage::disk()->assertMissing($previousThumbnail);
    Storage::disk()->assertExists($book->cover_path);
});

it('guardal Cover que se sube a mano', function () {
    $book = Book::factory()->withoutCover()->create();

    $file = UploadedFile::fake()->createWithContent('portada.jpg', testImage(400, 600));

    $this->store->storeUpload($book, $file);

    expect($book->hasCover())->toBeTrue();

    Storage::disk()->assertExists($book->cover_path);
});

it('rechaza lo que no es una imagen', function () {
    $book = Book::factory()->withoutCover()->create();

    expect(fn () => $this->store->store($book, 'esto no es una imagen'))
        ->toThrow(InvalidImage::class);

    expect($book->fresh()->hasCover())->toBeFalse();
});

it('rechaza la imagen que pesa de más', function () {
    config()->set('covers.max_weight', 1024);

    $book = Book::factory()->withoutCover()->create();

    expect(fn () => coverStore()->store($book, testImage(800, 1200)))
        ->toThrow(InvalidImage::class);
});

it('deja al Book sin Cover y borra sus archivos', function () {
    $book = Book::factory()->withoutCover()->create();

    $this->store->store($book, testImage(400, 600));

    $path = $book->cover_path;
    $thumbnail = $book->cover_thumbnail_path;

    $this->store->delete($book);

    expect($book->hasCover())->toBeFalse()
        ->and($book->fresh()->cover_thumbnail_path)->toBeNull();

    Storage::disk()->assertMissing($path);
    Storage::disk()->assertMissing($thumbnail);
});

it('escribe en el disco que diga FILESYSTEM_DISK, sin tocar el código', function () {
    config()->set('filesystems.disks.otro', ['driver' => 'local', 'root' => storage_path('framework/testing/otro')]);
    config()->set('filesystems.default', 'otro');

    Storage::fake('otro');

    $book = Book::factory()->withoutCover()->create();

    coverStore()->store($book, testImage(400, 600));

    Storage::disk('otro')->assertExists($book->cover_path);
});

it('respetal directorio configurado', function () {
    config()->set('covers.directory', 'cubiertas');

    $book = Book::factory()->withoutCover()->create();

    coverStore()->store($book, testImage(300, 450));

    expect($book->cover_path)->toStartWith('cubiertas/');
});
