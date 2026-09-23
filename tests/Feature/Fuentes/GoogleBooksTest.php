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
