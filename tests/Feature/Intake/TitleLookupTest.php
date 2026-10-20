<?php

use App\Enums\Source;
use App\Enums\Status;
use App\Jobs\DownloadCover;
use App\Models\Book;
use App\Models\User;
use App\Sources\Candidates;
use App\Sources\LookupResult;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();

    $this->actingAs(User::factory()->create());
});

/** Typing the title and asking the Source for its editions. */
function lookUp(string $title = 'Pedro Páramo', ?string $author = null): Testable
{
    $screen = Livewire::test('pages::title-lookup')->set('title', $title);

    if ($author !== null) {
        $screen->set('author', $author);
    }

    return $screen->call('lookUp');
}

/** The key the screen points at the Candidate of that position with. */
function candidateKey(Testable $screen, int $position = 0): string
{
    return $screen->get('candidates')[$position]['key'];
}

/** The usual two editions: same title, different publisher and different year. */
function twoEditions(): Candidates
{
    return Candidates::of([
        candidateOf(
            isbn: '9788437604176',
            publisher: 'Cátedra',
            year: 1997,
            coverUrl: 'https://covers.example/catedra.jpg',
            reference: 'catedra-1997',
        ),
        candidateOf(
            isbn: '9786074454635',
            publisher: 'Editorial RM',
            year: 2005,
            coverUrl: 'https://covers.example/rm.jpg',
            reference: 'rm-2005',
        ),
    ]);
}

it('pide sesión', function () {
    auth()->logout();

    $this->get(route('title-lookup'))->assertRedirect(route('login'));
});

it('se abre con el Status en Owned', function () {
    $this->get(route('title-lookup'))->assertOk();

    Livewire::test('pages::title-lookup')
        ->assertSet('status', Status::Owned->value);
});

it('muestra los Candidates con Cover, autores, editorial y año', function () {
    sourcesReturn(twoEditions());

    lookUp()
        ->assertCount('candidates', 2)
        ->assertSee('Juan Rulfo')
        ->assertSee('Cátedra')
        ->assertSee('1997')
        ->assertSee('https://covers.example/catedra.jpg', false);
});

it('distingue dos ediciones del mismo título por editorial, año e ISBN', function () {
    sourcesReturn(twoEditions());

    lookUp()
        ->assertSee('Cátedra')
        ->assertSee('Editorial RM')
        ->assertSee('1997')
        ->assertSee('2005')
        ->assertSee('9788437604176')
        ->assertSee('9786074454635');
});

it('le mandal Autor a la Source para acotar', function () {
    $received = [];

    sourcesReturn(function (string $title, ?string $author) use (&$received): Candidates {
        $received = [$title, $author];

        return twoEditions();
    });

    lookUp('Pedro Páramo', 'Juan Rulfo');

    expect($received)->toBe(['Pedro Páramo', 'Juan Rulfo']);
});

it('consulta sin Autor cuando no se escribió ninguno', function () {
    $received = ['', 'sin pasar'];

    sourcesReturn(function (string $title, ?string $author) use (&$received): Candidates {
        $received = [$title, $author];

        return twoEditions();
    });

    lookUp('Pedro Páramo');

    expect($received)->toBe(['Pedro Páramo', null]);
});

it('no consulta nada sin título', function () {
    sourcesReturn(twoEditions());

    Livewire::test('pages::title-lookup')
        ->set('title', '  ')
        ->call('lookUp')
        ->assertHasErrors('title')
        ->assertCount('candidates', 0);
});

it('registral Book con el Status elegida al elegir el Candidate', function () {
    sourcesReturn(twoEditions());

    $screen = lookUp()->set('status', Status::Owned->value);

    $screen->call('choose', candidateKey($screen, 1));

    $book = Book::sole();

    expect($book->isbn)->toBe('9786074454635')
        ->and($book->publisher)->toBe('Editorial RM')
        ->and($book->year)->toBe(2005)
        ->and($book->status)->toBe(Status::Owned);

    $screen->assertSet('registered', $book->getKey())->assertSee('quedó registrado');
});

it('registra Owned cuando no se cambial Status', function () {
    sourcesReturn(twoEditions());

    $screen = lookUp();

    $screen->call('choose', candidateKey($screen));

    expect(Book::sole()->status)->toBe(Status::Owned);
});

it('encolal Cover del Candidate que se eligió', function () {
    sourcesReturn(twoEditions());

    $screen = lookUp();

    $screen->call('choose', candidateKey($screen));

    Queue::assertPushed(DownloadCover::class);

    expect(Book::sole()->cover_source_url)->toBe('https://covers.example/catedra.jpg');
});

it('registral Candidate que no trae ISBN', function () {
    sourcesReturn(Candidates::of([
        candidateOf(isbn: null, title: 'El llano en llamas', reference: 'sin-isbn'),
    ]));

    $screen = lookUp('El llano en llamas');

    $screen->assertSee('no trae ISBN')
        ->call('choose', candidateKey($screen));

    $book = Book::sole();

    expect($book->title)->toBe('El llano en llamas')
        ->and($book->isbn)->toBeNull();
});

it('avisa cuál es el Book que ya tenía ese ISBN, en vez de duplicarlo', function () {
    Book::factory()->create(['isbn' => '9788437604176', 'title' => 'Pedro Páramo']);

    sourcesReturn(twoEditions());

    $screen = lookUp();

    $screen->call('choose', candidateKey($screen))
        ->assertSet('registered', null)
        ->assertSee('ya está registrado con ese ISBN');

    expect(Book::count())->toBe(1);
});

it('ofrece ir al Book que ya estaba, en cuanto exista su Record', function () {
    // The Book sheet arrives with BIB-14. Until then there is nowhere to send
    // the person, and the notice just says which Book it is.
    Route::middleware('web')->get('books/{book}', fn () => '')->name('book');

    Book::factory()->create(['isbn' => '9788437604176', 'title' => 'Pedro Páramo']);

    sourcesReturn(twoEditions());

    $screen = lookUp();

    $screen->call('choose', candidateKey($screen))
        ->assertSee('Ir a «Pedro Páramo»');
});

it('deja elegir otra edición después de avisar que una ya estaba', function () {
    Book::factory()->create(['isbn' => '9788437604176']);

    sourcesReturn(twoEditions());

    $screen = lookUp();

    $screen->call('choose', candidateKey($screen))
        ->assertCount('candidates', 2)
        ->call('choose', candidateKey($screen, 1));

    expect(Book::count())->toBe(2)
        ->and(Book::query()->where('isbn', '9786074454635')->exists())->toBeTrue();
});

it('ofrece ManualEntry cuando ninguna Source tiene ese título, conservando lo escrito', function () {
    sourcesReturn(Candidates::none());

    lookUp('Un título que nadie tiene', 'Quien sea')
        ->assertSet('withoutEditions', true)
        ->assertSee('Ninguna Fuente tiene ediciones')
        ->assertSee(route('manual-entry', [
            'title' => 'Un título que nadie tiene',
            'author' => 'Quien sea',
        ]));
});

it('la Source que no pudo contestar pide reintentar y no ofrece ManualEntry', function () {
    sourcesReturn(Candidates::unavailable('HTTP 429'));

    lookUp()
        ->assertSet('withoutEditions', false)
        ->assertSee('no pudieron contestar')
        ->assertSee('Vuelve a intentarlo')
        ->assertDontSee('Capturar a mano');
});

it('deja la pantalla lista paral Book siguiente', function () {
    sourcesReturn(twoEditions());

    $screen = lookUp();

    $screen->call('choose', candidateKey($screen))
        ->call('another')
        ->assertSet('title', '')
        ->assertSet('author', '')
        ->assertSet('registered', null)
        ->assertSet('status', Status::Owned->value)
        ->assertCount('candidates', 0);
});

it('no usa la palabra «búsqueda»: aquí se consulta la Source', function () {
    sourcesReturn(twoEditions());

    $screen = lookUp();

    foreach (['úsqueda', 'uscar', 'usca ', 'uscador'] as $forbidden) {
        $screen->assertDontSee($forbidden);
    }

    $screen->assertSee('Consultar la Fuente');
});

it('registra el Libro solo con título y autor, sin consultar la Fuente ni pedir ISBN', function () {
    // The Source is not asked: what gets registered is what was typed.
    sourcesAnswer(LookupResult::withoutRecord(), fn () => throw new LogicException('The Source was asked.'));

    Livewire::test('pages::title-lookup')
        ->set('title', 'Apuntes de mi abuelo')
        ->set('author', 'Juan Pérez')
        ->call('registerAsTyped')
        ->assertSet('notice', '«Apuntes de mi abuelo» quedó registrado sin ISBN. Situación: Acervo.');

    $book = Book::sole();

    expect($book->title)->toBe('Apuntes de mi abuelo')
        ->and($book->authors)->toBe(['Juan Pérez'])
        ->and($book->isbn)->toBeNull()
        ->and($book->source)->toBe(Source::Manual)
        ->and($book->status)->toBe(Status::Owned);
});

it('registra solo con título cuando el autor queda en blanco', function () {
    sourcesAnswer(LookupResult::withoutRecord());

    Livewire::test('pages::title-lookup')
        ->set('title', 'Recetario familiar')
        ->call('registerAsTyped');

    expect(Book::sole()->authors)->toBe([]);
});

it('registra en Por comprar cuando esa es la Situación elegida', function () {
    sourcesAnswer(LookupResult::withoutRecord());

    Livewire::test('pages::title-lookup')
        ->set('title', 'Recetario familiar')
        ->set('status', Status::Wanted->value)
        ->call('registerAsTyped')
        ->assertSet('notice', '«Recetario familiar» quedó registrado sin ISBN. Situación: Por comprar.');

    expect(Book::sole()->status)->toBe(Status::Wanted)
        ->and(Book::sole()->isbn)->toBeNull();
});

it('no registra sin título', function () {
    sourcesAnswer(LookupResult::withoutRecord());

    Livewire::test('pages::title-lookup')
        ->set('author', 'Juan Pérez')
        ->call('registerAsTyped')
        ->assertHasErrors(['title' => 'required']);

    expect(Book::count())->toBe(0);
});

it('no registra en otra Situación que Acervo o Por comprar', function () {
    sourcesAnswer(LookupResult::withoutRecord());

    Livewire::test('pages::title-lookup')
        ->set('title', 'Recetario familiar')
        ->set('status', Status::Read->value)
        ->call('registerAsTyped')
        ->assertHasErrors('status');

    expect(Book::count())->toBe(0);
});

it('muestra un solo botón de registro solo con título y autor', function () {
    Livewire::test('pages::title-lookup')
        ->assertSee('Registrar solo con título y autor')
        ->assertDontSee('Registrar en Por comprar');
});

it('al guardar ofrece capturar otro Libro por título o ir al Escaneo', function () {
    sourcesAnswer(LookupResult::withoutRecord());

    Livewire::test('pages::title-lookup')
        ->set('title', 'Recetario familiar')
        ->call('registerAsTyped')
        ->assertSee('Capturar otro Libro por título')
        ->assertSee('Ir al Escaneo')
        ->assertSee('href="'.route('scan').'"', false);
});
