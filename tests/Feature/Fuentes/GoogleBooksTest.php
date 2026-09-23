<?php

use App\Dominio\Isbn;
use App\Enums\Fuente;
use App\Fuentes\GoogleBooks;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function googleBooks(?string $llave = null): GoogleBooks
{
    return new GoogleBooks(
        url: 'https://www.googleapis.com',
        llave: $llave,
        timeout: 10,
    );
}

it('arma la Ficha de un ISBN que conoce, con la misma forma que la otra Fuente', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-volumen'))]);

    $ficha = googleBooks()->porIsbn(Isbn::desde('9788437604176'))->ficha;

    expect($ficha->titulo)->toBe('Pedro Páramo')
        ->and($ficha->autores)->toBe(['Juan Rulfo'])
        ->and($ficha->editorial)->toBe('Editorial RM')
        ->and($ficha->anio)->toBe(2005)
        ->and($ficha->portadaUrl)->toStartWith('https://')
        ->and($ficha->descripcion)->toContain('Comala')
        ->and($ficha->fuente)->toBe(Fuente::GoogleBooks);
});

it('entrega la Descripción como texto, sin las etiquetas de HTML que manda Google', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-volumen'))]);

    $ficha = googleBooks()->porIsbn(Isbn::desde('9788437604176'))->ficha;

    expect($ficha->descripcion)->not->toContain('<p>')
        ->and($ficha->descripcion)->toContain('ánimas');
});

it('manda la llave cuando está configurada en el entorno', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-volumen'))]);

    googleBooks('la-llave')->porIsbn(Isbn::desde('9788437604176'));

    Http::assertSent(fn ($peticion) => str_contains($peticion->url(), 'key=la-llave'));
});

it('funciona igual sin llave, pero no la manda', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-volumen'))]);

    $respuesta = googleBooks()->porIsbn(Isbn::desde('9788437604176'));

    expect($respuesta->hayFicha())->toBeTrue();

    Http::assertSent(fn ($peticion) => ! str_contains($peticion->url(), 'key='));
});

it('devuelve sin Ficha cuando Google no encuentra el ISBN', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-sin-resultados'))]);

    $respuesta = googleBooks()->porIsbn(Isbn::desde('9788437604176'));

    expect($respuesta->noConoceElIsbn())->toBeTrue();
});

it('trata el 429 como Fuente que no pudo contestar, no como ISBN desconocido', function (?string $llave) {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-cuota-agotada'), 429)]);

    $respuesta = googleBooks($llave)->porIsbn(Isbn::desde('9788437604176'));

    expect($respuesta->respondio())->toBeFalse()
        ->and($respuesta->noConoceElIsbn())->toBeFalse()
        ->and($respuesta->motivo)->toContain('429');
})->with([null, 'la-llave']);

it('dice en el motivo si el 429 fue de la cuota compartida o de la llave', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-cuota-agotada'), 429)]);

    expect(googleBooks()->porIsbn(Isbn::desde('9788437604176'))->motivo)
        ->toContain('cuota compartida')
        ->and(googleBooks('la-llave')->porIsbn(Isbn::desde('9788437604176'))->motivo)
        ->toContain('cuota de la llave');
});

it('avisa que no pudo contestar cuando la red falla', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    $respuesta = googleBooks()->porIsbn(Isbn::desde('9788437604176'));

    expect($respuesta->respondio())->toBeFalse()
        ->and($respuesta->motivo)->toContain('no respondió');
});

it('le pregunta a Google el ISBN normalizado a trece dígitos', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-volumen'))]);

    googleBooks()->porIsbn(Isbn::desde('8437604176'));

    Http::assertSent(fn ($peticion) => str_contains(urldecode($peticion->url()), 'isbn:9788437604176'));
});

it('devuelve un Candidato por cada edición que encuentra del título', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-busqueda'))]);

    $candidatos = googleBooks()->porTitulo('Pedro Páramo');

    expect($candidatos)->toHaveCount(4)
        ->and($candidatos->hayCandidatos())->toBeTrue();
});

it('trae de cada Candidato lo que hace falta para elegir una edición', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-busqueda'))]);

    $candidato = googleBooks()->porTitulo('Pedro Páramo')->candidatos[0];

    expect($candidato->titulo)->toBe('Pedro Páramo')
        ->and($candidato->autores)->toBe(['Juan Rulfo'])
        ->and($candidato->editorial)->toBe('Editorial RM')
        ->and($candidato->anio)->toBe(2005)
        ->and($candidato->portadaUrl)->toStartWith('https://')
        ->and($candidato->isbn->valor())->toBe('9788437604176')
        ->and($candidato->fuente)->toBe(Fuente::GoogleBooks);
});

it('distingue dos Candidatos del mismo título pero distinta edición', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-busqueda'))]);

    [$rm, $catedra] = googleBooks()->porTitulo('Pedro Páramo')->candidatos;

    expect($rm->titulo)->toBe($catedra->titulo)
        ->and($rm->editorial)->not->toBe($catedra->editorial)
        ->and($rm->anio)->not->toBe($catedra->anio)
        ->and($rm->isbn->valor())->not->toBe($catedra->isbn->valor())
        ->and($rm->clave())->not->toBe($catedra->clave());
});

it('acepta el Candidato que no trae ISBN, en vez de descartarlo', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-busqueda'))]);

    $sinIsbn = googleBooks()->porTitulo('Pedro Páramo')->candidatos[2];

    expect($sinIsbn->tieneIsbn())->toBeFalse()
        ->and($sinIsbn->isbn)->toBeNull()
        ->and($sinIsbn->editorial)->toBe('Fondo de Cultura Económica')
        ->and($sinIsbn->clave())->toContain('7HlLAAAAMAAJ');
});

it('ignora el ISBN que no cuadra y se queda con el que sí', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-busqueda'))]);

    $candidato = googleBooks()->porTitulo('Pedro Páramo')->candidatos[3];

    // El ISBN_13 del volumen trae mal el dígito de control; el de diez cuadra.
    expect($candidato->isbn->valor())->toBe('9788437654324');
});

it('acota cada término a su campo y agrega el autor cuando se da', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-busqueda'))]);

    googleBooks()->porTitulo('Pedro Páramo', 'Rulfo');

    Http::assertSent(function ($peticion) {
        $consulta = urldecode($peticion->url());

        return str_contains($consulta, 'intitle:"Pedro Páramo"')
            && str_contains($consulta, 'inauthor:"Rulfo"');
    });
});

it('no manda el autor cuando no se da', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-busqueda'))]);

    googleBooks()->porTitulo('Pedro Páramo');

    Http::assertSent(fn ($peticion) => ! str_contains(urldecode($peticion->url()), 'inauthor:'));
});

it('manda la llave también en la búsqueda por título', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-busqueda'))]);

    googleBooks('la-llave')->porTitulo('Pedro Páramo');

    Http::assertSent(fn ($peticion) => str_contains($peticion->url(), 'key=la-llave'));
});

it('devuelve una lista vacía cuando el título no da resultados, no una excepción', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-sin-resultados'))]);

    $candidatos = googleBooks()->porTitulo('un título que no existe');

    expect($candidatos->noConoceElTitulo())->toBeTrue()
        ->and($candidatos->candidatos)->toBe([])
        ->and($candidatos->respondio())->toBeTrue();
});

it('no traduce el 429 de la búsqueda a «no hay ese título»', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(grabada('google-books-cuota-agotada'), 429)]);

    $candidatos = googleBooks()->porTitulo('Pedro Páramo');

    expect($candidatos->respondio())->toBeFalse()
        ->and($candidatos->noConoceElTitulo())->toBeFalse()
        ->and($candidatos->motivo)->toContain('429');
});

it('avisa que no pudo buscar cuando la red falla', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    $candidatos = googleBooks()->porTitulo('Pedro Páramo');

    expect($candidatos->respondio())->toBeFalse()
        ->and($candidatos->motivo)->toContain('no respondió');
});

it('no sale a la red con un título vacío', function () {
    Http::fake();

    expect(googleBooks()->porTitulo('   ')->candidatos)->toBe([]);

    Http::assertNothingSent();
});
