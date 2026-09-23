<?php

use App\Domain\InvalidIsbn;
use App\Domain\Isbn;
use App\Enums;
use App\Sources\Candidate;
use App\Sources\Candidates;
use App\Sources\LookupResult;
use App\Sources\Record;
use App\Sources\Source;
use App\Sources\SourceResolver;
use Illuminate\Support\Facades\Http;

/** A fake Source that always answers the same, to test the order. */
function sourceThat(LookupResult $result, Enums\Source $identifier, ?Candidates $candidates = null): Source
{
    return new class($result, $identifier, $candidates) implements Source
    {
        public int $lookups = 0;

        public int $searches = 0;

        public function __construct(
            private readonly LookupResult $result,
            private readonly Enums\Source $identifier,
            private readonly ?Candidates $candidates,
        ) {}

        public function identifier(): Enums\Source
        {
            return $this->identifier;
        }

        public function byIsbn(Isbn $isbn): LookupResult
        {
            $this->lookups++;

            return $this->result;
        }

        public function byTitle(string $title, ?string $author = null): Candidates
        {
            $this->searches++;

            return $this->candidates ?? Candidates::none();
        }
    };
}

function fakeCandidate(Enums\Source $source): Candidate
{
    return new Candidate(
        isbn: Isbn::from('9788437604176'),
        title: 'Pedro Páramo',
        authors: ['Juan Rulfo'],
        publisher: 'Editorial RM',
        year: 2005,
        edition: null,
        coverUrl: null,
        description: null,
        source: $source,
    );
}

function fakeRecord(Enums\Source $source): Record
{
    return new Record(
        isbn: Isbn::from('9788437604176'),
        title: 'Pedro Páramo',
        authors: ['Juan Rulfo'],
        publisher: 'Editorial RM',
        year: 2005,
        edition: null,
        coverUrl: null,
        description: null,
        source: $source,
    );
}

it('se queda con la primera Source que conoce el ISBN y no consulta las demás', function () {
    $first = sourceThat(LookupResult::withRecord(fakeRecord(Enums\Source::OpenLibrary)), Enums\Source::OpenLibrary);
    $second = sourceThat(LookupResult::withoutRecord(), Enums\Source::GoogleBooks);

    $result = (new SourceResolver([$first, $second]))->byIsbn(Isbn::from('9788437604176'));

    expect($result->record->source)->toBe(Enums\Source::OpenLibrary)
        ->and($second->lookups)->toBe(0);
});

it('cae a la siguiente Source cuando la primera no conoce el ISBN', function () {
    $first = sourceThat(LookupResult::withoutRecord(), Enums\Source::OpenLibrary);
    $second = sourceThat(LookupResult::withRecord(fakeRecord(Enums\Source::GoogleBooks)), Enums\Source::GoogleBooks);

    $result = (new SourceResolver([$first, $second]))->byIsbn(Isbn::from('9788437604176'));

    expect($result->record->source)->toBe(Enums\Source::GoogleBooks);
});

it('devuelve sin Record cuando ninguna lo conoce, sin lanzar excepción', function () {
    $resolver = new SourceResolver([
        sourceThat(LookupResult::withoutRecord(), Enums\Source::OpenLibrary),
        sourceThat(LookupResult::withoutRecord(), Enums\Source::GoogleBooks),
    ]);

    $result = $resolver->byIsbn(Isbn::from('9788437604176'));

    expect($result->isbnUnknown())->toBeTrue()
        ->and($result->answered())->toBeTrue();
});

it('no manda a ManualEntry un ISBN que una Source no pudo contestar', function () {
    $resolver = new SourceResolver([
        sourceThat(LookupResult::withoutRecord(), Enums\Source::OpenLibrary),
        sourceThat(LookupResult::unavailable('HTTP 429'), Enums\Source::GoogleBooks),
    ]);

    $result = $resolver->byIsbn(Isbn::from('9788437604176'));

    expect($result->answered())->toBeFalse()
        ->and($result->isbnUnknown())->toBeFalse()
        ->and($result->reason)->toContain('Google Books');
});

it('sigue consultando cuando la primera Source no pudo contestar', function () {
    $resolver = new SourceResolver([
        sourceThat(LookupResult::unavailable('se cayó la red'), Enums\Source::OpenLibrary),
        sourceThat(LookupResult::withRecord(fakeRecord(Enums\Source::GoogleBooks)), Enums\Source::GoogleBooks),
    ]);

    expect($resolver->byIsbn(Isbn::from('9788437604176'))->hasRecord())->toBeTrue();
});

it('rechaza un ISBN con el dígito de control inválido antes de salir a la red', function () {
    Http::fake();

    expect(fn () => Isbn::from('9788437604177'))->toThrow(InvalidIsbn::class);

    Http::assertNothingSent();
});

it('queda armado desde la configuración, con Open Library primero', function () {
    Http::fake([
        'openlibrary.org/isbn/*' => Http::response(recorded('open-library-edition-without-work')),
        'openlibrary.org/authors/*' => Http::response(recorded('open-library-author-garcia-marquez')),
    ]);

    $record = app(SourceResolver::class)->byIsbn(Isbn::from('9788420471839'))->record;

    expect($record->source)->toBe(Enums\Source::OpenLibrary);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
});

it('usa Google Books de respaldo cuando Open Library no conoce el ISBN', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response(status: 404),
        'www.googleapis.com/*' => Http::response(recorded('google-books-volume')),
    ]);

    $record = app(SourceResolver::class)->byIsbn(Isbn::from('9788437604176'))->record;

    expect($record->source)->toBe(Enums\Source::GoogleBooks)
        ->and($record->title)->toBe('Pedro Páramo');
});

it('se queda con los Candidates de la primera Source que tenga alguno', function () {
    $first = sourceThat(
        LookupResult::withoutRecord(),
        Enums\Source::GoogleBooks,
        Candidates::of([fakeCandidate(Enums\Source::GoogleBooks)]),
    );
    $second = sourceThat(LookupResult::withoutRecord(), Enums\Source::OpenLibrary, Candidates::none());

    $candidates = (new SourceResolver([$first], [$first, $second]))->byTitle('Pedro Páramo');

    expect($candidates)->toHaveCount(1)
        ->and($second->searches)->toBe(0);
});

it('cae a la siguiente Source cuando la primera no tiene el título', function () {
    $resolver = new SourceResolver([], [
        sourceThat(LookupResult::withoutRecord(), Enums\Source::GoogleBooks, Candidates::none()),
        sourceThat(LookupResult::withoutRecord(), Enums\Source::OpenLibrary, Candidates::of([
            fakeCandidate(Enums\Source::OpenLibrary),
        ])),
    ]);

    expect($resolver->byTitle('Pedro Páramo')->candidates[0]->source)
        ->toBe(Enums\Source::OpenLibrary);
});

it('devuelve una lista vacía cuando ninguna tiene el título, sin lanzar excepción', function () {
    $resolver = new SourceResolver([], [
        sourceThat(LookupResult::withoutRecord(), Enums\Source::GoogleBooks, Candidates::none()),
        sourceThat(LookupResult::withoutRecord(), Enums\Source::OpenLibrary, Candidates::none()),
    ]);

    $candidates = $resolver->byTitle('un título que no existe');

    expect($candidates->titleUnknown())->toBeTrue()
        ->and($candidates->answered())->toBeTrue()
        ->and($candidates->candidates)->toBe([]);
});

it('no confunde una Source que no pudo contestar con un título que nadie tiene', function () {
    $resolver = new SourceResolver([], [
        sourceThat(LookupResult::withoutRecord(), Enums\Source::GoogleBooks, Candidates::unavailable('HTTP 429')),
        sourceThat(LookupResult::withoutRecord(), Enums\Source::OpenLibrary, Candidates::none()),
    ]);

    $candidates = $resolver->byTitle('Pedro Páramo');

    expect($candidates->answered())->toBeFalse()
        ->and($candidates->titleUnknown())->toBeFalse()
        ->and($candidates->reason)->toContain('Google Books');
});

it('sigue buscando cuando la primera Source no pudo contestar', function () {
    $resolver = new SourceResolver([], [
        sourceThat(LookupResult::withoutRecord(), Enums\Source::GoogleBooks, Candidates::unavailable('HTTP 429')),
        sourceThat(LookupResult::withoutRecord(), Enums\Source::OpenLibrary, Candidates::of([
            fakeCandidate(Enums\Source::OpenLibrary),
        ])),
    ]);

    expect($resolver->byTitle('Pedro Páramo')->hasCandidates())->toBeTrue();
});

it('busca por título con Google Books primero, al revés que por ISBN', function () {
    Http::fake([
        'www.googleapis.com/*' => Http::response(recorded('google-books-search')),
        'openlibrary.org/*' => Http::response(recorded('open-library-search')),
    ]);

    $candidates = app(SourceResolver::class)->byTitle('Pedro Páramo', 'Rulfo');

    expect($candidates->candidates[0]->source)->toBe(Enums\Source::GoogleBooks);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openlibrary.org'));
});

it('usa Open Library de respaldo cuando Google Books agotó la cuota', function () {
    Http::fake([
        'www.googleapis.com/*' => Http::response(recorded('google-books-quota-exhausted'), 429),
        'openlibrary.org/*' => Http::response(recorded('open-library-search')),
    ]);

    $candidates = app(SourceResolver::class)->byTitle('Pedro Páramo', 'Rulfo');

    expect($candidates->hasCandidates())->toBeTrue()
        ->and($candidates->candidates[0]->source)->toBe(Enums\Source::OpenLibrary);
});
