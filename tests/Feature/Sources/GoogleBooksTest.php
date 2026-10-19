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

/**
 * The recorded volume is «Cien años de soledad / One Hundred Years of
 * Solitude», ISBN 9780307474728.
 */
it('armal Record de un ISBN que conoce, con la misma forma que la otra Source', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-volume'))]);

    $record = googleBooks()->byIsbn(Isbn::from('9780307474728'))->record;

    expect($record->title)->toBe('Cien años de soledad / One Hundred Years of Solitude')
        ->and($record->authors)->toBe(['Gabriel García Márquez'])
        ->and($record->publisher)->toBe('Vintage Espanol')
        ->and($record->year)->toBe(2009)
        ->and($record->coverUrl)->toStartWith('https://')
        ->and($record->description)->toContain('Macondo')
        ->and($record->source)->toBe(Source::GoogleBooks);
});

it('sirvel Cover por https aunque Google la ofrezca por http', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-volume'))]);

    $record = googleBooks()->byIsbn(Isbn::from('9780307474728'))->record;

    expect($record->coverUrl)->toStartWith('https://books.google.com/books/content?id=7lj5EHcChqYC');
});

it('dal Record sin Cover ni editorial cuando el volumen no trae ninguna', function () {
    // ISBN 9788439732471: Google has the volume and publishes neither
    // `imageLinks` nor `publisher`.
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-volume-without-cover'))]);

    $record = googleBooks()->byIsbn(Isbn::from('9788439732471'))->record;

    expect($record->title)->toBe('Cien años de soledad: Ed. Conmemorativa Ilustrada 50 Aniversario')
        ->and($record->coverUrl)->toBeNull()
        ->and($record->publisher)->toBeNull()
        ->and($record->hasDescription())->toBeFalse();
});

it('entrega la Description como texto, sin las entidades de HTML que manda Google', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-volume'))]);

    $record = googleBooks()->byIsbn(Isbn::from('9780307474728'))->record;

    expect($record->description)->not->toContain('&quot;')
        ->and($record->description)->toContain('"');
});

it('manda la llave cuando está configurada en el entorno', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-volume'))]);

    googleBooks('la-llave')->byIsbn(Isbn::from('9780307474728'));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'key=la-llave'));
});

it('funciona igual sin llave, pero no la manda', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-volume'))]);

    $result = googleBooks()->byIsbn(Isbn::from('9780307474728'));

    expect($result->hasRecord())->toBeTrue();

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'key='));
});

it('devuelve sin Record cuando Google no encuentral ISBN', function () {
    Http::fake([
        'www.googleapis.com/*' => Http::response(recorded('google-books-no-results')),
        // Built here: no recording has a lookup that knows nothing.
        'books.google.com/*' => Http::response('var _GBSBookInfo = {};'),
    ]);

    $result = googleBooks()->byIsbn(Isbn::from('9788437604176'));

    expect($result->isbnUnknown())->toBeTrue();
});

/**
 * «Borderlands» by Ken Salazar, ISBN 9798902680222, out on 2026-07-28. On
 * 2026-10-19 the search returned `totalItems: 0` and the volume already existed.
 */
function fakeNotIndexed(mixed $volume = null, mixed $lookup = null): void
{
    Http::fake([
        'www.googleapis.com/books/v1/volumes/91rKEQAAQBAJ*' => $volume ?? Http::response(recorded('google-books-volume-by-id')),
        'www.googleapis.com/*' => Http::response(recorded('google-books-not-indexed')),
        'books.google.com/*' => $lookup ?? Http::response(recorded('google-books-lookup')),
    ]);
}

it('encuentral libro que la búsqueda todavía no indexa, por la consulta de ISBN', function () {
    fakeNotIndexed();

    $record = googleBooks()->byIsbn(Isbn::from('9798902680222'))->record;

    expect($record->title)->toBe('Borderlands: My Fight for an Inclusive America')
        ->and($record->authors)->toBe(['Ken Salazar'])
        ->and($record->publisher)->toBe('Simon and Schuster')
        ->and($record->year)->toBe(2026)
        ->and($record->source)->toBe(Source::GoogleBooks);
});

it('lee el volumen con la llave, y la consulta de ISBN sin ella', function () {
    fakeNotIndexed();

    googleBooks('la-llave')->byIsbn(Isbn::from('9798902680222'));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/volumes/91rKEQAAQBAJ')
        && str_contains($request->url(), 'key=la-llave'));
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://books.google.com/books?')
        && str_contains(urldecode($request->url()), 'bibkeys=ISBN:9798902680222')
        && ! str_contains($request->url(), 'key='));
});

it('no usa la consulta de ISBN cuando la búsqueda ya encontró el volumen', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-volume'))]);

    googleBooks()->byIsbn(Isbn::from('9780307474728'));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'books.google.com'));
});

it('descarta el volumen de la consulta cuando no publica el ISBN pedido', function () {
    // Built here: the lookup answering with a volume of another ISBN.
    fakeNotIndexed(volume: Http::response([
        'id' => '91rKEQAAQBAJ',
        'volumeInfo' => [
            'title' => 'Otro libro',
            'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => '9780307474728']],
        ],
    ]));

    expect(googleBooks()->byIsbn(Isbn::from('9798902680222'))->isbnUnknown())->toBeTrue();
});

it('devuelve sin Record cuando el volumen de la consulta ya no existe', function () {
    fakeNotIndexed(volume: Http::response(['error' => ['code' => 404]], 404));

    expect(googleBooks()->byIsbn(Isbn::from('9798902680222'))->isbnUnknown())->toBeTrue();
});

it('avisa que no pudo contestar cuando la consulta de ISBN falla', function () {
    fakeNotIndexed(lookup: Http::response('', 503));

    $result = googleBooks()->byIsbn(Isbn::from('9798902680222'));

    expect($result->answered())->toBeFalse()
        ->and($result->reason)->toContain('ISBN lookup');
});

it('tratal 429 como Source que no pudo contestar, no como ISBN desconocido', function (?string $key) {
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

it('devuelve un Candidate por cada edición que encuentra del título', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search'))]);

    $candidates = googleBooks()->byTitle('Pedro Paramo', 'Rulfo');

    expect($candidates)->toHaveCount(20)
        ->and($candidates->hasCandidates())->toBeTrue();
});

it('trae de cada Candidate lo que hace falta para elegir una edición', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search'))]);

    $candidate = googleBooks()->byTitle('Pedro Paramo', 'Rulfo')->candidates[0];

    expect($candidate->title)->toBe('Pedro Paramo')
        ->and($candidate->publisher)->toBe('CreateSpace')
        ->and($candidate->year)->toBe(2015)
        ->and($candidate->coverUrl)->toStartWith('https://')
        ->and($candidate->isbn->value())->toBe('9781514731468')
        ->and($candidate->source)->toBe(Source::GoogleBooks);
});

it('distingue dos Candidates del mismo título pero distinta edición', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search'))]);

    $candidates = googleBooks()->byTitle('Pedro Paramo', 'Rulfo')->candidates;

    [$first, $second] = [$candidates[4], $candidates[5]];

    expect($first->title)->toBe($second->title)
        ->and($first->year)->not->toBe($second->year)
        ->and($first->isbn->value())->not->toBe($second->isbn->value())
        ->and($first->key())->not->toBe($second->key());
});

it('aceptal Candidate que no trae ISBN, en vez de descartarlo', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search'))]);

    // Google publishes an OCLC number for this volume and no ISBN at all.
    $withoutIsbn = googleBooks()->byTitle('Pedro Paramo', 'Rulfo')->candidates[6];

    expect($withoutIsbn->hasIsbn())->toBeFalse()
        ->and($withoutIsbn->isbn)->toBeNull()
        ->and($withoutIsbn->key())->toContain('_2_jzwEACAAJ');
});

it('juntal subtítulo al título del Candidate cuando el volumen lo trae', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search'))]);

    $candidate = googleBooks()->byTitle('Pedro Paramo', 'Rulfo')->candidates[8];

    expect($candidate->title)->toBe('Pedro Paramo: A Novel of Mexico');
});

it('ignoral identificador que no es un ISBN y deja el Candidate sin uno', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search'))]);

    // `industryIdentifiers` mixes in internal library keys; one of those is not
    // an ISBN even though it sits in the same array.
    $candidate = googleBooks()->byTitle('Pedro Paramo', 'Rulfo')->candidates[8];

    expect($candidate->hasIsbn())->toBeFalse();
});

it('descartal ISBN cuyo dígito de control no cuadra', function () {
    // No recording carries one —of the 20 volumes of the search, every ISBN
    // adds up—, so the case is built here and does not pretend to be a
    // recording by living in `tests/Fixtures/sources`.
    Http::fake(['www.googleapis.com/*' => Http::response([
        'kind' => 'books#volumes',
        'totalItems' => 1,
        'items' => [[
            'id' => 'inventado',
            'volumeInfo' => [
                'title' => 'Pedro Páramo',
                'industryIdentifiers' => [
                    ['type' => 'ISBN_13', 'identifier' => '9788437604177'],
                    ['type' => 'ISBN_10', 'identifier' => '8437654327'],
                ],
            ],
        ]],
    ])]);

    $candidate = googleBooks()->byTitle('Pedro Páramo')->candidates[0];

    expect($candidate->isbn->value())->toBe('9788437654324');
});

it('limpial marcado de HTML que la búsqueda mete en la Description', function () {
    // Recorded from `intitle:"Atomic Habits"`: of the titles this project
    // searches for, it is the one whose Description really arrives with tags.
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search-with-markup'))]);

    $candidate = googleBooks()->byTitle('Atomic Habits')->candidates[0];

    expect($candidate->description)->not->toContain('<br>')
        ->and($candidate->description)->not->toContain('<')
        ->and($candidate->description)->toContain("\n");
});

it('acota cada término a su campo y agregal autor cuando se da', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(recorded('google-books-search'))]);

    googleBooks()->byTitle('Pedro Páramo', 'Rulfo');

    Http::assertSent(function ($request) {
        $query = urldecode($request->url());

        return str_contains($query, 'intitle:"Pedro Páramo"')
            && str_contains($query, 'inauthor:"Rulfo"');
    });
});

it('no mandal autor cuando no se da', function () {
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
