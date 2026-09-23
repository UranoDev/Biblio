<?php

use App\Jobs\DescargarPortada;
use App\Models\Libro;
use App\Portadas\AlmacenDePortadas;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Http::preventStrayRequests();
});

function descargar(Libro $libro): void
{
    (new DescargarPortada($libro))->handle(app(AlmacenDePortadas::class));
}

it('deja anotada la URL de la Portada y pone la descarga en cola', function () {
    Queue::fake();

    $libro = Libro::factory()->sinPortada()->create();

    DescargarPortada::paraElLibro($libro, 'https://covers.openlibrary.org/b/id/42-L.jpg');

    expect($libro->fresh()->portada_origen_url)->toBe('https://covers.openlibrary.org/b/id/42-L.jpg');

    Queue::assertPushed(DescargarPortada::class);
});

it('no pone nada en cola cuando la Fuente no trajo Portada', function () {
    Queue::fake();

    $libro = Libro::factory()->sinPortada()->create();

    DescargarPortada::paraElLibro($libro, null);

    expect($libro->fresh()->portada_origen_url)->toBeNull();

    Queue::assertNothingPushed();
});

it('descarga la Portada y la guarda en el disco configurado', function () {
    Http::fake(['covers.openlibrary.org/*' => Http::response(imagenDePrueba(800, 1200), 200)]);

    $libro = Libro::factory()->portadaPendiente()->create([
        'portada_origen_url' => 'https://covers.openlibrary.org/b/id/42-L.jpg',
    ]);

    descargar($libro);

    expect($libro->fresh()->tienePortada())->toBeTrue();

    Storage::disk()->assertExists($libro->fresh()->portada_ruta);
    Storage::disk()->assertExists($libro->fresh()->portada_miniatura_ruta);
});

it('deja el Libro sin Portada cuando la imagen ya no está, y conserva de dónde bajarla', function () {
    Http::fake(['covers.openlibrary.org/*' => Http::response('', 404)]);

    $libro = Libro::factory()->portadaPendiente()->create([
        'portada_origen_url' => 'https://covers.openlibrary.org/b/id/42-L.jpg',
    ]);

    descargar($libro);

    $libro->refresh();

    expect($libro->tienePortada())->toBeFalse()
        ->and($libro->portada_origen_url)->toBe('https://covers.openlibrary.org/b/id/42-L.jpg')
        ->and($libro->puedeReintentarLaPortada())->toBeTrue();
});

it('deja el Libro sin Portada cuando lo que llega no es una imagen', function () {
    Http::fake(['covers.openlibrary.org/*' => Http::response('<html>quota exceeded</html>', 200)]);

    $libro = Libro::factory()->portadaPendiente()->create([
        'portada_origen_url' => 'https://covers.openlibrary.org/b/id/42-L.jpg',
    ]);

    descargar($libro);

    expect($libro->fresh()->tienePortada())->toBeFalse();

    expect(Storage::disk()->allFiles())->toBeEmpty();
});

it('falla para que la cola reintente cuando la Fuente se cae', function () {
    Http::fake(['covers.openlibrary.org/*' => Http::response('', 503)]);

    $libro = Libro::factory()->portadaPendiente()->create([
        'portada_origen_url' => 'https://covers.openlibrary.org/b/id/42-L.jpg',
    ]);

    expect(fn () => descargar($libro))->toThrow(RuntimeException::class);
});

it('falla para que la cola reintente cuando la descarga tarda de más', function () {
    Http::fake(fn () => throw new ConnectionException('Operation timed out'));

    $libro = Libro::factory()->portadaPendiente()->create([
        'portada_origen_url' => 'https://covers.openlibrary.org/b/id/42-L.jpg',
    ]);

    expect(fn () => descargar($libro))->toThrow(ConnectionException::class);

    expect($libro->fresh()->puedeReintentarLaPortada())->toBeTrue();
});

it('reemplaza la Portada que ya tenía el Libro', function () {
    Http::fake(['covers.openlibrary.org/*' => Http::response(imagenDePrueba(400, 600), 200)]);

    $libro = Libro::factory()->sinPortada()->create([
        'portada_origen_url' => 'https://covers.openlibrary.org/b/id/42-L.jpg',
    ]);

    descargar($libro);
    $primera = $libro->fresh()->portada_ruta;

    descargar($libro->fresh());

    expect($libro->fresh()->portada_ruta)->not->toBe($primera);

    Storage::disk()->assertMissing($primera);
});

it('se puede reintentar mientras quede de dónde bajarla', function () {
    Queue::fake();

    $libro = Libro::factory()->portadaPendiente()->create();

    expect(DescargarPortada::reintentar($libro))->toBeTrue();

    Queue::assertPushed(DescargarPortada::class);
});

it('no reintenta un Libro que no tiene de dónde bajar la Portada', function () {
    Queue::fake();

    $libro = Libro::factory()->sinPortada()->create();

    expect(DescargarPortada::reintentar($libro))->toBeFalse();

    Queue::assertNothingPushed();
});
