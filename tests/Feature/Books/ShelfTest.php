<?php

use App\Enums\ReadingStatus;
use App\Enums\Status;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/** The Shelf, as it opens from the sidebar. */
function shelf(): Testable
{
    return Livewire::test('pages::shelf');
}

it('pide sesión', function () {
    auth()->logout();

    $this->get(route('shelf'))->assertRedirect(route('login'));
});

it('abre el Acervo', function () {
    Book::factory()->create(['title' => 'Pedro Páramo']);

    $this->get(route('shelf'))
        ->assertOk()
        ->assertSee('Acervo')
        ->assertSee('Pedro Páramo');
});

it('muestra el título y los autores de cada Book', function () {
    Book::factory()->create([
        'title' => 'Cartucho',
        'authors' => ['Nellie Campobello'],
    ]);

    shelf()
        ->assertSee('Cartucho')
        ->assertSee('Nellie Campobello');
});

/*
|--------------------------------------------------------------------------
| The Cover
|--------------------------------------------------------------------------
*/

it('usa la versión reducida del Cover y no la original', function () {
    $book = Book::factory()->create();

    $this->get(route('shelf'))
        ->assertSee($book->coverThumbnailUrl())
        ->assertDontSee($book->coverUrl());
});

it('dibuja la pieza generada del Book que no tiene imagen guardada, sin dejar hueco', function () {
    $stored = Book::factory()->create(['title' => 'Con foto']);
    $generated = Book::factory()->coverGenerated()->create(['title' => 'Sin foto']);

    $page = $this->get(route('shelf'))->assertOk();

    // The generated piece takes exactly the same place: both Books are in the
    // grid, and only the one with a file asks for an image.
    $page->assertSee('Con foto')
        ->assertSee('Sin foto')
        ->assertSee($stored->coverThumbnailUrl())
        ->assertSee($generated->generatedCover()->label());

    expect($generated->coverThumbnailUrl())->toBeNull();
});

/*
|--------------------------------------------------------------------------
| The three Reading states
|--------------------------------------------------------------------------
*/

it('sella los Books leídos y deja sin sellar los que faltan por leer', function () {
    Book::factory()->create(['title' => 'Sin leer']);
    Book::factory()->readUndated()->create(['title' => 'Leído hace años']);
    Book::factory()->read()->create(['title' => 'Leído con fecha']);

    $html = $this->get(route('shelf'))->assertOk()->getContent();

    expect(substr_count($html, 'data-test="reading-stamp"'))->toBe(2)
        ->and($html)->toContain('data-reading="'.ReadingStatus::ReadUndated->value.'"')
        ->and($html)->toContain('data-reading="'.ReadingStatus::ReadDated->value.'"')
        ->and($html)->not->toContain('data-reading="'.ReadingStatus::Unread->value.'"');
});

it('no escribe el año de la Lectura en la retícula', function () {
    Book::factory()->read()->create([
        'title' => 'Cartucho',
        'read_on' => '2019-03-14',
        'year' => 1931,
    ]);

    // The grid answers «¿lo leí?» and the sheet answers «¿cuándo?»: a Book read
    // in 2019 and one read without a date carry the same stamp (BIB-38).
    shelf()->assertDontSee('2019');
});

it('deja filtrar por los tres estados con su nombre', function () {
    foreach (ReadingStatus::cases() as $state) {
        shelf()->assertSee($state->label());
    }
});

/*
|--------------------------------------------------------------------------
| The Search
|--------------------------------------------------------------------------
*/

it('busca por título mientras se escribe', function () {
    Book::factory()->create(['title' => 'Pedro Páramo']);
    Book::factory()->create(['title' => 'La feria']);

    shelf()
        ->set('search', 'páramo')
        ->assertSee('Pedro Páramo')
        ->assertDontSee('La feria');
});

it('busca por autor', function () {
    Book::factory()->create(['title' => 'El llano en llamas', 'authors' => ['Juan Rulfo']]);
    Book::factory()->create(['title' => 'La feria', 'authors' => ['Juan José Arreola']]);

    shelf()
        ->set('search', 'rulfo')
        ->assertSee('El llano en llamas')
        ->assertDontSee('La feria');
});

it('busca un autor acentuado, esté como esté guardado el JSON', function () {
    Book::factory()->create(['title' => 'Canción de tumba', 'authors' => ['Julián Herbert']]);
    Book::factory()->create(['title' => 'La feria', 'authors' => ['Juan José Arreola']]);

    shelf()
        ->set('search', 'julián')
        ->assertSee('Canción de tumba')
        ->assertDontSee('La feria');
});

it('ignora los espacios de alrededor de lo que se busca', function () {
    Book::factory()->create(['title' => 'Cartucho']);

    shelf()->set('search', '  cartucho  ')->assertSee('Cartucho');
});

it('sin nada escrito no filtra', function () {
    Book::factory()->create(['title' => 'Cartucho']);
    Book::factory()->create(['title' => 'La feria']);

    shelf()->set('search', '')
        ->assertSee('Cartucho')
        ->assertSee('La feria');
});

it('trata el comodín de SQL como un carácter más', function () {
    Book::factory()->create(['title' => 'Cartucho']);

    // A `%` typed into the box is a percent sign, not «everything».
    shelf()->set('search', '%')->assertDontSee('Cartucho');
});

it('dice cuando nada coincide', function () {
    Book::factory()->create(['title' => 'Cartucho']);

    shelf()->set('search', 'ninguno')
        ->assertSee('Ningún Libro coincide')
        ->assertDontSee('Cartucho');
});

/*
|--------------------------------------------------------------------------
| Owned and Wanted
|--------------------------------------------------------------------------
*/

it('abre en el Acervo y deja fuera lo que está por comprar', function () {
    Book::factory()->owned()->create(['title' => 'Cartucho']);
    Book::factory()->wanted()->create(['title' => 'La feria']);

    shelf()
        ->assertSet('status', Status::Owned->value)
        ->assertSee('Cartucho')
        ->assertDontSee('La feria');
});

it('separa lo que está por comprar', function () {
    Book::factory()->owned()->create(['title' => 'Cartucho']);
    Book::factory()->wanted()->create(['title' => 'La feria']);

    shelf()
        ->set('status', Status::Wanted->value)
        ->assertSee('La feria')
        ->assertDontSee('Cartucho');
});

it('muestra las dos situaciones juntas', function () {
    Book::factory()->owned()->create(['title' => 'Cartucho']);
    Book::factory()->wanted()->create(['title' => 'La feria']);

    shelf()
        ->set('status', '')
        ->assertSee('Cartucho')
        ->assertSee('La feria');
});

it('marca el Book que está por comprar', function () {
    Book::factory()->wanted()->create(['title' => 'La feria']);

    shelf()->set('status', Status::Wanted->value)->assertSee('Por comprar');
});

it('cuenta cada situación sin que la cuenta dependa de la pestaña abierta', function () {
    Book::factory()->owned()->count(3)->create();
    Book::factory()->wanted()->count(2)->create();

    $counts = fn (string $status): array => shelf()->set('status', $status)->get('counts');

    expect($counts(Status::Owned->value))->toBe(['' => 5, 'owned' => 3, 'wanted' => 2])
        ->and($counts(Status::Wanted->value))->toBe(['' => 5, 'owned' => 3, 'wanted' => 2])
        ->and($counts(''))->toBe(['' => 5, 'owned' => 3, 'wanted' => 2]);
});

it('cuenta lo que la Search dejó, no todo el acervo', function () {
    Book::factory()->owned()->create(['title' => 'Cartucho']);
    Book::factory()->owned()->create(['title' => 'La feria']);
    Book::factory()->wanted()->create(['title' => 'Cartucho de otra edición']);

    expect(shelf()->set('search', 'cartucho')->get('counts'))
        ->toBe(['' => 2, 'owned' => 1, 'wanted' => 1]);
});

it('avisa cuando lo que se busca está en la otra situación', function () {
    Book::factory()->wanted()->create(['title' => 'La feria']);

    shelf()
        ->set('search', 'feria')
        ->assertSee('Ningún Libro coincide')
        ->assertSee('Hay 1 en «Por comprar»');
});

it('no avisa de la otra situación cuando ahí tampoco hay nada', function () {
    Book::factory()->owned()->create(['title' => 'Cartucho']);

    shelf()
        ->set('search', 'ninguno')
        ->assertSee('Ningún Libro coincide')
        ->assertDontSee('Por comprar»');
});

/*
|--------------------------------------------------------------------------
| The filters
|--------------------------------------------------------------------------
*/

it('filtra por ReadingStatus', function () {
    Book::factory()->create(['title' => 'Sin leer']);
    Book::factory()->readUndated()->create(['title' => 'Leído hace años']);
    Book::factory()->read()->create(['title' => 'Leído con fecha']);

    shelf()->set('reading', ReadingStatus::Unread->value)
        ->assertSee('Sin leer')
        ->assertDontSee('Leído hace años')
        ->assertDontSee('Leído con fecha');

    shelf()->set('reading', ReadingStatus::ReadUndated->value)
        ->assertSee('Leído hace años')
        ->assertDontSee('Leído con fecha');

    shelf()->set('reading', ReadingStatus::ReadDated->value)
        ->assertSee('Leído con fecha')
        ->assertDontSee('Leído hace años');
});

it('filtra por Category', function () {
    $novel = Category::factory()->named('Novela mexicana')->create();

    $one = Book::factory()->create(['title' => 'Cartucho']);
    $one->categories()->attach($novel);

    Book::factory()->create(['title' => 'La feria']);

    shelf()
        ->set('category', $novel->slug)
        ->assertSee('Cartucho')
        ->assertDontSee('La feria');
});

it('ofrece sólo las Categories que algún Book tiene', function () {
    $used = Category::factory()->named('Novela mexicana')->create();
    Category::factory()->named('Poesía')->create();

    Book::factory()->create()->categories()->attach($used);

    expect(shelf()->get('categories')->pluck('name')->all())->toBe(['Novela mexicana']);
});

it('funciona en una biblioteca sin ninguna Category', function () {
    Book::factory()->count(2)->create();

    $page = shelf();

    expect($page->get('categories'))->toBeEmpty();

    // The filter is not drawn: an empty one would promise a way of looking that
    // nobody can use yet.
    $page->assertDontSee('Todas las categorías');
});

it('combina la Search con los filtros', function () {
    $novel = Category::factory()->named('Novela mexicana')->create();

    $wanted = Book::factory()->wanted()->read()->create(['title' => 'Cartucho']);
    $wanted->categories()->attach($novel);

    $other = Book::factory()->wanted()->create(['title' => 'Cartucho sin leer']);
    $other->categories()->attach($novel);

    shelf()
        ->set('status', Status::Wanted->value)
        ->set('search', 'cartucho')
        ->set('reading', ReadingStatus::ReadDated->value)
        ->set('category', $novel->slug)
        ->assertSee('Cartucho')
        ->assertDontSee('Cartucho sin leer');
});

it('quita todos los filtros de un golpe', function () {
    Book::factory()->owned()->create(['title' => 'Cartucho']);

    shelf()
        ->set('search', 'ninguno')
        ->set('status', '')
        ->set('reading', ReadingStatus::ReadDated->value)
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('status', Status::Owned->value)
        ->assertSet('reading', '')
        ->assertSet('category', '')
        ->assertSee('Cartucho');
});

/*
|--------------------------------------------------------------------------
| Hidden, and getting to the sheet
|--------------------------------------------------------------------------
*/

it('muestra el Book oculto con su marca: esta pantalla es privada', function () {
    Book::factory()->hidden()->create(['title' => 'Cartucho']);

    shelf()
        ->assertSee('Cartucho')
        ->assertSee('Oculto');
});

it('no marca como oculto al Book que sí sale en la Vitrina', function () {
    Book::factory()->create(['title' => 'Cartucho']);

    shelf()->assertDontSee('Oculto');
});

it('lleva a la ficha de cada Book', function () {
    $book = Book::factory()->create();

    $this->get(route('shelf'))->assertSee(route('book', $book));
});

/*
|--------------------------------------------------------------------------
| Two hundred Books
|--------------------------------------------------------------------------
*/

it('carga sin consultas N+1 con doscientos Books', function () {
    $category = Category::factory()->create();

    $counted = function (): int {
        $queries = 0;

        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->get(route('shelf'))->assertOk();

        return $queries;
    };

    Book::factory()->count(5)->create()
        ->each(fn (Book $book) => $book->categories()->attach($category));

    $few = $counted();

    Book::factory()->count(195)->create()
        ->each(fn (Book $book) => $book->categories()->attach($category));

    $many = $counted();

    // Not «few queries» but «the same queries»: what a grid of two hundred
    // Covers cannot do is ask one more question per Book.
    expect($many)->toBe($few)
        ->and($few)->toBeLessThanOrEqual(10);
});
