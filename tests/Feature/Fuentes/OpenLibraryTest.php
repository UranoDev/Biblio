<?php

use App\Dominio\Isbn;
use App\Enums\Fuente;
use App\Enums\Procedencia;
use App\Fuentes\OpenLibrary;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function openLibrary(): OpenLibrary
{
    return new OpenLibrary(
        url: 'https://openlibrary.org',
        portadasUrl: 'https://covers.openlibrary.org',
        timeout: 10,
    );
}

/** @param  array<string, mixed>  $extra */
function fingirOpenLibrary(array $extra = []): void
{
    // El `+` deja las respuestas del caso por delante de las de base: Http
    // resuelve el primer patrón que coincide.
    Http::fake($extra + [
        'openlibrary.org/isbn/*' => Http::response(grabada('open-library-edicion')),
        'openlibrary.org/works/*' => Http::response(grabada('open-library-obra')),
        'openlibrary.org/authors/OL2622837A.json' => Http::response(grabada('open-library-autor-garcia-marquez')),
        'openlibrary.org/authors/OL9388A.json' => Http::response(grabada('open-library-autor-vargas-llosa')),
    ]);
}

it('arma la Ficha de un ISBN que conoce, siguiendo el enlace a la obra', function () {
    fingirOpenLibrary();

    $respuesta = openLibrary()->porIsbn(Isbn::desde('9788420471839'));

    expect($respuesta->hayFicha())->toBeTrue();

    $ficha = $respuesta->ficha;

    expect($ficha->titulo)->toBe('Cien años de soledad: novela')
        ->and($ficha->autores)->toBe(['Gabriel García Márquez', 'Mario Vargas Llosa'])
        ->and($ficha->editorial)->toBe('Alfaguara')
        ->and($ficha->anio)->toBe(2007)
        ->and($ficha->edicion)->toBe('Edición conmemorativa')
        ->and($ficha->portadaUrl)->toBe('https://covers.openlibrary.org/b/id/8231856-L.jpg')
        ->and($ficha->descripcion)->toContain('familia Buendía')
        ->and($ficha->fuente)->toBe(Fuente::OpenLibrary)
        ->and($ficha->procedenciaDeLaDescripcion())->toBe(Procedencia::Fuente);
});

it('conserva el orden en que la Fuente acredita a los autores', function () {
    fingirOpenLibrary();

    $ficha = openLibrary()->porIsbn(Isbn::desde('9788420471839'))->ficha;

    expect($ficha->autores[0])->toBe('Gabriel García Márquez');
});

it('lee la descripción de la obra venga como cadena o como objeto con value', function (string $archivo) {
    fingirOpenLibrary(['openlibrary.org/works/*' => Http::response(grabada($archivo))]);

    $ficha = openLibrary()->porIsbn(Isbn::desde('9788420471839'))->ficha;

    expect($ficha->descripcion)->toBe(
        'La historia de la familia Buendía a lo largo de siete generaciones en el pueblo de Macondo.'
    );
})->with(['open-library-obra', 'open-library-obra-descripcion-objeto']);

it('devuelve sin Ficha cuando Open Library no conoce el ISBN', function () {
    Http::fake(['openlibrary.org/*' => Http::response(status: 404)]);

    $respuesta = openLibrary()->porIsbn(Isbn::desde('9788420471839'));

    expect($respuesta->noConoceElIsbn())->toBeTrue()
        ->and($respuesta->respondio())->toBeTrue()
        ->and($respuesta->ficha)->toBeNull();
});

it('avisa que no pudo contestar cuando Open Library devuelve un error', function () {
    Http::fake(['openlibrary.org/*' => Http::response(status: 503)]);

    $respuesta = openLibrary()->porIsbn(Isbn::desde('9788420471839'));

    expect($respuesta->respondio())->toBeFalse()
        ->and($respuesta->noConoceElIsbn())->toBeFalse()
        ->and($respuesta->motivo)->toContain('503');
});

it('avisa que no pudo contestar cuando la red falla', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    $respuesta = openLibrary()->porIsbn(Isbn::desde('9788420471839'));

    expect($respuesta->respondio())->toBeFalse()
        ->and($respuesta->motivo)->toContain('no respondió');
});

it('da la Ficha sin Descripción cuando la edición no apunta a ninguna obra', function () {
    Http::fake([
        'openlibrary.org/isbn/*' => Http::response(grabada('open-library-edicion-sin-obra')),
        'openlibrary.org/authors/*' => Http::response(grabada('open-library-autor-garcia-marquez')),
    ]);

    $ficha = openLibrary()->porIsbn(Isbn::desde('9788420471839'))->ficha;

    expect($ficha->titulo)->toBe('Los recuerdos del porvenir')
        ->and($ficha->tieneDescripcion())->toBeFalse()
        ->and($ficha->procedenciaDeLaDescripcion())->toBeNull();
});

it('da la Ficha sin Descripción cuando la obra no se pudo consultar', function () {
    fingirOpenLibrary(['openlibrary.org/works/*' => Http::response(status: 429)]);

    $respuesta = openLibrary()->porIsbn(Isbn::desde('9788420471839'));

    expect($respuesta->hayFicha())->toBeTrue()
        ->and($respuesta->ficha->tieneDescripcion())->toBeFalse();
});

it('no devuelve Portada cuando la edición sólo trae la marca de que no tiene', function () {
    Http::fake([
        'openlibrary.org/isbn/*' => Http::response(grabada('open-library-edicion-sin-obra')),
        'openlibrary.org/authors/*' => Http::response(grabada('open-library-autor-garcia-marquez')),
    ]);

    $ficha = openLibrary()->porIsbn(Isbn::desde('9788420471839'))->ficha;

    expect($ficha->portadaUrl)->toBeNull();
});

it('prefiere reintentar antes que dar una Ficha a la que le faltan autores', function () {
    fingirOpenLibrary(['openlibrary.org/authors/*' => Http::response(status: 500)]);

    $respuesta = openLibrary()->porIsbn(Isbn::desde('9788420471839'));

    expect($respuesta->respondio())->toBeFalse()
        ->and($respuesta->hayFicha())->toBeFalse();
});

it('le pregunta a Open Library el ISBN normalizado a trece dígitos', function () {
    fingirOpenLibrary();

    openLibrary()->porIsbn(Isbn::desde('8420471836'));

    Http::assertSent(fn ($peticion) => str_contains($peticion->url(), '/isbn/9788420471839.json'));
});
