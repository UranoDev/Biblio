<?php

use App\Covers\MissingCovers;
use App\Domain\Isbn;
use App\Enums;
use App\Jobs\DownloadCover;
use App\Jobs\FindCover;
use App\Models\Book;
use App\Sources\Candidates;
use App\Sources\LookupResult;
use App\Sources\Source;
use App\Sources\SourceResolver;
use Illuminate\Support\Facades\Queue;

/*
 * El trabajo que le pregunta a las Fuentes si alguna tiene portada de un Libro
 * que no tiene ninguna (BIB-57). Es la mitad que va antes de DownloadCover:
 * aquél baja la imagen que una Fuente ya señaló, y éste va a buscar la
 * dirección cuando no hay, o cuando la que hay se rindió.
 */

/**
 * Una cascada de Fuentes que contestan lo que dice la prueba, en orden, y que
 * lleva la cuenta de a cuáles se les preguntó.
 *
 * @param  array<string, string|null>  $covers  la portada de cada Fuente, o null si no tiene
 */
function cascadeWith(array $covers): object
{
    $sources = [];
    $asked = new class
    {
        /** @var list<string> */
        public array $names = [];
    };

    foreach ($covers as $name => $coverUrl) {
        $sources[] = new class($name, $coverUrl, $asked) implements Source
        {
            public function __construct(
                private readonly string $name,
                private readonly ?string $coverUrl,
                private readonly object $asked,
            ) {}

            public function identifier(): Enums\Source
            {
                return Enums\Source::OpenLibrary;
            }

            public function byIsbn(Isbn $isbn): LookupResult
            {
                $this->asked->names[] = $this->name;

                return LookupResult::withRecord(
                    recordOf($isbn->value(), coverUrl: $this->coverUrl),
                );
            }

            public function byTitle(string $title, ?string $author = null): Candidates
            {
                return Candidates::none();
            }
        };
    }

    app()->instance(SourceResolver::class, new SourceResolver($sources));

    return $asked;
}

beforeEach(function () {
    Queue::fake();
});

it('guarda la portada de la primera Fuente que la tenga y encola la descarga', function () {
    $book = Book::factory()->coverGenerated()->create();

    cascadeWith([
        'open_library' => null,
        'google_books' => 'https://books.google.com/portada.jpg',
    ]);

    (new FindCover($book))->handle(app(SourceResolver::class));

    expect($book->fresh()->cover_source_url)->toBe('https://books.google.com/portada.jpg');

    Queue::assertPushed(DownloadCover::class);
});

it('no le pregunta a la segunda cuando la primera trae portada', function () {
    $book = Book::factory()->coverGenerated()->create();

    $asked = cascadeWith([
        'open_library' => 'https://covers.openlibrary.org/b/id/1-L.jpg',
        'google_books' => 'https://books.google.com/portada.jpg',
    ]);

    (new FindCover($book))->handle(app(SourceResolver::class));

    expect($asked->names)->toBe(['open_library'])
        ->and($book->fresh()->cover_source_url)->toBe('https://covers.openlibrary.org/b/id/1-L.jpg');
});

it('si ninguna la tiene, el Libro se queda como estaba y nada se encola', function () {
    $book = Book::factory()->coverGenerated()->create();

    cascadeWith([
        'open_library' => null,
        'google_books' => null,
    ]);

    (new FindCover($book))->handle(app(SourceResolver::class));

    expect($book->fresh()->cover_source_url)->toBeNull();

    Queue::assertNotPushed(DownloadCover::class);
});

it('se salta la dirección que ya se había rendido y sigue con la siguiente Fuente', function () {
    $book = Book::factory()->coverDownloadFailed()->create();
    $refused = $book->cover_source_url;

    $asked = cascadeWith([
        'open_library' => $refused,
        'google_books' => 'https://books.google.com/portada.jpg',
    ]);

    (new FindCover($book))->handle(app(SourceResolver::class));

    expect($asked->names)->toBe(['open_library', 'google_books'])
        ->and($book->fresh()->cover_source_url)->toBe('https://books.google.com/portada.jpg');
});

it('la dirección nueva borra la marca de la descarga que se rindió', function () {
    $book = Book::factory()->coverDownloadFailed()->create();

    cascadeWith(['google_books' => 'https://books.google.com/portada.jpg']);

    (new FindCover($book))->handle(app(SourceResolver::class));

    expect($book->fresh()->coverDownloadHasFailed())->toBeFalse()
        ->and($book->fresh()->cover_failure)->toBeNull();
});

it('no le pregunta a nadie por el Libro cuya Portada ya llegó', function () {
    $book = Book::factory()->create();

    $asked = cascadeWith(['open_library' => 'https://covers.openlibrary.org/b/id/1-L.jpg']);

    (new FindCover($book))->handle(app(SourceResolver::class));

    expect($asked->names)->toBe([]);

    Queue::assertNotPushed(DownloadCover::class);
});

it('no toca la fotografía que subió el dueño', function () {
    $book = Book::factory()->coverFromOwner()->create();
    $book->forceFill(['cover_path' => null, 'cover_thumbnail_path' => null])->saveQuietly();

    $asked = cascadeWith(['open_library' => 'https://covers.openlibrary.org/b/id/1-L.jpg']);

    (new FindCover($book->fresh()))->handle(app(SourceResolver::class));

    expect($asked->names)->toBe([]);
});

it('sin ISBN no hay nada que preguntar', function () {
    $book = Book::factory()->coverGenerated()->create();
    $book->forceFill(['isbn' => null])->saveQuietly();

    $asked = cascadeWith(['open_library' => 'https://covers.openlibrary.org/b/id/1-L.jpg']);

    (new FindCover($book->fresh()))->handle(app(SourceResolver::class));

    expect($asked->names)->toBe([]);
});

it('corrido dos veces seguidas, la segunda ya no alcanza al que se resolvió', function () {
    $book = Book::factory()->coverDownloadFailed()->create();

    cascadeWith([
        'open_library' => $book->cover_source_url,
        'google_books' => 'https://books.google.com/portada.jpg',
    ]);

    (new FindCover($book))->handle(app(SourceResolver::class));

    expect(MissingCovers::count())->toBe(0);
});

it('dos trabajos para el mismo Libro: el segundo ya no pregunta ni pisa lo que halló el primero', function () {
    $book = Book::factory()->coverGenerated()->create();

    $asked = cascadeWith([
        'open_library' => null,
        'google_books' => 'https://books.google.com/portada.jpg',
    ]);

    (new FindCover($book))->handle(app(SourceResolver::class));
    (new FindCover($book->fresh()))->handle(app(SourceResolver::class));

    expect($asked->names)->toBe(['open_library', 'google_books'])
        ->and($book->fresh()->cover_source_url)->toBe('https://books.google.com/portada.jpg');

    Queue::assertPushed(DownloadCover::class, 1);
});
