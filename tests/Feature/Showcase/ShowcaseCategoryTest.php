<?php

use App\Models\Book;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * Walking the Showcase by Category: the address of one Category, and the
 * navigation that leads to it.
 *
 * As on every public surface, half of this file is about what does NOT come
 * out. Here there are two of those: a Hidden Book of the Category, and the
 * Category whose Books are all Hidden — which must not even be reachable by
 * typing its address, because answering it would confirm that it exists
 * (ADR-003).
 */

beforeEach(function () {
    Storage::fake();
});

/**
 * A Category with its Books attached, the way every test here needs it.
 *
 * @param  iterable<int, Book>  $books
 */
function categoryOf(string $name, iterable $books): Category
{
    $category = Category::factory()->named($name)->create();

    $category->books()->attach(collect($books)->map->getKey()->all());

    return $category;
}

it('enseña los Libros visibles de la Categoría', function () {
    $category = categoryOf('Poesía', [
        Book::factory()->create(['title' => 'Muerte sin fin']),
        Book::factory()->create(['title' => 'Piedra de sol']),
    ]);

    $this->get(route('showcase.category', $category))
        ->assertOk()
        ->assertSee('Poesía')
        ->assertSee('Muerte sin fin')
        ->assertSee('Piedra de sol');
});

it('no enseña los Libros de otra Categoría', function () {
    $poetry = categoryOf('Poesía', [Book::factory()->create(['title' => 'Muerte sin fin'])]);
    categoryOf('Ensayo', [Book::factory()->create(['title' => 'El laberinto de la soledad'])]);

    $this->get(route('showcase.category', $poetry))
        ->assertSee('Muerte sin fin')
        ->assertDontSee('El laberinto de la soledad');
});

it('saca un Libro con varias Categorías en todas ellas', function () {
    $book = Book::factory()->create(['title' => 'Nostalgia de la muerte']);

    $poetry = categoryOf('Poesía', [$book]);
    $mexican = categoryOf('Literatura mexicana', [$book]);

    $this->get(route('showcase.category', $poetry))->assertSee('Nostalgia de la muerte');
    $this->get(route('showcase.category', $mexican))->assertSee('Nostalgia de la muerte');
});

it('separa los que tengo de los que me faltan dentro de la Categoría', function () {
    $category = categoryOf('Poesía', [
        Book::factory()->owned()->create(['title' => 'Muerte sin fin']),
        Book::factory()->wanted()->create(['title' => 'Piedra de sol']),
    ]);

    $this->get(route('showcase.category', $category))
        ->assertSee('Los que tengo')
        ->assertSee('Los que me faltan')
        ->assertDontSee('Por comprar');
});

it('no arma la sección de los que me faltan cuando no falta ninguno', function () {
    $category = categoryOf('Poesía', [Book::factory()->owned()->create()]);

    $this->get(route('showcase.category', $category))->assertDontSee('Los que me faltan');
});

it('no saca un Libro oculto de la Categoría, ni nada de lo mío que traiga', function () {
    $hidden = Book::factory()->hidden()->lent('Rosario')->create([
        'title' => 'El libro que no sale',
        'note' => 'Lo que me dejó y nadie más tiene por qué leer.',
    ]);

    $category = categoryOf('Poesía', [
        Book::factory()->create(['title' => 'El libro que sí sale']),
        $hidden,
    ]);

    $this->get(route('showcase.category', $category))
        ->assertSee('El libro que sí sale')
        ->assertDontSee('El libro que no sale')
        ->assertDontSee('Rosario')
        ->assertDontSee('nadie más tiene por qué leer');
});

it('cuenta sólo los Libros visibles de la Categoría', function () {
    $category = categoryOf('Poesía', [
        Book::factory()->create(),
        Book::factory()->hidden()->create(),
    ]);

    $this->get(route('showcase.category', $category))
        ->assertSee('1 libro')
        ->assertDontSee('2 libros');
});

it('no deja alcanzar la Categoría cuyos Libros están todos ocultos', function () {
    $category = categoryOf('Poesía', Book::factory()->count(2)->hidden()->create());

    $this->get(route('showcase.category', $category))->assertNotFound();
});

it('no deja alcanzar la Categoría que se quedó sin Libros', function () {
    $category = Category::factory()->named('Ensayo')->create();

    $this->get(route('showcase.category', $category))->assertNotFound();
});

it('contesta 404 a una Categoría que no existe, no una lista vacía', function () {
    $this->get(route('showcase.category', 'no-existe'))->assertNotFound();
});

it('usa el slug de la Categoría en una dirección que se lee', function () {
    $category = categoryOf('Ciencia ficción', [Book::factory()->create()]);

    expect(route('showcase.category', $category, false))->toBe('/category/ciencia-ficcion');
});

it('no rompe el enlace al renombrar: la dirección vieja lleva a la nueva', function () {
    $category = categoryOf('Ciencia ficción', [Book::factory()->create(['title' => 'Solaris'])]);

    $category->rename('Ficción especulativa');

    $this->get('/category/ciencia-ficcion')
        ->assertMovedPermanently()
        ->assertRedirect(route('showcase.category', $category->fresh()));

    $this->get(route('showcase.category', $category->fresh()))->assertOk()->assertSee('Solaris');
});

it('lleva la dirección de la Categoría fusionada a la que se quedó con los Libros', function () {
    $kept = categoryOf('Narrativa', [Book::factory()->create(['title' => 'Solaris'])]);
    $absorbed = categoryOf('Ciencia ficción', [Book::factory()->create(['title' => 'Ubik'])]);

    $kept->absorb($absorbed);

    $this->get(route('showcase.category', $absorbed))
        ->assertMovedPermanently()
        ->assertRedirect(route('showcase.category', $kept));

    $this->get(route('showcase.category', $kept))
        ->assertSee('Solaris')
        ->assertSee('Ubik');
});

it('no redirige a un 404: la dirección vieja de una Categoría inalcanzable no contesta', function () {
    $category = categoryOf('Ciencia ficción', Book::factory()->count(1)->hidden()->create());

    $category->rename('Ficción especulativa');

    $this->get('/category/ciencia-ficcion')->assertNotFound();
});

it('no acepta que le escriban, ni con una petición armada a mano', function (string $method) {
    $category = categoryOf('Poesía', [Book::factory()->create()]);

    $this->call($method, route('showcase.category', $category))->assertMethodNotAllowed();
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

it('dibuja doscientos Libros de una Categoría con las mismas consultas que uno', function () {
    $category = categoryOf('Poesía', [Book::factory()->create()]);

    DB::enableQueryLog();
    $this->get(route('showcase.category', $category))->assertOk();
    $one = count(DB::getQueryLog());

    $category->books()->attach(Book::factory()->count(199)->create()->map->getKey()->all());

    DB::flushQueryLog();
    $this->get(route('showcase.category', $category))->assertOk();
    $twoHundred = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect($twoHundred)->toBe($one);
});

/*
|--------------------------------------------------------------------------
| The navigation
|--------------------------------------------------------------------------
*/

it('lista en la navegación las Categorías con Libros visibles, y desde ahí se llega', function () {
    $category = categoryOf('Poesía', [Book::factory()->create()]);

    $this->get(route('showcase'))
        ->assertSee('Poesía')
        ->assertSee('href="'.route('showcase.category', $category).'"', false);
});

it('no nombra en la navegación la Categoría cuyos Libros están todos ocultos', function () {
    categoryOf('Poesía', [Book::factory()->create()]);
    categoryOf('Lo que no se enseña', Book::factory()->count(2)->hidden()->create());

    $this->get(route('showcase'))
        ->assertSee('Poesía')
        ->assertDontSee('Lo que no se enseña');
});

it('tampoco nombra la Categoría que se quedó sin Libros', function () {
    Category::factory()->named('Ensayo')->create();

    $this->get(route('showcase'))->assertDontSee('Ensayo');
});

it('lleva la navegación en la portada, en la Categoría y en la ficha del Libro', function () {
    $book = Book::factory()->create(['title' => 'Muerte sin fin']);
    $category = categoryOf('Poesía', [$book]);

    $urls = [
        route('showcase'),
        route('showcase.category', $category),
        route('showcase.book', $book),
    ];

    foreach ($urls as $url) {
        $this->get($url)->assertSee('data-test="category-nav"', false);
    }
});

it('dice en la navegación cuál es la Categoría en la que se está', function () {
    $category = categoryOf('Poesía', [Book::factory()->create()]);

    $html = $this->get(route('showcase.category', $category))->getContent();

    expect($html)->toContain('aria-current="page"')
        ->and($html)->toContain('data-test="category-nav-poesia"');
});

it('no ofrece la vuelta a toda la biblioteca en la portada, que es donde lleva', function () {
    categoryOf('Poesía', [Book::factory()->create()]);

    $this->get(route('showcase'))->assertDontSee('category-nav-all', false);
});

it('ofrece la vuelta a toda la biblioteca desde la Categoría y desde el Libro', function () {
    $book = Book::factory()->create();
    $category = categoryOf('Poesía', [$book]);

    $this->get(route('showcase.category', $category))
        ->assertSee('Toda la biblioteca')
        ->assertSee('category-nav-all', false);

    $this->get(route('showcase.book', $book))
        ->assertSee('Toda la biblioteca')
        ->assertSee('category-nav-all', false);
});

it('deja la vuelta a toda la biblioteca aunque no haya ninguna Categoría', function () {
    $book = Book::factory()->create();

    $this->get(route('showcase.book', $book))->assertSee('Toda la biblioteca');
});

it('no arma la navegación en la portada cuando no hay ninguna Categoría', function () {
    Book::factory()->create();

    $this->get(route('showcase'))->assertDontSee('data-test="category-nav"', false);
});

it('navega sin abrir nada: la navegación son ligas que ya vienen en el HTML', function () {
    $category = categoryOf('Poesía', [Book::factory()->create()]);

    $html = $this->get(route('showcase.category', $category))->getContent();

    expect($html)->toContain('<nav aria-label="Categorías"')
        ->and($html)->toContain('href="'.route('showcase.category', $category).'"')
        ->and($html)->not->toContain('<script')
        ->and($html)->not->toContain('x-data')
        ->and($html)->not->toContain('wire:');
});

it('vuelve ligas las Categorías de la ficha pública de un Libro', function () {
    $book = Book::factory()->create(['title' => 'Muerte sin fin']);
    $category = categoryOf('Poesía', [$book]);

    $this->get(route('showcase.book', $book))
        ->assertSee('href="'.route('showcase.category', $category).'"', false);
});
