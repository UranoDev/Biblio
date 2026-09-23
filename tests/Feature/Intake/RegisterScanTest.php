<?php

use App\Enums\Origin;
use App\Enums\ScanOutcome;
use App\Enums\Status;
use App\Intake\RegisterScan;
use App\Intake\ScanResult;
use App\Jobs\DownloadCover;
use App\Models\Book;
use App\Sources\LookupResult;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function scanOf(string $typed): ScanResult
{
    return app(RegisterScan::class)->register($typed);
}

it('registral Book con el Record que trajo la Source', function () {
    sourcesAnswer(LookupResult::withRecord(recordOf(
        isbn: '9788437604176',
        description: 'La historia de Juan Preciado en Comala.',
    )));

    $result = scanOf('9788437604176');

    expect($result->outcome)->toBe(ScanOutcome::Registered)
        ->and($result->book->title)->toBe('Pedro Páramo')
        ->and($result->book->authors)->toBe(['Juan Rulfo'])
        ->and($result->book->isbn)->toBe('9788437604176')
        ->and($result->book->status)->toBe(Status::Owned)
        ->and($result->book->description_origin)->toBe(Origin::Source)
        ->and(Book::count())->toBe(1);
});

it('encolal Cover del Book que acaba de registrar', function () {
    sourcesAnswer(LookupResult::withRecord(recordOf(
        isbn: '9788437604176',
        coverUrl: 'https://covers.openlibrary.org/b/id/12345-L.jpg',
    )));

    $result = scanOf('9788437604176');

    Queue::assertPushed(DownloadCover::class);

    expect($result->book->fresh()->cover_source_url)
        ->toBe('https://covers.openlibrary.org/b/id/12345-L.jpg');
});

it('no encola nada cuando el Record no trae Cover', function () {
    sourcesAnswer(LookupResult::withRecord(recordOf('9788437604176')));

    scanOf('9788437604176');

    Queue::assertNothingPushed();
});

it('avisa que el Book ya está en el Shelf, sin duplicarlo ni volver a preguntarle a la Source', function () {
    $book = Book::factory()->owned()->create(['isbn' => '9788437604176']);

    sourcesAnswer(LookupResult::unavailable('no tendría que haberse consultado'));

    $result = scanOf('9788437604176');

    expect($result->outcome)->toBe(ScanOutcome::AlreadyOnShelf)
        ->and($result->book->is($book))->toBeTrue()
        ->and($result->canBeUndone())->toBeFalse()
        ->and(Book::count())->toBe(1);
});

it('pasa al Shelf el Book que estaba Wanted', function () {
    $book = Book::factory()->wanted()->create(['isbn' => '9788437604176']);

    $result = scanOf('9788437604176');

    expect($result->outcome)->toBe(ScanOutcome::MovedToShelf)
        ->and($result->canBeUndone())->toBeTrue()
        ->and($book->fresh()->status)->toBe(Status::Owned)
        ->and(Book::count())->toBe(1);
});

it('reconoce el ISBN-10 del Book guardado con su ISBN-13', function () {
    Book::factory()->owned()->create(['isbn' => '9788437604176']);

    expect(scanOf('8437604176')->outcome)->toBe(ScanOutcome::AlreadyOnShelf)
        ->and(Book::count())->toBe(1);
});

it('ofrece ManualEntry cuando ninguna Source conoce el ISBN', function () {
    sourcesAnswer(LookupResult::withoutRecord());

    $result = scanOf('9788437604176');

    expect($result->outcome)->toBe(ScanOutcome::UnknownIsbn)
        ->and($result->offersManualEntry())->toBeTrue()
        ->and($result->isbn->value())->toBe('9788437604176')
        ->and(Book::count())->toBe(0);
});

it('no ofrece ManualEntry cuando la Source no pudo contestar', function () {
    sourcesAnswer(LookupResult::unavailable('Google Books: quota exhausted'));

    $result = scanOf('9788437604176');

    expect($result->outcome)->toBe(ScanOutcome::SourceUnavailable)
        ->and($result->offersManualEntry())->toBeFalse()
        ->and($result->reason)->toContain('quota exhausted')
        ->and(Book::count())->toBe(0);
});

it('rechaza lo que no es un ISBN sin consultar a ninguna Source', function () {
    sourcesAnswer(LookupResult::unavailable('no tendría que haberse consultado'));

    $result = scanOf('1234567890');

    expect($result->outcome)->toBe(ScanOutcome::InvalidIsbn)
        ->and($result->typed)->toBe('1234567890')
        ->and($result->isbn)->toBeNull()
        ->and(Book::count())->toBe(0);
});

it('aceptal ISBN tal como lo imprime el código de barras, con guiones', function () {
    sourcesAnswer(LookupResult::withRecord(recordOf('9788437604176')));

    expect(scanOf('978-84-376-0417-6')->book->isbn)->toBe('9788437604176');
});

it('dejal Book sin Description cuando la Source no la trajo', function () {
    sourcesAnswer(LookupResult::withRecord(recordOf('9788437604176')));

    $book = scanOf('9788437604176')->book;

    expect($book->description)->toBeNull()
        ->and($book->description_origin)->toBeNull();
});
