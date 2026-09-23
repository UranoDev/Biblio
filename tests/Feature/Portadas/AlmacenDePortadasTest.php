<?php

use App\Models\Libro;
use App\Portadas\AlmacenDePortadas;
use App\Portadas\ImagenInvalida;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();

    $this->almacen = app(AlmacenDePortadas::class);
});

/** El almacén se arma con la configuración vigente, así que se rearma al cambiarla. */
function almacenDePortadas(): AlmacenDePortadas
{
    app()->forgetInstance(AlmacenDePortadas::class);

    return app(AlmacenDePortadas::class);
}

it('guarda la original y la versión reducida, y el Libro apunta a las dos', function () {
    $libro = Libro::factory()->sinPortada()->create();

    $this->almacen->guardar($libro, imagenDePrueba(800, 1200));

    expect($libro->portada_ruta)->toStartWith('portadas/'.$libro->getKey().'/')
        ->and($libro->portada_ruta)->toEndWith('.jpg')
        ->and($libro->portada_miniatura_ruta)->toEndWith('-miniatura.jpg')
        ->and($libro->tienePortada())->toBeTrue();

    Storage::disk()->assertExists($libro->portada_ruta);
    Storage::disk()->assertExists($libro->portada_miniatura_ruta);

    expect($libro->fresh()->portada_ruta)->toBe($libro->portada_ruta);
});

it('reduce la miniatura al ancho configurado y deja la original como llegó', function () {
    config()->set('portadas.ancho_miniatura', 300);

    $libro = Libro::factory()->sinPortada()->create();

    almacenDePortadas()->guardar($libro, imagenDePrueba(900, 1350));

    $original = getimagesizefromstring(Storage::disk()->get($libro->portada_ruta));
    $miniatura = getimagesizefromstring(Storage::disk()->get($libro->portada_miniatura_ruta));

    expect($original[0])->toBe(900)
        ->and($miniatura[0])->toBe(300)
        ->and($miniatura[1])->toBe(450);
});

it('guarda la original en el formato en el que llegó', function () {
    $libro = Libro::factory()->sinPortada()->create();

    $this->almacen->guardar($libro, imagenDePrueba(300, 450, 'png'));

    expect($libro->portada_ruta)->toEndWith('.png')
        ->and($libro->portada_miniatura_ruta)->toEndWith('-miniatura.jpg');
});

it('reemplaza la Portada anterior y borra sus archivos', function () {
    $libro = Libro::factory()->sinPortada()->create();

    $this->almacen->guardar($libro, imagenDePrueba(400, 600));

    $anterior = $libro->portada_ruta;
    $anteriorMiniatura = $libro->portada_miniatura_ruta;

    $this->almacen->guardar($libro, imagenDePrueba(500, 750, 'png'));

    expect($libro->portada_ruta)->not->toBe($anterior);

    Storage::disk()->assertMissing($anterior);
    Storage::disk()->assertMissing($anteriorMiniatura);
    Storage::disk()->assertExists($libro->portada_ruta);
});

it('guarda la Portada que se sube a mano', function () {
    $libro = Libro::factory()->sinPortada()->create();

    $archivo = UploadedFile::fake()->createWithContent('portada.jpg', imagenDePrueba(400, 600));

    $this->almacen->guardarSubida($libro, $archivo);

    expect($libro->tienePortada())->toBeTrue();

    Storage::disk()->assertExists($libro->portada_ruta);
});

it('rechaza lo que no es una imagen', function () {
    $libro = Libro::factory()->sinPortada()->create();

    expect(fn () => $this->almacen->guardar($libro, 'esto no es una imagen'))
        ->toThrow(ImagenInvalida::class);

    expect($libro->fresh()->tienePortada())->toBeFalse();
});

it('rechaza la imagen que pesa de más', function () {
    config()->set('portadas.peso_maximo', 1024);

    $libro = Libro::factory()->sinPortada()->create();

    expect(fn () => almacenDePortadas()->guardar($libro, imagenDePrueba(800, 1200)))
        ->toThrow(ImagenInvalida::class);
});

it('deja al Libro sin Portada y borra sus archivos', function () {
    $libro = Libro::factory()->sinPortada()->create();

    $this->almacen->guardar($libro, imagenDePrueba(400, 600));

    $ruta = $libro->portada_ruta;
    $miniatura = $libro->portada_miniatura_ruta;

    $this->almacen->borrar($libro);

    expect($libro->tienePortada())->toBeFalse()
        ->and($libro->fresh()->portada_miniatura_ruta)->toBeNull();

    Storage::disk()->assertMissing($ruta);
    Storage::disk()->assertMissing($miniatura);
});

it('escribe en el disco que diga FILESYSTEM_DISK, sin tocar el código', function () {
    config()->set('filesystems.disks.otro', ['driver' => 'local', 'root' => storage_path('framework/testing/otro')]);
    config()->set('filesystems.default', 'otro');

    Storage::fake('otro');

    $libro = Libro::factory()->sinPortada()->create();

    almacenDePortadas()->guardar($libro, imagenDePrueba(400, 600));

    Storage::disk('otro')->assertExists($libro->portada_ruta);
});

it('respeta el directorio configurado', function () {
    config()->set('portadas.directorio', 'cubiertas');

    $libro = Libro::factory()->sinPortada()->create();

    almacenDePortadas()->guardar($libro, imagenDePrueba(300, 450));

    expect($libro->portada_ruta)->toStartWith('cubiertas/');
});
