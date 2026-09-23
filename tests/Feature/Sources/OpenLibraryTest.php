<?php

use App\Domain\Isbn;
use App\Enums\Origin;
use App\Enums\Source;
use App\Sources\OpenLibrary;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function openLibrary(): OpenLibrary
{
    return new OpenLibrary(
        url: 'https://openlibrary.org',
        coversUrl: 'https://covers.openlibrary.org',
        timeout: 10,
    );
}

/** @param  array<string, mixed>  $extra */
function fakeOpenLibrary(array $extra = []): void
{
    // The `+` leaves the responses of the case ahead of the base ones: Http
    // resolves the first pattern that matches.
    Http::fake($extra + [
        'openlibrary.org/isbn/*' => Http::response(recorded('open-library-edition')),
        'openlibrary.org/works/*' => Http::response(recorded('open-library-work')),
        'openlibrary.org/authors/OL2622837A.json' => Http::response(recorded('open-library-author-garcia-marquez')),
        'openlibrary.org/authors/OL9388A.json' => Http::response(recorded('open-library-author-vargas-llosa')),
    ]);
}

it('arma la Ficha de un ISBN que conoce, siguiendo el enlace a la obra', function () {
    fakeOpenLibrary();

    $result = openLibrary()->byIsbn(Isbn::from('9788420471839'));

    expect($result->hasRecord())->toBeTrue();

    $record = $result->record;

    expect($record->title)->toBe('Cien años de soledad: novela')
        ->and($record->authors)->toBe(['Gabriel García Márquez', 'Mario Vargas Llosa'])
        ->and($record->publisher)->toBe('Alfaguara')
        ->and($record->year)->toBe(2007)
        ->and($record->edition)->toBe('Edición conmemorativa')
        ->and($record->coverUrl)->toBe('https://covers.openlibrary.org/b/id/8231856-L.jpg')
        ->and($record->description)->toContain('familia Buendía')
        ->and($record->source)->toBe(Source::OpenLibrary)
        ->and($record->descriptionOrigin())->toBe(Origin::Source);
});

it('conserva el orden en que la Fuente acredita a los autores', function () {
    fakeOpenLibrary();

    $record = openLibrary()->byIsbn(Isbn::from('9788420471839'))->record;

    expect($record->authors[0])->toBe('Gabriel García Márquez');
});

it('lee la descripción de la obra venga como cadena o como objeto con value', function (string $file) {
    fakeOpenLibrary(['openlibrary.org/works/*' => Http::response(recorded($file))]);

    $record = openLibrary()->byIsbn(Isbn::from('9788420471839'))->record;

    expect($record->description)->toBe(
        'La historia de la familia Buendía a lo largo de siete generaciones en el pueblo de Macondo.'
    );
})->with(['open-library-work', 'open-library-work-description-object']);

it('devuelve sin Ficha cuando Open Library no conoce el ISBN', function () {
    Http::fake(['openlibrary.org/*' => Http::response(status: 404)]);

    $result = openLibrary()->byIsbn(Isbn::from('9788420471839'));

    expect($result->isbnUnknown())->toBeTrue()
        ->and($result->answered())->toBeTrue()
        ->and($result->record)->toBeNull();
});

it('avisa que no pudo contestar cuando Open Library devuelve un error', function () {
    Http::fake(['openlibrary.org/*' => Http::response(status: 503)]);

    $result = openLibrary()->byIsbn(Isbn::from('9788420471839'));

    expect($result->answered())->toBeFalse()
        ->and($result->isbnUnknown())->toBeFalse()
        ->and($result->reason)->toContain('503');
});

it('avisa que no pudo contestar cuando la red falla', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    $result = openLibrary()->byIsbn(Isbn::from('9788420471839'));

    expect($result->answered())->toBeFalse()
        ->and($result->reason)->toContain('did not answer');
});

it('da la Ficha sin Descripción cuando la edición no apunta a ninguna obra', function () {
    Http::fake([
        'openlibrary.org/isbn/*' => Http::response(recorded('open-library-edition-without-work')),
        'openlibrary.org/authors/*' => Http::response(recorded('open-library-author-garcia-marquez')),
    ]);

    $record = openLibrary()->byIsbn(Isbn::from('9788420471839'))->record;

    expect($record->title)->toBe('Los recuerdos del porvenir')
        ->and($record->hasDescription())->toBeFalse()
        ->and($record->descriptionOrigin())->toBeNull();
});

it('da la Ficha sin Descripción cuando la obra no se pudo consultar', function () {
    fakeOpenLibrary(['openlibrary.org/works/*' => Http::response(status: 429)]);

    $result = openLibrary()->byIsbn(Isbn::from('9788420471839'));

    expect($result->hasRecord())->toBeTrue()
        ->and($result->record->hasDescription())->toBeFalse();
});

it('no devuelve Portada cuando la edición sólo trae la marca de que no tiene', function () {
    Http::fake([
        'openlibrary.org/isbn/*' => Http::response(recorded('open-library-edition-without-work')),
        'openlibrary.org/authors/*' => Http::response(recorded('open-library-author-garcia-marquez')),
    ]);

    $record = openLibrary()->byIsbn(Isbn::from('9788420471839'))->record;

    expect($record->coverUrl)->toBeNull();
});

it('prefiere reintentar antes que dar una Ficha a la que le faltan autores', function () {
    fakeOpenLibrary(['openlibrary.org/authors/*' => Http::response(status: 500)]);

    $result = openLibrary()->byIsbn(Isbn::from('9788420471839'));

    expect($result->answered())->toBeFalse()
        ->and($result->hasRecord())->toBeFalse();
});

it('le pregunta a Open Library el ISBN normalizado a trece dígitos', function () {
    fakeOpenLibrary();

    openLibrary()->byIsbn(Isbn::from('8420471836'));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/isbn/9788420471839.json'));
});

it('devuelve un Candidato por obra, que es todo lo que su búsqueda entrega', function () {
    Http::fake(['openlibrary.org/search.json*' => Http::response(recorded('open-library-search'))]);

    $candidates = openLibrary()->byTitle('Pedro Páramo', 'Rulfo');

    // The recorded response brings 6 works with 134 editions among them, and
    // Open Library delivers a single one of each work. That is why it is the
    // fallback and not the first one of the TitleLookup.
    expect($candidates)->toHaveCount(6);
});

it('arma el Candidato con los datos de la edición, no con los de la obra', function () {
    Http::fake(['openlibrary.org/search.json*' => Http::response(recorded('open-library-search'))]);

    $candidate = openLibrary()->byTitle('Pedro Páramo', 'Rulfo')->candidates[0];

    expect($candidate->title)->toBe('Pedro Páramo')
        ->and($candidate->authors)->toBe(['Juan Rulfo'])
        ->and($candidate->publisher)->toBe('Grove Press')
        ->and($candidate->year)->toBe(1969)
        ->and($candidate->coverUrl)->toBe('https://covers.openlibrary.org/b/id/9286827-L.jpg')
        ->and($candidate->source)->toBe(Source::OpenLibrary);
});

it('acepta la edición que Open Library no publica con ISBN', function () {
    Http::fake(['openlibrary.org/search.json*' => Http::response(recorded('open-library-search'))]);

    $candidate = openLibrary()->byTitle('Pedro Páramo', 'Rulfo')->candidates[0];

    expect($candidate->hasIsbn())->toBeFalse()
        ->and($candidate->key())->toContain('OL27792668M');
});

it('guarda el ISBN de la edición normalizado a trece dígitos', function () {
    Http::fake(['openlibrary.org/search.json*' => Http::response(recorded('open-library-search'))]);

    $candidate = openLibrary()->byTitle('Pedro Páramo', 'Rulfo')->candidates[1];

    expect($candidate->isbn->value())->toBe('9788521906032');
});

it('distingue dos Candidatos del mismo título por su editorial y su año', function () {
    Http::fake(['openlibrary.org/search.json*' => Http::response(recorded('open-library-search'))]);

    $candidates = openLibrary()->byTitle('Pedro Páramo', 'Rulfo')->candidates;

    $keys = array_map(fn ($candidate) => $candidate->key(), $candidates);

    expect(array_unique($keys))->toHaveCount(count($candidates));
});

it('le pasa el título y el autor a la búsqueda, y pide los campos de la edición', function () {
    Http::fake(['openlibrary.org/search.json*' => Http::response(recorded('open-library-search'))]);

    openLibrary()->byTitle('Pedro Páramo', 'Rulfo');

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'title=Pedro Páramo')
            && str_contains($url, 'author=Rulfo')
            && str_contains($url, 'editions.publisher');
    });
});

it('no manda el autor cuando no se da', function () {
    Http::fake(['openlibrary.org/search.json*' => Http::response(recorded('open-library-search'))]);

    openLibrary()->byTitle('Pedro Páramo');

    Http::assertSent(fn ($request) => ! str_contains(urldecode($request->url()), 'author='));
});

it('devuelve una lista vacía cuando la búsqueda no da resultados', function () {
    Http::fake(['openlibrary.org/search.json*' => Http::response(['numFound' => 0, 'docs' => []])]);

    $candidates = openLibrary()->byTitle('un título que no existe');

    expect($candidates->titleUnknown())->toBeTrue()
        ->and($candidates->answered())->toBeTrue()
        ->and($candidates->candidates)->toBe([]);
});

it('avisa que no pudo buscar en vez de decir que no hay ese título', function () {
    Http::fake(['openlibrary.org/search.json*' => Http::response(status: 503)]);

    $candidates = openLibrary()->byTitle('Pedro Páramo');

    expect($candidates->answered())->toBeFalse()
        ->and($candidates->titleUnknown())->toBeFalse()
        ->and($candidates->reason)->toContain('503');
});

it('avisa que no pudo buscar cuando la red falla', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    expect(openLibrary()->byTitle('Pedro Páramo')->reason)->toContain('did not answer');
});

it('no sale a la red con un título vacío', function () {
    Http::fake();

    expect(openLibrary()->byTitle('')->candidates)->toBe([]);

    Http::assertNothingSent();
});
