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

it('enseña los que están Por comprar en «En mi lista», con su etiqueta', function () {
    Book::factory()->wanted()->create(['title' => 'Los detectives salvajes']);

    $this->get(route('showcase'))
        ->assertSee('En mi lista')
        ->assertSee('Los detectives salvajes')
        ->assertSee('Por comprar');
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
    $book = Book::factory()->lent('Rosario')->read()->create([
        'title' => 'El llano en llamas',
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
| Las primeras filas de «Los que tengo»
|--------------------------------------------------------------------------
|
| La portada abre en tres filas y guarda el resto detrás de un control (BIB-60).
| Cuántos Libros son esas tres filas lo mide el navegador contra las tarjetas ya
| dibujadas, así que lo que se puede comprobar desde acá es lo otro: que el HTML
| trae el acervo completo, que sin scripts no queda nada escondido detrás de un
| botón, y que el recorte es de «Los que tengo» y de nadie más.
*/

it('manda el acervo completo en el HTML, aunque la portada abra en las primeras filas', function () {
    $titles = ['Pedro Páramo', 'La montaña mágica', 'Rayuela', 'El llano en llamas'];

    foreach ($titles as $title) {
        Book::factory()->owned()->create(['title' => $title]);
    }

    $response = $this->get(route('showcase'));

    foreach ($titles as $title) {
        $response->assertSee($title);
    }
});

it('deja el control apagado hasta que el navegador mide las filas', function () {
    Book::factory()->owned()->count(4)->create();

    $html = $this->get(route('showcase'))->assertSee('Cargar más')->getContent();

    // Sin scripts la retícula está entera y el control no está: lo enciende el
    // mismo guión que esconde las tarjetas, así que nunca queda un botón que no
    // hace nada delante de Libros que no se ven.
    expect($html)->toMatch('/<button(?=[^>]*data-test="first-rows-more")[^>]*\shidden\s/');
});

it('recorta «Los que tengo» y no «En mi lista»', function () {
    Book::factory()->owned()->create(['title' => 'El que tengo']);
    Book::factory()->wanted()->create(['title' => 'El que me falta']);

    $response = $this->get(route('showcase'))->assertSee('El que me falta');

    expect(substr_count($response->getContent(), 'data-test="first-rows"'))->toBe(1);

    $response->assertSeeInOrder(['En mi lista', 'data-test="first-rows"'], false);
});

it('trae el acervo visible cuando lo pide revuelto, y nada más', function () {
    Book::factory()->owned()->create(['title' => 'El que sale']);
    Book::factory()->owned()->hidden()->create(['title' => 'El oculto']);
    Book::factory()->wanted()->create(['title' => 'El que me falta']);

    expect(Showcase::onShelfAtRandom()->pluck('title')->all())->toBe(['El que sale']);
});

it('revuelve el acervo en cada carga, para que las tres filas no sean siempre las mismas', function () {
    Book::factory()->owned()->count(20)->create();

    $first = Showcase::onShelfAtRandom()->modelKeys();
    $second = Showcase::onShelfAtRandom()->modelKeys();

    expect($first)->not->toBe($second)
        ->and(array_diff($first, $second))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| The section «En mi lista» (BIB-63)
|--------------------------------------------------------------------------
|
| The front page merges what I am reading and what I still need to buy into
| «En mi lista», each card labelled «Leyendo» or «Por comprar». The Shelf
| («Los que tengo») goes underneath with the full collection.
*/

it('enseña en «En mi lista» el Libro que estoy leyendo, con su etiqueta', function () {
    Book::factory()->owned()->beingRead()->create(['title' => 'La casa de los espíritus']);

    $this->get(route('showcase'))
        ->assertSee('En mi lista')
        ->assertSee('La casa de los espíritus')
        ->assertSee('Leyendo');
});

it('no arma «En mi lista» cuando no hay ninguno empezado ni por comprar', function () {
    Book::factory()->owned()->read()->create();
    Book::factory()->owned()->create();

    $this->get(route('showcase'))->assertDontSee('En mi lista');
});

it('pone las secciones en su orden: en mi lista, los que tengo', function () {
    Book::factory()->owned()->beingRead()->create();
    Book::factory()->wanted()->create();

    $this->get(route('showcase'))->assertSeeInOrder([
        'En mi lista',
        'Los que tengo',
    ]);
});

it('deja el Libro en curso dentro del acervo, y lo sigue contando', function () {
    $beingRead = Book::factory()->owned()->beingRead()->create(['title' => 'El que tengo empezado']);
    Book::factory()->owned()->create(['title' => 'El otro que tengo']);

    expect(Showcase::beingRead()->modelKeys())->toBe([$beingRead->getKey()])
        ->and(Showcase::onShelfAtRandom()->modelKeys())->toContain($beingRead->getKey());

    $this->get(route('showcase'))->assertSee('2 libros');
});

it('no saca a la Vitrina el Libro oculto que estoy leyendo', function () {
    Book::factory()->owned()->beingRead()->hidden()->create(['title' => 'El empezado a escondidas']);
    Book::factory()->owned()->beingRead()->create(['title' => 'El empezado a la vista']);

    $this->get(route('showcase'))
        ->assertSee('El empezado a la vista')
        ->assertDontSee('El empezado a escondidas');

    expect(Showcase::beingRead()->pluck('title')->all())->toBe(['El empezado a la vista']);
});

it('no pone en lo que estoy leyendo un Libro que todavía no tengo', function () {
    Book::factory()->wanted()->create(['title' => 'El que me falta']);

    $this->get(route('showcase'))
        ->assertSee('El que me falta')
        ->assertDontSee('Lo que estoy leyendo');

    expect(Showcase::beingRead())->toBeEmpty();
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
    foreach (['Lírica', 'Ensayo', 'Narrativa'] as $name) {
        Category::factory()->named($name)->create()->books()->attach(Book::factory()->create());
    }

    expect(Showcase::categories()->pluck('name')->all())->toBe(['Ensayo', 'Lírica', 'Narrativa']);
});
