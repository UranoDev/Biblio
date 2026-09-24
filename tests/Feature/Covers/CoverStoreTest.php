<?php

use App\Covers\CoverStore;
use App\Covers\InvalidImage;
use App\Enums\CoverOrigin;
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
    $book = Book::factory()->coverGenerated()->create();

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

    $book = Book::factory()->coverGenerated()->create();

    coverStore()->store($book, testImage(900, 1350));

    $original = getimagesizefromstring(Storage::disk()->get($book->cover_path));
    $thumbnail = getimagesizefromstring(Storage::disk()->get($book->cover_thumbnail_path));

    expect($original[0])->toBe(900)
        ->and($thumbnail[0])->toBe(300)
        ->and($thumbnail[1])->toBe(450);
});

it('guarda la original en el formato en el que llegó', function () {
    $book = Book::factory()->coverGenerated()->create();

    $this->store->store($book, testImage(300, 450, 'png'));

    expect($book->cover_path)->toEndWith('.png')
        ->and($book->cover_thumbnail_path)->toEndWith('-thumbnail.jpg');
});

it('reemplazal Cover anterior y borra sus archivos', function () {
    $book = Book::factory()->coverGenerated()->create();

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
    $book = Book::factory()->coverGenerated()->create();

    $file = UploadedFile::fake()->createWithContent('portada.jpg', testImage(400, 600));

    $this->store->storeUpload($book, $file);

    expect($book->hasCover())->toBeTrue();

    Storage::disk()->assertExists($book->cover_path);
});

it('rechaza lo que no es una imagen', function () {
    $book = Book::factory()->coverGenerated()->create();

    expect(fn () => $this->store->store($book, 'esto no es una imagen'))
        ->toThrow(InvalidImage::class);

    expect($book->fresh()->hasCover())->toBeFalse();
});

it('rechaza la imagen que pesa de más', function () {
    config()->set('covers.max_weight', 1024);

    $book = Book::factory()->coverGenerated()->create();

    expect(fn () => coverStore()->store($book, testImage(800, 1200)))
        ->toThrow(InvalidImage::class);
});

it('devuelve al Book a su Cover generado y borra los archivos', function () {
    $book = Book::factory()->coverGenerated()->create();

    $this->store->store($book, testImage(400, 600));

    $path = $book->cover_path;
    $thumbnail = $book->cover_thumbnail_path;

    $this->store->delete($book);

    expect($book->hasCover())->toBeFalse()
        ->and($book->fresh()->cover_thumbnail_path)->toBeNull()
        ->and($book->fresh()->cover_origin)->toBe(CoverOrigin::Generated);

    Storage::disk()->assertMissing($path);
    Storage::disk()->assertMissing($thumbnail);
});

it('escribe en el disco que diga FILESYSTEM_DISK, sin tocar el código', function () {
    config()->set('filesystems.disks.otro', ['driver' => 'local', 'root' => storage_path('framework/testing/otro')]);
    config()->set('filesystems.default', 'otro');

    Storage::fake('otro');

    $book = Book::factory()->coverGenerated()->create();

    coverStore()->store($book, testImage(400, 600));

    Storage::disk('otro')->assertExists($book->cover_path);
});

it('respetal directorio configurado', function () {
    config()->set('covers.directory', 'cubiertas');

    $book = Book::factory()->coverGenerated()->create();

    coverStore()->store($book, testImage(300, 450));

    expect($book->cover_path)->toStartWith('cubiertas/');
});

it('no guarda el pixel de 1×1 que Open Library devuelve cuando no tiene Cover', function () {
    $book = Book::factory()->coverGenerated()->create();

    expect(fn () => $this->store->store($book, recordedCover('open-library-no-cover.gif')))
        ->toThrow(InvalidImage::class);

    expect($book->fresh()->hasCover())->toBeFalse()
        ->and(Storage::disk()->allFiles())->toBeEmpty();
});

it('no guarda el marcador «image not available» de Google Books', function (string $file) {
    $book = Book::factory()->coverGenerated()->create();

    expect(fn () => $this->store->store($book, recordedCover($file)))
        ->toThrow(InvalidImage::class);

    expect($book->fresh()->hasCover())->toBeFalse()
        ->and(Storage::disk()->allFiles())->toBeEmpty();
})->with([
    'google-books-not-available.png',
    'google-books-not-available-128x198.jpg',
    'google-books-not-available-128x170.png',
]);

it('no guarda el logo de Internet Archive, que es lo que Open Library sirve cuando el archivo ya no está', function () {
    $book = Book::factory()->coverGenerated()->create();

    expect(fn () => $this->store->store($book, recordedCover('internet-archive-placeholder.jpg')))
        ->toThrow(InvalidImage::class);

    expect($book->fresh()->hasCover())->toBeFalse()
        ->and(Storage::disk()->allFiles())->toBeEmpty();
});

// La portada de «Show Me the Numbers»: fondo blanco casi entero, el título
// arriba, el autor abajo y una sola figura al centro. Mide 0.915 de color
// parejo, por encima del 0.90 que rechaza a un marcador, y aun así es una
// portada de verdad (BIB-53).
it('guarda una portada real de fondo minimalista', function () {
    $book = Book::factory()->coverGenerated()->create();

    $this->store->store($book, recordedCover('open-library-minimal-cover.jpg'));

    expect($book->hasCover())->toBeTrue()
        ->and($book->cover_origin)->toBe(CoverOrigin::Source);

    Storage::disk()->assertExists($book->cover_path);
});

it('no guarda una imagen más chica que el mínimo, aunque sea una portada de verdad', function () {
    $book = Book::factory()->coverGenerated()->create();

    expect(fn () => $this->store->store($book, testImage(40, 55)))
        ->toThrow(InvalidImage::class, 'La imagen mide 40 px de lado corto y el mínimo es 64.');

    expect(Storage::disk()->allFiles())->toBeEmpty();
});

it('mide el lado corto, no el largo', function () {
    $book = Book::factory()->coverGenerated()->create();

    expect(fn () => $this->store->store($book, testImage(40, 1200)))
        ->toThrow(InvalidImage::class);
});

it('sube el mínimo sin tocar código', function () {
    config()->set('covers.minimum_side', 600);

    $book = Book::factory()->coverGenerated()->create();

    expect(fn () => coverStore()->store($book, testImage(400, 600)))
        ->toThrow(InvalidImage::class);
});

it('no guarda una imagen de un solo color plano, aunque mida lo suficiente', function () {
    $book = Book::factory()->coverGenerated()->create();

    $canvas = imagecreatetruecolor(600, 900);
    imagefilledrectangle($canvas, 0, 0, 600, 900, (int) imagecolorallocate($canvas, 250, 250, 250));
    ob_start();
    imagepng($canvas);
    $blank = (string) ob_get_clean();
    imagedestroy($canvas);

    expect(fn () => $this->store->store($book, $blank))
        ->toThrow(InvalidImage::class);

    expect(Storage::disk()->allFiles())->toBeEmpty();
});

it('conserval Cover que ya teníal Book cuando lo que llega es un marcador', function () {
    $book = Book::factory()->coverGenerated()->create();

    $this->store->store($book, testImage(400, 600));
    $path = $book->cover_path;

    expect(fn () => $this->store->store($book, recordedCover('google-books-not-available.png')))
        ->toThrow(InvalidImage::class);

    expect($book->fresh()->cover_path)->toBe($path);

    Storage::disk()->assertExists($path);
});

it('anota de dónde vino cada Cover que guarda', function () {
    $fromSource = Book::factory()->coverGenerated()->create();
    $mine = Book::factory()->coverGenerated()->create();

    $this->store->store($fromSource, testImage(400, 600), CoverOrigin::Source);
    $this->store->store($mine, testImage(400, 600), CoverOrigin::Owner);

    expect($fromSource->cover_origin)->toBe(CoverOrigin::Source)
        ->and($mine->cover_origin)->toBe(CoverOrigin::Owner);
});

it('lo que trae una Fuente reemplaza al Cover generado', function () {
    $book = Book::factory()->coverGenerated()->create();

    $this->store->store($book, testImage(400, 600), CoverOrigin::Source);

    expect($book->hasGeneratedCover())->toBeFalse()
        ->and($book->hasCover())->toBeTrue();
});

it('lo que trae una Fuente no reemplaza la foto que tomó el dueño', function () {
    $book = Book::factory()->coverGenerated()->create();

    $this->store->store($book, testImage(400, 600), CoverOrigin::Owner);

    $mine = $book->cover_path;

    $this->store->store($book, testImage(1200, 1800), CoverOrigin::Source);

    expect($book->fresh()->cover_path)->toBe($mine)
        ->and($book->fresh()->cover_origin)->toBe(CoverOrigin::Owner);

    Storage::disk()->assertExists($mine);
});

it('otra foto del dueño sí reemplaza a la suya', function () {
    $book = Book::factory()->coverGenerated()->create();

    $this->store->store($book, testImage(400, 600), CoverOrigin::Owner);
    $first = $book->cover_path;

    $this->store->store($book, testImage(500, 750), CoverOrigin::Owner);

    expect($book->fresh()->cover_path)->not->toBe($first)
        ->and($book->fresh()->cover_origin)->toBe(CoverOrigin::Owner);

    Storage::disk()->assertMissing($first);
});
