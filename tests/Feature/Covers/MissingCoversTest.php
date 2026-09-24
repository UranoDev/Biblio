<?php

use App\Covers\MissingCovers;
use App\Jobs\FindCover;
use App\Models\Book;
use Illuminate\Support\Facades\Queue;

/*
 * Los Libros que muestran la portada generada y a los que todavía se les puede
 * preguntar por una imagen (BIB-57).
 *
 * Open Library contesta muchos ISBN sin traer portada de esa edición, así que
 * el Libro quedó registrado con el campo vacío y nada volvió nunca por él.
 * BIB-55 lo arregla hacia adelante, en la captura; esto es el camino de
 * regreso.
 */

beforeEach(function () {
    Queue::fake();
});

it('encuentra al que nunca tuvo de dónde bajarla y al que ya se rindió', function () {
    $neverAsked = Book::factory()->coverGenerated()->create();
    $gaveUp = Book::factory()->coverDownloadFailed()->create();

    expect(MissingCovers::books()->pluck('id')->all())
        ->toBe([$neverAsked->id, $gaveUp->id]);
});

it('deja fuera al que ya tiene portada guardada', function () {
    Book::factory()->create();
    Book::factory()->coverFromOwner()->create();

    expect(MissingCovers::count())->toBe(0);
});

it('deja fuera al que tiene descarga en camino, porque ya se le está contestando', function () {
    Book::factory()->coverPending()->create();

    expect(MissingCovers::count())->toBe(0);
});

it('deja fuera al que no tiene ISBN, porque no hay con qué preguntar', function () {
    $book = Book::factory()->coverGenerated()->create();
    $book->forceFill(['isbn' => null])->saveQuietly();

    expect(MissingCovers::count())->toBe(0);
});

it('incluye a los Ocultos: la portada se ve en el acervo aunque no salga en la vitrina', function () {
    Book::factory()->coverGenerated()->create(['is_hidden' => true]);

    expect(MissingCovers::count())->toBe(1);
});

it('los encola, uno por Libro', function () {
    Book::factory()->coverGenerated()->count(3)->create();

    expect(MissingCovers::queue())->toBe(3);

    Queue::assertPushed(FindCover::class, 3);
});

it('encola los que se le entregan y no vuelve a buscarlos', function () {
    $books = Book::factory()->coverGenerated()->count(2)->create();

    Book::factory()->coverGenerated()->create();

    expect(MissingCovers::queue($books))->toBe(2);

    Queue::assertPushed(FindCover::class, 2);
});
