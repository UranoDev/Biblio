<?php

use App\Models\Book;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The mark of the Reading: the stamp and the line of the state (BIB-38)
|--------------------------------------------------------------------------
|
| The stamp over the Cover answers «¿lo leí?» and the line above the title
| answers «¿cuándo?». Each one is measured here for what it is supposed to say,
| and for what it is supposed to keep quiet about.
|
*/

/** What the line above the title ended up saying, wherever it was drawn. */
function markSaid(string $html): string
{
    preg_match('/data-test="reading-mark"[^>]*>(.*?)<\/span>/s', $html, $said);

    return trim($said[1] ?? '');
}

/** The stamp as it was drawn, from its opening tag to the end of the word. */
function stampDrawn(string $html): string
{
    preg_match('/<svg[^>]*data-test="reading-stamp".*?<\/svg>/s', $html, $stamp);

    return $stamp[0] ?? '';
}

it('sella la Portada de un Book leído en la ficha, y no la de uno por leer', function () {
    $this->actingAs(User::factory()->create());

    expect(Livewire::test('book-cover', ['book' => Book::factory()->read()->create()])->html())
        ->toContain('data-test="reading-stamp"')
        ->and(Livewire::test('book-cover', ['book' => Book::factory()->create()])->html())
        ->not->toContain('data-test="reading-stamp"');
});

it('vuelve a leer la Portada cuando la ficha guarda una Lectura', function () {
    $this->actingAs(User::factory()->create());

    $book = Book::factory()->create(['is_read' => false, 'read_on' => null]);

    // The block of the Cover is its own component: it does not re-render just
    // because the sheet around it did, so the sheet says when it saved one.
    $cover = Livewire::test('book-cover', ['book' => $book]);

    $book->markAsRead('2024-05-02');

    expect($cover->dispatch('reading-saved')->html())->toContain('data-test="reading-stamp"');
});

it('no escribe el año en el sello, en ninguna superficie', function () {
    $this->actingAs(User::factory()->create());

    $book = Book::factory()->read()->create([
        'title' => 'Cartucho',
        'read_on' => '2019-03-14',
        'year' => 1931,
        'is_hidden' => false,
    ]);

    $surfaces = [
        Livewire::test('pages::shelf')->html(),
        Livewire::test('book-cover', ['book' => $book])->html(),
        $this->get(route('showcase'))->getContent(),
        $this->get(route('showcase.book', $book))->getContent(),
    ];

    foreach ($surfaces as $html) {
        expect(stampDrawn($html))->toContain('LEÍDO')->not->toContain('2019');
    }
});

it('gira el sello con la Portada, no en contra de ella', function () {
    $this->actingAs(User::factory()->create());

    Book::factory()->read()->create(['title' => 'Cartucho']);

    $surfaces = [
        Livewire::test('pages::shelf')->html(),
        $this->get(route('showcase'))->getContent(),
    ];

    foreach ($surfaces as $html) {
        // The stamp is inside the box that carries the tilt: between the box
        // opening and the stamp there is nothing that closes it, so the word
        // turns with the Cover instead of holding level against it.
        preg_match('/rotate-\(--cover-tilt\)[^>]*>(.*?)data-test="reading-stamp"/s', $html, $between);

        expect($between[1] ?? null)->not->toBeNull()
            ->and($between[1])->not->toContain('</div>');
    }
});

it('dice el estado de la Lectura arriba del título, con su año cuando lo hay', function (
    callable $make,
    string $said,
) {
    $this->actingAs(User::factory()->create());

    $html = Livewire::test('pages::book', ['book' => $make()])->html();

    expect(markSaid($html))->toBe($said);
})->with([
    'por leer' => [fn () => Book::factory()->create(), 'Por leer'],
    'leído sin fecha' => [fn () => Book::factory()->readUndated()->create(), 'Leído'],
    'leído con fecha' => [fn () => Book::factory()->read()->create(['read_on' => '2024-11-02']), 'Leído, 2024'],
]);

it('deja el renglón del estado en su lugar en los tres estados', function () {
    $this->actingAs(User::factory()->create());

    $books = [
        Book::factory()->create(['title' => 'Por leer']),
        Book::factory()->readUndated()->create(['title' => 'Leído hace años']),
        Book::factory()->read()->create(['title' => 'Leído con fecha']),
    ];

    // Marking a Book as read does not move the sheet: the line is there in the
    // three states, always once and always above the title.
    foreach ($books as $book) {
        $html = Livewire::test('pages::book', ['book' => $book])->html();

        expect(substr_count($html, 'data-test="reading-mark"'))->toBe(1)
            ->and(strpos($html, 'data-test="reading-mark"'))
            ->toBeLessThan((int) strpos($html, 'data-flux-heading'));
    }
});

it('no le dice al visitante el año en que leí el Libro', function () {
    $book = Book::factory()->read()->create([
        'title' => 'Cartucho',
        'read_on' => '2019-03-14',
        'year' => 1931,
    ]);

    $html = $this->get(route('showcase.book', $book))->getContent();

    // The read date does not leave the back office (ADR-003), so out here the
    // two read states say the same thing.
    expect(markSaid($html))->toBe('Leído')
        ->and($html)->not->toContain('2019');
});
