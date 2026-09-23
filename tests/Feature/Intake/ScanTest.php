<?php

use App\Enums\Status;
use App\Jobs\DownloadCover;
use App\Models\Book;
use App\Models\User;
use App\Sources\LookupResult;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();

    $this->actingAs(User::factory()->create());
});

/** Scanning an ISBN is typing it into the field and having the Enter arrive. */
function scan(Testable $screen, string $isbn): Testable
{
    return $screen->set('isbn', $isbn)->call('register');
}

it('pide sesión', function () {
    auth()->logout();

    $this->get(route('scan'))->assertRedirect(route('login'));
});

it('se abre con el campo del ISBN enfocado', function () {
    $this->get(route('scan'))
        ->assertOk()
        ->assertSee('autofocus', false)
        ->assertSee('x-ref="isbn"', false);
});

it('devuelve el foco al campo después de cada Escaneo', function () {
    sourcesAnswer(fn ($isbn) => LookupResult::withRecord(recordOf((string) $isbn)));

    scan(Livewire::test('pages::scan'), testIsbn(1))
        ->assertDispatched('focus-isbn')
        ->assertSet('isbn', '');
});

it('registra veinte Libros seguidos sin un clic entre uno y otro', function () {
    sourcesAnswer(fn ($isbn) => LookupResult::withRecord(recordOf(
        isbn: (string) $isbn,
        title: 'Libro '.$isbn,
    )));

    $screen = Livewire::test('pages::scan');

    foreach (range(1, 20) as $number) {
        scan($screen, testIsbn($number))->assertDispatched('focus-isbn');
    }

    expect(Book::count())->toBe(20);

    $screen->assertCount('burst', 20);
});

it('no abre ningún modal, pase lo que pase con el Escaneo', function () {
    $outcomes = [
        fn () => sourcesAnswer(LookupResult::withRecord(recordOf('9788437604176'))),
        fn () => sourcesAnswer(LookupResult::withoutRecord()),
        fn () => sourcesAnswer(LookupResult::unavailable('Google Books: cuota agotada')),
    ];

    foreach ($outcomes as $prepare) {
        $prepare();

        scan(Livewire::test('pages::scan'), '9788437604176')
            ->assertDispatched('focus-isbn')
            ->assertDontSee('<ui-modal', false)
            ->assertDontSee('role="dialog"', false)
            ->assertDontSee('data-flux-modal', false);
    }

    // And the invalid ISBN, which never gets to query any Source.
    scan(Livewire::test('pages::scan'), '1234567890')
        ->assertDispatched('focus-isbn')
        ->assertDontSee('<ui-modal', false)
        ->assertDontSee('role="dialog"', false);
});

it('avisa que el Libro ya estaba en el Acervo', function () {
    Book::factory()->owned()->create([
        'isbn' => '9788437604176',
        'title' => 'Pedro Páramo',
    ]);

    scan(Livewire::test('pages::scan'), '9788437604176')
        ->assertSee('«Pedro Páramo» ya está en el Acervo.')
        ->assertDontSee('Deshacer');

    expect(Book::count())->toBe(1);
});

it('avisa el paso de Por comprar al Acervo y lo deja deshacer', function () {
    $book = Book::factory()->wanted()->create([
        'isbn' => '9788437604176',
        'title' => 'Pedro Páramo',
    ]);

    $screen = scan(Livewire::test('pages::scan'), '9788437604176')
        ->assertSee('«Pedro Páramo» estaba Por comprar y pasó al Acervo.')
        ->assertSee('Deshacer');

    expect($book->fresh()->status)->toBe(Status::Owned);

    $screen->call('undo', $screen->get('burst')[0]['key'])
        ->assertSee('«Pedro Páramo» vuelve a estar Por comprar.')
        ->assertSee('Regresó a Por comprar')
        ->assertDispatched('focus-isbn');

    expect($book->fresh()->status)->toBe(Status::Wanted);
});

it('muestra la ráfaga con lo más reciente arriba', function () {
    sourcesAnswer(fn ($isbn) => LookupResult::withRecord(recordOf(
        isbn: (string) $isbn,
        title: 'Libro '.$isbn,
    )));

    $screen = Livewire::test('pages::scan');

    scan($screen, testIsbn(1));
    scan($screen, testIsbn(2));

    $burst = $screen->get('burst');

    expect($burst[0]['isbn'])->toBe(testIsbn(2))
        ->and($burst[1]['isbn'])->toBe(testIsbn(1));
});

it('guarda el ISBN que ninguna Fuente conoce para la Captura manual', function () {
    sourcesAnswer(LookupResult::withoutRecord());

    scan(Livewire::test('pages::scan'), '978-84-376-0417-6')
        ->assertSet('isbnWithoutSource', '9788437604176')
        ->assertSee('Ninguna Fuente conoce el ISBN 9788437604176.')
        ->assertSet('isbn', '');
});

it('ofrece la Captura manual con el ISBN que ninguna Fuente conoce', function () {
    sourcesAnswer(LookupResult::withoutRecord());

    scan(Livewire::test('pages::scan'), '9788437604176')
        ->assertSee(route('manual-entry', ['isbn' => '9788437604176']), false);
});

it('avisa el ISBN inválido y no interrumpe la ráfaga', function () {
    sourcesAnswer(fn ($isbn) => LookupResult::withRecord(recordOf((string) $isbn)));

    $screen = scan(Livewire::test('pages::scan'), '9788437604177')
        ->assertSee('«9788437604177» no es un ISBN.');

    expect(Book::count())->toBe(0);

    scan($screen, testIsbn(1))->assertSee('quedó registrado.');

    expect(Book::count())->toBe(1);
});

it('encola la Portada del Libro que se registra en la ráfaga', function () {
    sourcesAnswer(LookupResult::withRecord(recordOf(
        isbn: '9788437604176',
        coverUrl: 'https://covers.openlibrary.org/b/id/12345-L.jpg',
    )));

    scan(Livewire::test('pages::scan'), '9788437604176');

    Queue::assertPushed(DownloadCover::class);
});

it('no registra nada cuando el campo llega vacío', function () {
    scan(Livewire::test('pages::scan'), '   ')
        ->assertCount('burst', 0)
        ->assertDispatched('focus-isbn');

    expect(Book::count())->toBe(0);
});
