<?php

use App\Domain\Isbn;
use App\Enums\Source;
use App\Sources\GoogleBooks;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function googleBooks(?string $key = null): GoogleBooks
{
    return new GoogleBooks(
        url: 'https://www.googleapis.com',
        key: $key,
        timeout: 10,
    );
}

it('arma la Ficha de un ISBN que conoce, con la misma forma que la otra Fuente', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-volume'))]);

    $record = googleBooks()->byIsbn(Isbn::from('9788437604176'))->record;

    expect($record->title)->toBe('Pedro Páramo')
        ->and($record->authors)->toBe(['Juan Rulfo'])
        ->and($record->publisher)->toBe('Editorial RM')
        ->and($record->year)->toBe(2005)
        ->and($record->coverUrl)->toStartWith('https://')
        ->and($record->description)->toContain('Comala')
        ->and($record->source)->toBe(Source::GoogleBooks);
});

it('entrega la Descripción como texto, sin las etiquetas de HTML que manda Google', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-volume'))]);

    $record = googleBooks()->byIsbn(Isbn::from('9788437604176'))->record;

    expect($record->description)->not->toContain('<p>')
        ->and($record->description)->toContain('ánimas');
});

it('manda la llave cuando está configurada en el entorno', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-volume'))]);

    googleBooks('la-llave')->byIsbn(Isbn::from('9788437604176'));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'key=la-llave'));
});

it('funciona igual sin llave, pero no la manda', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-volume'))]);

    $result = googleBooks()->byIsbn(Isbn::from('9788437604176'));

    expect($result->hasRecord())->toBeTrue();

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'key='));
});

it('devuelve sin Ficha cuando Google no encuentra el ISBN', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-no-results'))]);

    $result = googleBooks()->byIsbn(Isbn::from('9788437604176'));

    expect($result->isbnUnknown())->toBeTrue();
});

it('trata el 429 como Fuente que no pudo contestar, no como ISBN desconocido', function (?string $key) {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-quota-exhausted'), 429)]);

    $result = googleBooks($key)->byIsbn(Isbn::from('9788437604176'));

    expect($result->answered())->toBeFalse()
        ->and($result->isbnUnknown())->toBeFalse()
        ->and($result->reason)->toContain('429');
})->with([null, 'la-llave']);

it('dice en el motivo si el 429 fue de la cuota compartida o de la llave', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-quota-exhausted'), 429)]);

    expect(googleBooks()->byIsbn(Isbn::from('9788437604176'))->reason)
        ->toContain('shared quota')
        ->and(googleBooks('la-llave')->byIsbn(Isbn::from('9788437604176'))->reason)
        ->toContain('quota on the configured key');
});

it('avisa que no pudo contestar cuando la red falla', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    $result = googleBooks()->byIsbn(Isbn::from('9788437604176'));

    expect($result->answered())->toBeFalse()
        ->and($result->reason)->toContain('did not answer');
});

it('le pregunta a Google el ISBN normalizado a trece dígitos', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-volume'))]);

    googleBooks()->byIsbn(Isbn::from('8437604176'));

    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'isbn:9788437604176'));
});

it('devuelve un Candidato por cada edición que encuentra del título', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search'))]);

    $candidates = googleBooks()->byTitle('Pedro Páramo');

    expect($candidates)->toHaveCount(4)
        ->and($candidates->hasCandidates())->toBeTrue();
});

it('trae de cada Candidato lo que hace falta para elegir una edición', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search'))]);

    $candidate = googleBooks()->byTitle('Pedro Páramo')->candidates[0];

    expect($candidate->title)->toBe('Pedro Páramo')
        ->and($candidate->authors)->toBe(['Juan Rulfo'])
        ->and($candidate->publisher)->toBe('Editorial RM')
        ->and($candidate->year)->toBe(2005)
        ->and($candidate->coverUrl)->toStartWith('https://')
        ->and($candidate->isbn->value())->toBe('9788437604176')
        ->and($candidate->source)->toBe(Source::GoogleBooks);
});

it('distingue dos Candidatos del mismo título pero distinta edición', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search'))]);

    [$rm, $catedra] = googleBooks()->byTitle('Pedro Páramo')->candidates;

    expect($rm->title)->toBe($catedra->title)
        ->and($rm->publisher)->not->toBe($catedra->publisher)
        ->and($rm->year)->not->toBe($catedra->year)
        ->and($rm->isbn->value())->not->toBe($catedra->isbn->value())
        ->and($rm->key())->not->toBe($catedra->key());
});

it('acepta el Candidato que no trae ISBN, en vez de descartarlo', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search'))]);

    $withoutIsbn = googleBooks()->byTitle('Pedro Páramo')->candidates[2];

    expect($withoutIsbn->hasIsbn())->toBeFalse()
        ->and($withoutIsbn->isbn)->toBeNull()
        ->and($withoutIsbn->publisher)->toBe('Fondo de Cultura Económica')
        ->and($withoutIsbn->key())->toContain('7HlLAAAAMAAJ');
});

it('ignora el ISBN que no cuadra y se queda con el que sí', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search'))]);

    $candidate = googleBooks()->byTitle('Pedro Páramo')->candidates[3];

    // The ISBN_13 of the volume has a wrong check digit; the ten-digit one adds up.
    expect($candidate->isbn->value())->toBe('9788437654324');
});

it('acota cada término a su campo y agrega el autor cuando se da', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search'))]);

    googleBooks()->byTitle('Pedro Páramo', 'Rulfo');

    Http::assertSent(function ($request) {
        $query = urldecode($request->url());

        return str_contains($query, 'intitle:"Pedro Páramo"')
            && str_contains($query, 'inauthor:"Rulfo"');
    });
});

it('no manda el autor cuando no se da', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search'))]);

    googleBooks()->byTitle('Pedro Páramo');

    Http::assertSent(fn ($request) => ! str_contains(urldecode($request->url()), 'inauthor:'));
});

it('manda la llave también en la búsqueda por título', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search'))]);

    googleBooks('la-llave')->byTitle('Pedro Páramo');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'key=la-llave'));
});

it('devuelve una lista vacía cuando el título no da resultados, no una excepción', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-no-results'))]);

    $candidates = googleBooks()->byTitle('un título que no existe');

    expect($candidates->titleUnknown())->toBeTrue()
        ->and($candidates->candidates)->toBe([])
        ->and($candidates->answered())->toBeTrue();
});

it('no traduce el 429 de la búsqueda a «no hay ese título»', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-quota-exhausted'), 429)]);

    $candidates = googleBooks()->byTitle('Pedro Páramo');

    expect($candidates->answered())->toBeFalse()
        ->and($candidates->titleUnknown())->toBeFalse()
        ->and($candidates->reason)->toContain('429');
});

it('avisa que no pudo buscar cuando la red falla', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    $candidates = googleBooks()->byTitle('Pedro Páramo');

    expect($candidates->answered())->toBeFalse()
        ->and($candidates->reason)->toContain('did not answer');
});

it('no sale a la red con un título vacío', function () {
    Http::fake();

    expect(googleBooks()->byTitle('   ')->candidates)->toBe([]);

    Http::assertNothingSent();
});
