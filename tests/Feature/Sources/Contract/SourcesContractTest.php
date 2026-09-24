<?php

use App\Domain\Isbn;
use App\Sources\GoogleBooks;
use App\Sources\OpenLibrary;
use Illuminate\Support\Facades\Http;

/**
 * These are the only tests of the project that go out to the internet, and they
 * do not run with `php artisan test`: they run with
 * `php artisan test --group=contract`, by hand or every now and then.
 *
 * They do not check the contents of the catalog —a wiki changes, and that is
 * not a failure— but its shape: that the fields the adapters read still exist
 * and still arrive the way they did the day `sources:record` wrote them down.
 * When one of these fails, the answer is to re-record and read the recording,
 * not to loosen the test.
 */
pest()->group('contract');

beforeEach(function () {
    // `Http::preventStrayRequests()` is global, in tests/Pest.php. These are
    // the exception, and they say so out loud.
    Http::preventStrayRequests(false);
});

function liveOpenLibrary(): OpenLibrary
{
    return new OpenLibrary(
        url: (string) config('sources.open_library.url'),
        coversUrl: (string) config('sources.open_library.covers_url'),
        timeout: 30,
    );
}

function liveGoogleBooks(): GoogleBooks
{
    $key = config('sources.google_books.key');

    if (! is_string($key) || trim($key) === '') {
        test()->markTestSkipped(
            'Sin GOOGLE_BOOKS_API_KEY la consulta cae en la cuota compartida y contesta 429 sin decir nada del contrato.'
        );
    }

    return new GoogleBooks(
        url: (string) config('sources.google_books.url'),
        key: $key,
        timeout: 30,
    );
}

/** @return array<string, mixed> */
function liveJson(string $url, array $parameters = []): array
{
    $response = Http::acceptJson()->timeout(30)->get($url, $parameters);

    expect($response->status())->toBe(200, "«{$url}» contestó HTTP {$response->status()}.");

    $body = $response->json();

    expect($body)->toBeArray();

    return $body;
}

it('Open Library sigue describiendo la edición de un ISBN como cuando se grabó', function () {
    $edition = liveJson(config('sources.open_library.url').'/isbn/9788420471839.json');

    expect($edition)->toHaveKeys(['title', 'publishers', 'publish_date', 'covers', 'authors', 'works', 'edition_name'])
        ->and($edition['title'])->toBeString()
        ->and($edition['publishers'])->toBeArray()
        ->and($edition['publish_date'])->toBeString()
        ->and($edition['covers'])->toBeArray()
        ->and($edition['covers'][0])->toBeInt()
        ->and($edition['edition_name'])->toBeString()
        ->and($edition['authors'][0])->toHaveKey('key')
        ->and($edition['works'][0])->toHaveKey('key');
});

it('Open Library sigue guardando la Description en la obra, no en la edición', function () {
    // The edition points at the work and the Description lives there, which is
    // the whole reason the adapter makes a second request.
    $work = liveJson(config('sources.open_library.url').'/works/OL274505W.json');

    expect($work)->toHaveKey('description');

    $description = $work['description'];

    // It arrives as a string or as an object with `value`; both shapes are the
    // contract, and a third one would not be.
    expect(is_string($description) || (is_array($description) && isset($description['value'])))->toBeTrue();
});

it('Open Library sigue publicando el nombre del autor en su propia dirección', function () {
    $author = liveJson(config('sources.open_library.url').'/authors/OL27363A.json');

    expect($author['name'] ?? $author['personal_name'] ?? null)->toBeString();
});

it('Open Library sigue metiendo una edición adentro de cada obra que encuentra', function () {
    $body = liveJson(
        config('sources.open_library.url').OpenLibrary::SEARCH_PATH,
        OpenLibrary::searchParameters('Pedro Páramo', 'Rulfo'),
    );

    expect($body['docs'] ?? null)->toBeArray()->not->toBeEmpty();

    $editions = $body['docs'][0]['editions']['docs'] ?? null;

    expect($editions)->toBeArray()->not->toBeEmpty()
        ->and($editions[0])->toHaveKeys(['key', 'title']);
});

it('Open Library sigue armando el Record de un ISBN', function () {
    $result = liveOpenLibrary()->byIsbn(Isbn::from('9788420471839'));

    expect($result->hasRecord())->toBeTrue()
        ->and($result->record->title)->not->toBe('')
        ->and($result->record->authors)->not->toBeEmpty()
        ->and($result->record->publisher)->not->toBeNull()
        ->and($result->record->year)->not->toBeNull()
        ->and($result->record->coverUrl)->toStartWith('https://')
        ->and($result->record->hasDescription())->toBeTrue();
});

it('Open Library sigue devolviendo Candidates de un título', function () {
    $candidates = liveOpenLibrary()->byTitle('Pedro Páramo', 'Rulfo');

    expect($candidates->answered())->toBeTrue()
        ->and($candidates->hasCandidates())->toBeTrue()
        ->and($candidates->candidates[0]->title)->not->toBe('');
});

it('Google Books sigue contestando el volumen de un ISBN con la misma forma', function () {
    $body = liveJson(
        config('sources.google_books.url').GoogleBooks::VOLUMES_PATH,
        GoogleBooks::isbnParameters(Isbn::from('9780307474728')) + ['key' => config('sources.google_books.key')],
    );

    expect($body['totalItems'] ?? null)->toBeInt()
        ->and($body['items'] ?? null)->toBeArray()->not->toBeEmpty();

    $info = $body['items'][0]['volumeInfo'] ?? null;

    expect($info)->toBeArray()
        ->and($info)->toHaveKeys(['title', 'authors', 'publisher', 'publishedDate', 'imageLinks', 'industryIdentifiers'])
        ->and($info['authors'])->toBeArray()
        ->and($info['imageLinks'])->toHaveKey('thumbnail')
        ->and($info['industryIdentifiers'][0])->toHaveKeys(['type', 'identifier']);
})->skip(fn () => ! is_string(config('sources.google_books.key')));

it('Google Books sigue armando el Record de un ISBN', function () {
    $result = liveGoogleBooks()->byIsbn(Isbn::from('9780307474728'));

    expect($result->hasRecord())->toBeTrue()
        ->and($result->record->title)->not->toBe('')
        ->and($result->record->authors)->not->toBeEmpty()
        ->and($result->record->publisher)->not->toBeNull()
        ->and($result->record->year)->not->toBeNull()
        ->and($result->record->coverUrl)->toStartWith('https://')
        ->and($result->record->hasDescription())->toBeTrue();
});

it('Google Books sigue devolviendo un volumen por edición en la búsqueda por título', function () {
    $candidates = liveGoogleBooks()->byTitle('Pedro Paramo', 'Rulfo');

    expect($candidates->answered())->toBeTrue()
        ->and($candidates->hasCandidates())->toBeTrue()
        ->and(count($candidates->candidates))->toBeGreaterThan(1);

    $keys = array_map(fn ($candidate) => $candidate->key(), $candidates->candidates);

    // Each volume is its own edition: two Candidates of the same title have to
    // be distinguishable, which is what ADR-002 leans on to put Google Books
    // first in the TitleLookup.
    expect(array_unique($keys))->toHaveCount(count($keys));
});
