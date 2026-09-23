<?php

use App\Enums\Origin;
use App\Enums\Source;
use App\Enums\Status;
use App\Intake\IntakeResult;
use App\Intake\RegisterCandidate;
use App\Jobs\DownloadCover;
use App\Models\Book;
use App\Sources\Candidate;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function registerCandidate(...$arguments): IntakeResult
{
    return app(RegisterCandidate::class)->register(...$arguments);
}

it('registra el Candidato con la Situación que se le indique', function () {
    $result = registerCandidate(
        candidateOf(isbn: '9788437604176', title: 'Pedro Páramo'),
        Status::Owned,
    );

    expect($result->alreadyRegistered)->toBeFalse()
        ->and($result->book->title)->toBe('Pedro Páramo')
        ->and($result->book->isbn)->toBe('9788437604176')
        ->and($result->book->status)->toBe(Status::Owned);
});

it('nace Por comprar cuando no se indica Situación', function () {
    $result = registerCandidate(candidateOf(isbn: '9788437604176'));

    expect($result->book->status)->toBe(Status::Wanted);
});

it('deja registrado de qué Fuente salió la edición', function () {
    $result = registerCandidate(candidateOf(isbn: '9788437604176'));

    expect($result->book->source)->toBe(Source::OpenLibrary);
});

it('encola la Portada que dio la Fuente', function () {
    $result = registerCandidate(candidateOf(
        isbn: '9788437604176',
        coverUrl: 'https://covers.example/pedro-paramo.jpg',
    ));

    Queue::assertPushed(DownloadCover::class, fn (DownloadCover $job): bool => $job->book->is($result->book));

    expect($result->book->refresh()->cover_source_url)
        ->toBe('https://covers.example/pedro-paramo.jpg');
});

it('registra el Candidato que no trae ISBN, y el Libro queda sin ISBN', function () {
    $result = registerCandidate(candidateOf(isbn: null, title: 'El llano en llamas'));

    expect($result->alreadyRegistered)->toBeFalse()
        ->and($result->book->isbn)->toBeNull()
        ->and($result->book->title)->toBe('El llano en llamas')
        ->and($result->book->source)->toBe(Source::OpenLibrary);
});

it('encola la Portada también cuando el Candidato no trae ISBN', function () {
    $result = registerCandidate(candidateOf(
        isbn: null,
        coverUrl: 'https://covers.example/sin-isbn.jpg',
    ));

    Queue::assertPushed(DownloadCover::class);

    expect($result->book->refresh()->cover_source_url)
        ->toBe('https://covers.example/sin-isbn.jpg');
});

it('avisa cuál es el Libro que ya tenía ese ISBN, en vez de duplicarlo', function () {
    $alreadyThere = Book::factory()->create(['isbn' => '9788437604176', 'title' => 'Pedro Páramo']);

    $result = registerCandidate(candidateOf(isbn: '9788437604176'), Status::Owned);

    expect($result->alreadyRegistered)->toBeTrue()
        ->and($result->book->is($alreadyThere))->toBeTrue()
        ->and(Book::count())->toBe(1);
});

it('no cambia la Situación del Libro que ya estaba', function () {
    Book::factory()->create([
        'isbn' => '9788437604176',
        'status' => Status::Wanted,
    ]);

    $result = registerCandidate(candidateOf(isbn: '9788437604176'), Status::Owned);

    expect($result->book->status)->toBe(Status::Wanted);
});

it('dos Candidatos sin ISBN del mismo título se registran por separado', function () {
    registerCandidate(candidateOf(isbn: null, title: 'Cartucho'));
    registerCandidate(candidateOf(isbn: null, title: 'Cartucho'));

    expect(Book::count())->toBe(2);
});

it('guarda la Descripción del Candidato con la procedencia de la Fuente', function () {
    $candidate = new Candidate(
        isbn: null,
        title: 'Cartucho',
        authors: ['Nellie Campobello'],
        publisher: 'Era',
        year: 2000,
        edition: null,
        coverUrl: null,
        description: 'Relatos de la lucha en el norte de México.',
        source: Source::GoogleBooks,
    );

    $result = registerCandidate($candidate);

    expect($result->book->description)->toBe('Relatos de la lucha en el norte de México.')
        ->and($result->book->description_origin)->toBe(Origin::Source);
});

it('un Candidato sin Descripción no guarda procedencia', function () {
    $result = registerCandidate(candidateOf(isbn: null));

    expect($result->book->description)->toBeNull()
        ->and($result->book->description_origin)->toBeNull();
});
