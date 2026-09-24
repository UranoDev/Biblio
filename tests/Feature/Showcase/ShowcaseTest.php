<?php

use App\Models\Book;
use App\Models\Category;
use App\Showcase\Showcase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * The Showcase, at the root of the site: what a visitor with no session sees.
 *
 * Half of this file is about what does NOT come out. A forgotten filter breaks
 * nothing and warns nobody (ADR-003), so the only thing that catches it is a
 * test per public surface that goes looking for what should not be there.
 */

beforeEach(function () {
    Storage::fake();
});

it('abre sin sesión y no manda al login', function () {
    Book::factory()->create(['title' => 'Pedro Páramo']);

    $this->get(route('showcase'))
        ->assertOk()
        ->assertSee('Pedro Páramo');
});

it('muestra el Cover, el título y los autores de cada Libro visible', function () {
    Book::factory()->create([
        'title' => 'La montaña mágica',
        'authors' => ['Thomas Mann'],
    ]);

    $this->get(route('showcase'))
        ->assertSee('La montaña mágica')
        ->assertSee('Thomas Mann');
});

it('enseña los que están Por comprar en una sección aparte, con palabras de visitante', function () {
    Book::factory()->wanted()->create(['title' => 'Los detectives salvajes']);

    $this->get(route('showcase'))
        ->assertSee('Los que me faltan')
        ->assertSee('Los detectives salvajes')
        ->assertDontSee('Por comprar');
});

it('no arma la sección de los que faltan cuando no falta ninguno', function () {
    Book::factory()->owned()->create();

    $this->get(route('showcase'))->assertDontSee('Los que me faltan');
});

it('deja ver la marca de Lectura de cada Libro', function () {
    Book::factory()->read()->create(['title' => 'Rayuela']);

    $this->get(route('showcase'))->assertSee('Leído');
});

it('no le nombra al visitante una fecha de lectura que no le enseña', function () {
    Book::factory()->readUndated()->create(['title' => 'Leído hace años']);

    $this->get(route('showcase'))
        ->assertSee('Leído')
        ->assertDontSee('sin fecha');
});

it('no saca un Libro oculto, aunque esté en el acervo', function () {
    Book::factory()->hidden()->create(['title' => 'El libro que no sale']);
    Book::factory()->create(['title' => 'El libro que sí sale']);

    $this->get(route('showcase'))
        ->assertSee('El libro que sí sale')
        ->assertDontSee('El libro que no sale');
});

it('tampoco saca un Libro oculto de los que están Por comprar', function () {
    Book::factory()->wanted()->hidden()->create(['title' => 'El deseo escondido']);
    Book::factory()->wanted()->create(['title' => 'El deseo a la vista']);

    $this->get(route('showcase'))
        ->assertSee('El deseo a la vista')
        ->assertDontSee('El deseo escondido');
});

it('no sirve la fecha de lectura, el apunte ni el préstamo en el HTML', function () {
    $book = Book::factory()->lent('Rosario')->create([
        'title' => 'El llano en llamas',
        'is_read' => true,
        'read_on' => '2019-03-14',
        'note' => 'Lo que me dejó este libro y nadie más tiene por qué leer.',
    ]);

    $response = $this->get(route('showcase'));

    $response->assertSee('El llano en llamas')
        ->assertDontSee('Rosario')
        ->assertDontSee('nadie más tiene por qué leer')
        ->assertDontSee('2019-03-14')
        ->assertDontSee('14/03/2019');

    expect($response->getContent())->not->toContain((string) $book->lent_on?->format('Y-m-d'));
});

it('dibuja doscientos Libros con las mismas consultas que uno', function () {
    Book::factory()->create();

    DB::enableQueryLog();
    $this->get(route('showcase'))->assertOk();
    $one = count(DB::getQueryLog());

    Book::factory()->count(199)->create();

    DB::flushQueryLog();
    $this->get(route('showcase'))->assertOk();
    $twoHundred = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect($twoHundred)->toBe($one);
});

it('no acepta que le escriban, ni con una petición armada a mano', function (string $method) {
    $this->call($method, route('showcase'))->assertMethodNotAllowed();
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

it('no le hace leer el Libro dos veces a quien oye la página', function () {
    Book::factory()->create(['title' => 'Pedro Páramo']);

    $this->get(route('showcase'))->assertDontSee('Portada de Pedro Páramo');
});

/*
|--------------------------------------------------------------------------
| The Categories a visitor can navigate by
|--------------------------------------------------------------------------
|
| The navigation itself is BIB-19. What is settled here is which Categories it
| may name, because that is the part that errs outwards: a Category whose Books
| are all Hidden would say out loud, by its name alone, that there is something
| behind it (ADR-003).
*/

it('ofrece las Categorías que tienen algún Libro visible', function () {
    $category = Category::factory()->named('Narrativa')->create();
    $category->books()->attach(Book::factory()->count(2)->create());

    $offered = Showcase::categories();

    expect($offered->pluck('name')->all())->toBe(['Narrativa'])
        ->and((int) $offered->first()->books_count)->toBe(2);
});

it('no ofrece la Categoría cuyos Libros están todos ocultos', function () {
    $category = Category::factory()->named('Narrativa')->create();
    $category->books()->attach(Book::factory()->count(2)->create(['is_hidden' => true]));

    expect(Showcase::categories())->toHaveCount(0);
});

it('cuenta sólo los Libros visibles de cada Categoría', function () {
    $category = Category::factory()->named('Narrativa')->create();
    $category->books()->attach(Book::factory()->create());
    $category->books()->attach(Book::factory()->create(['is_hidden' => true]));

    expect((int) Showcase::categories()->first()->books_count)->toBe(1);
});

it('no ofrece la Categoría que se quedó sin Libros', function () {
    Category::factory()->named('Ensayo')->create();

    expect(Showcase::categories())->toHaveCount(0);
});

it('ofrece las Categorías por nombre', function () {
    foreach (['Poesía', 'Ensayo', 'Narrativa'] as $name) {
        Category::factory()->named($name)->create()->books()->attach(Book::factory()->create());
    }

    expect(Showcase::categories()->pluck('name')->all())->toBe(['Ensayo', 'Narrativa', 'Poesía']);
});
