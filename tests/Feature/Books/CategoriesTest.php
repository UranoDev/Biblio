<?php

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * The screen where the vocabulary is kept healthy: renaming, merging, and the
 * count that makes the Categories of a single Book visible (ADR-006).
 */

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function categories(): Testable
{
    return Livewire::test('pages::categories');
}

/** One Category with as many Books as asked for. */
function categoryWith(string $name, int $books = 0): Category
{
    $category = Category::factory()->named($name)->create();

    if ($books > 0) {
        $category->books()->attach(Book::factory()->count($books)->create());
    }

    return $category;
}

it('pide sesión', function () {
    auth()->logout();

    $this->get(route('categories'))->assertRedirect(route('login'));
});

it('abre las Categorías desde la barra lateral', function () {
    categoryWith('Narrativa', 2);

    $this->get(route('categories'))
        ->assertOk()
        ->assertSee('Categorías')
        ->assertSee('Narrativa');
});

/*
|--------------------------------------------------------------------------
| The list and its count
|--------------------------------------------------------------------------
*/

it('dice cuántos Libros tiene cada Categoría', function () {
    $several = categoryWith('Narrativa', 3);
    $one = categoryWith('Poesía', 1);

    categories()
        ->assertSeeHtml('data-test="count-'.$several->getKey().'"')
        ->assertSee('3 Libros')
        ->assertSeeHtml('data-test="count-'.$one->getKey().'"')
        ->assertSee('un Libro');
});

it('dice cuándo una Categoría se quedó sin Libros', function () {
    categoryWith('Ensayo');

    categories()->assertSee('Ningún Libro');
});

it('deja ver sólo las Categorías de un Libro o ninguno', function () {
    categoryWith('Narrativa', 3);
    categoryWith('Poesía', 1);
    categoryWith('Ensayo');

    categories()
        ->set('onlySmall', true)
        ->assertDontSee('Narrativa')
        ->assertSee('Poesía')
        ->assertSee('Ensayo');
});

it('busca una Categoría por parte de su nombre', function () {
    categoryWith('Narrativa', 1);
    categoryWith('Poesía', 1);

    categories()
        ->set('search', 'Narra')
        ->assertSee('Narrativa')
        ->assertDontSee('Poesía');
});

it('avisa cuando ninguna Categoría se llama así', function () {
    categoryWith('Narrativa', 1);

    categories()
        ->set('search', 'Astronomía')
        ->assertSeeHtml('data-test="no-matches"');
});

it('avisa cuando todavía no hay ninguna Categoría', function () {
    categories()->assertSeeHtml('data-test="empty"');
});

/*
|--------------------------------------------------------------------------
| Renaming
|--------------------------------------------------------------------------
*/

it('renombra una Categoría y con ella su slug', function () {
    $category = categoryWith('Ciencia ficcion', 1);

    categories()
        ->call('startRename', $category->getKey())
        ->set('name', 'Ciencia ficción')
        ->call('rename')
        ->assertHasNoErrors();

    expect($category->fresh()->name)->toBe('Ciencia ficción')
        ->and($category->fresh()->slug)->toBe('ciencia-ficcion');
});

it('el renombre alcanza a todos sus Libros', function () {
    $category = categoryWith('Novela', 3);

    categories()
        ->call('startRename', $category->getKey())
        ->set('name', 'Narrativa')
        ->call('rename');

    expect($category->fresh()->books)->toHaveCount(3);

    foreach ($category->fresh()->books as $book) {
        expect($book->categories->pluck('name')->all())->toBe(['Narrativa']);
    }
});

it('el enlace viejo sigue llegando a la Categoría renombrada', function () {
    $category = categoryWith('Novela', 1);

    categories()
        ->call('startRename', $category->getKey())
        ->set('name', 'Narrativa')
        ->call('rename');

    expect(Category::bySlug('novela')?->is($category))->toBeTrue();
});

it('propone fusionar cuando el nombre ya es de otra Categoría', function () {
    $category = categoryWith('Novela', 1);
    categoryWith('Narrativa', 1);

    categories()
        ->call('startRename', $category->getKey())
        ->set('name', 'Narrativa')
        ->call('rename')
        ->assertHasErrors('name');

    expect($category->fresh()->name)->toBe('Novela');
});

it('no renombra con el nombre en blanco', function () {
    $category = categoryWith('Novela', 1);

    categories()
        ->call('startRename', $category->getKey())
        ->set('name', '   ')
        ->call('rename')
        ->assertHasErrors('name');

    expect($category->fresh()->name)->toBe('Novela');
});

/*
|--------------------------------------------------------------------------
| Merging
|--------------------------------------------------------------------------
*/

it('fusiona dos Categorías en una', function () {
    $stays = categoryWith('Ciencia ficción', 1);
    $absorbed = categoryWith('Sci-fi', 2);

    categories()
        ->call('startMerge', $absorbed->getKey())
        ->set('into', (string) $stays->getKey())
        ->call('merge')
        ->assertHasNoErrors();

    expect($stays->fresh()->books)->toHaveCount(3)
        ->and(Category::find($absorbed->getKey()))->toBeNull();
});

it('un Libro que estaba en las dos no queda repetido', function () {
    $stays = categoryWith('Ciencia ficción');
    $absorbed = categoryWith('Sci-fi');
    $shared = Book::factory()->create();

    $stays->books()->attach($shared);
    $absorbed->books()->attach($shared);

    categories()
        ->call('startMerge', $absorbed->getKey())
        ->set('into', (string) $stays->getKey())
        ->call('merge');

    expect($stays->fresh()->books)->toHaveCount(1);
});

it('el enlace de la Categoría absorbida lleva a la que se quedó', function () {
    $stays = categoryWith('Ciencia ficción', 1);
    $absorbed = categoryWith('Sci-fi', 1);

    categories()
        ->call('startMerge', $absorbed->getKey())
        ->set('into', (string) $stays->getKey())
        ->call('merge');

    expect(Category::bySlug('sci-fi')?->is($stays))->toBeTrue();
});

it('no fusiona sin elegir la Categoría que se queda', function () {
    $absorbed = categoryWith('Sci-fi', 1);
    categoryWith('Ciencia ficción', 1);

    categories()
        ->call('startMerge', $absorbed->getKey())
        ->call('merge')
        ->assertHasErrors('into');

    expect(Category::find($absorbed->getKey()))->not->toBeNull();
});

it('no ofrece fusionar una Categoría consigo misma', function () {
    $absorbed = categoryWith('Sci-fi', 1);
    categoryWith('Ciencia ficción', 1);

    $others = categories()
        ->call('startMerge', $absorbed->getKey())
        ->instance()
        ->others;

    expect($others->pluck('name')->all())->toBe(['Ciencia ficción']);
});
