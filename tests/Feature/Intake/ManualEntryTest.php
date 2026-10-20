<?php

use App\Enums\Source;
use App\Enums\Status;
use App\Models\Book;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();

    $this->actingAs(User::factory()->create());
});

/** The screen as it arrives from the Scan or from the TitleLookup. */
function manualEntry(array $from = []): Testable
{
    return Livewire::withQueryParams($from)->test('pages::manual-entry');
}

it('pide sesión', function () {
    auth()->logout();

    $this->get(route('manual-entry'))->assertRedirect(route('login'));
});

it('llega desdel Scan con el ISBN ya puesto', function () {
    $this->get(route('manual-entry', ['isbn' => '9788437604176']))
        ->assertOk()
        ->assertSee('9788437604176');

    manualEntry(['isbn' => '9788437604176'])
        ->assertSet('isbn', '9788437604176')
        ->assertSet('title', '');
});

it('llega desdel TitleLookup con el título ya puesto', function () {
    $this->get(route('manual-entry', ['title' => 'El llano en llamas']))
        ->assertOk()
        ->assertSee('El llano en llamas');

    manualEntry(['title' => 'El llano en llamas'])
        ->assertSet('title', 'El llano en llamas')
        ->assertSet('isbn', '');
});

it('llega desdel TitleLookup con el Autor ya puesto', function () {
    manualEntry(['title' => 'El llano en llamas', 'author' => 'Juan Rulfo'])
        ->assertSet('title', 'El llano en llamas')
        ->assertSet('authors', ['Juan Rulfo']);
});

it('registra un Book con sólo el título', function () {
    manualEntry()
        ->set('title', 'Cartucho')
        ->call('register')
        ->assertHasNoErrors();

    $book = Book::sole();

    expect($book->title)->toBe('Cartucho')
        ->and($book->isbn)->toBeNull()
        ->and($book->authors)->toBe([])
        ->and($book->publisher)->toBeNull()
        ->and($book->year)->toBeNull();
});

it('no registra nada sin título', function () {
    manualEntry()
        ->set('title', '  ')
        ->call('register')
        ->assertHasErrors('title');

    expect(Book::count())->toBe(0);
});

it('marcal Book como capturado a mano', function () {
    manualEntry()->set('title', 'Cartucho')->call('register');

    expect(Book::sole()->source)->toBe(Source::Manual);
});

it('conserval orden en que se escribieron los Autores', function () {
    manualEntry()
        ->set('title', 'Cien años de soledad')
        ->call('addAuthor')
        ->call('addAuthor')
        ->set('authors.0', 'Gabriel García Márquez')
        ->set('authors.1', 'Carmen Balcells')
        ->set('authors.2', 'Gregory Rabassa')
        ->call('register')
        ->assertHasNoErrors();

    expect(Book::sole()->authors)
        ->toBe(['Gabriel García Márquez', 'Carmen Balcells', 'Gregory Rabassa']);
});

it('descarta los renglones de Autor que quedaron en blanco', function () {
    manualEntry()
        ->set('title', 'Pedro Páramo')
        ->call('addAuthor')
        ->call('addAuthor')
        ->set('authors.0', 'Juan Rulfo')
        ->set('authors.1', '   ')
        ->set('authors.2', 'Jorge Zepeda')
        ->call('register');

    expect(Book::sole()->authors)->toBe(['Juan Rulfo', 'Jorge Zepeda']);
});

it('quita un Autor sin descolocar a los demás', function () {
    manualEntry()
        ->call('addAuthor')
        ->call('addAuthor')
        ->set('authors.0', 'Primero')
        ->set('authors.1', 'Segundo')
        ->set('authors.2', 'Tercero')
        ->call('removeAuthor', 1)
        ->assertSet('authors', ['Primero', 'Tercero']);
});

it('guardal Record completa cuando se escribe toda', function () {
    manualEntry()
        ->set('title', 'Noticias del Imperio')
        ->set('authors.0', 'Fernando del Paso')
        ->set('publisher', 'Diana')
        ->set('year', '1987')
        ->set('edition', 'Primera edición')
        ->set('isbn', '978-84-376-0417-6')
        ->call('register')
        ->assertHasNoErrors();

    $book = Book::sole();

    expect($book->title)->toBe('Noticias del Imperio')
        ->and($book->authors)->toBe(['Fernando del Paso'])
        ->and($book->publisher)->toBe('Diana')
        ->and($book->year)->toBe(1987)
        ->and($book->edition)->toBe('Primera edición')
        // The ISBN is stored normalized to thirteen digits, as in the Scan.
        ->and($book->isbn)->toBe('9788437604176');
});

it('registral año de un libro anterior a 1901', function () {
    manualEntry()
        ->set('title', 'Los bandidos de Río Frío')
        ->set('year', '1891')
        ->call('register')
        ->assertHasNoErrors();

    expect(Book::sole()->year)->toBe(1891);
});

it('avisal año que no es un año', function () {
    manualEntry()
        ->set('title', 'Cartucho')
        ->set('year', 'mil novecientos treinta y uno')
        ->call('register')
        ->assertHasErrors('year');

    expect(Book::count())->toBe(0);
});

it('avisal ISBN que ya está registrado en vez de duplicarlo', function () {
    Book::factory()->create([
        'isbn' => '9788437604176',
        'title' => 'Pedro Páramo',
    ]);

    manualEntry()
        ->set('title', 'Pedro Páramo, otra vez')
        ->set('isbn', '9788437604176')
        ->call('register')
        ->assertHasErrors('isbn')
        ->assertSee('«Pedro Páramo» ya está registrado con ese ISBN.');

    expect(Book::count())->toBe(1);
});

it('reconoce el ISBN ya registrado aunque se escriba con diez dígitos', function () {
    Book::factory()->create(['isbn' => '9788437604176', 'title' => 'Pedro Páramo']);

    manualEntry()
        ->set('title', 'Pedro Páramo, otra vez')
        ->set('isbn', '84-376-0417-X')
        ->call('register')
        ->assertHasErrors('isbn');

    expect(Book::count())->toBe(1);
});

it('avisal ISBN inválido', function () {
    manualEntry()
        ->set('title', 'Cartucho')
        ->set('isbn', '9788437604177')
        ->call('register')
        ->assertHasErrors('isbn')
        ->assertSee('«9788437604177» no es un ISBN.');

    expect(Book::count())->toBe(0);
});

it('registra como Leído por omisión', function () {
    manualEntry()->set('title', 'Cartucho')->call('register');

    expect(Book::sole()->status)->toBe(Status::Read);
});

it('ofrece las tres Situaciones como radio buttons, con Acervo, leído marcado', function () {
    manualEntry()
        ->assertSet('status', Status::Read->value)
        ->assertSee('Acervo, leído')
        ->assertSee('Acervo, por leer')
        ->assertSee('Por comprar')
        ->assertDontSee('segmented');
});

it('registra Wanted cuando se elige esa Status', function () {
    manualEntry()
        ->set('title', 'Cartucho')
        ->set('status', Status::Wanted->value)
        ->call('register')
        ->assertHasNoErrors();

    expect(Book::sole()->status)->toBe(Status::Wanted);
});

it('rechaza una Status que no es ninguna de las dos', function () {
    manualEntry()
        ->set('title', 'Cartucho')
        ->set('status', 'prestado')
        ->call('register')
        ->assertHasErrors('status')
        ->assertSee('La Situación es Acervo o Por comprar.');

    expect(Book::count())->toBe(0);
});

it('ofrece subir el Cover en cuanto el Book queda registrado', function () {
    manualEntry()
        ->set('title', 'Cartucho')
        ->call('register')
        ->assertSee('«Cartucho» quedó registrado')
        ->assertSeeLivewire('book-cover');
});

it('dejal Book registrado aunque no se le suba ningun Cover', function () {
    manualEntry()->set('title', 'Cartucho')->call('register');

    $book = Book::sole();

    expect($book->hasCover())->toBeFalse()
        // Without a source URL there is no download to retry: the only way is
        // to upload it by hand.
        ->and($book->canRetryCover())->toBeFalse();

    Queue::assertNothingPushed();
});

it('sube el Cover del Book recién capturado con el componente que ya existe', function () {
    Storage::fake();

    manualEntry()->set('title', 'Cartucho')->call('register');

    $book = Book::sole();

    Livewire::test('book-cover', ['book' => $book])
        ->set('file', UploadedFile::fake()->createWithContent('portada.jpg', testImage()))
        ->call('save')
        ->assertHasNoErrors();

    expect($book->refresh()->hasCover())->toBeTrue();
});

it('deja la pantalla en blanco para capturar el Book siguiente', function () {
    manualEntry(['isbn' => '9788437604176'])
        ->set('title', 'Cartucho')
        ->call('register')
        ->call('another')
        ->assertSet('title', '')
        ->assertSet('isbn', '')
        ->assertSet('authors', [''])
        ->assertSet('registered', null)
        ->assertSet('status', Status::Read->value)
        ->assertSee('Registrar el Libro');

    expect(Book::count())->toBe(1);
});

it('registra y regresa al Escaneo, que dice cuál quedó registrado', function () {
    manualEntry()
        ->set('title', 'Cartucho')
        ->call('registerAndScan')
        ->assertRedirect(route('scan'));

    expect(Book::sole()->title)->toBe('Cartucho');

    Livewire::test('pages::scan')
        ->assertSet('notice', '«Cartucho» quedó registrado a mano.');
});

it('no regresa al Escaneo cuando falta el título', function () {
    manualEntry()
        ->call('registerAndScan')
        ->assertHasErrors(['title' => 'required'])
        ->assertNoRedirect();

    expect(Book::count())->toBe(0);
});
