<?php

use App\Models\Book;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * The page of one Book in the Showcase: the second public surface, and so the
 * second place where a Hidden Book has to be unreachable (ADR-003). Not hidden
 * from the layout — not served at all.
 */

beforeEach(function () {
    Storage::fake();
});

it('muestra la ficha, la descripción, las categorías y las citas', function () {
    $book = Book::factory()->descriptionFromSource()->create([
        'title' => 'Pedro Páramo',
        'authors' => ['Juan Rulfo'],
        'publisher' => 'Editorial RM',
        'year' => 2005,
        'description' => 'Un hombre llega a Comala buscando a su padre.',
    ]);

    $book->categories()->attach(Category::factory()->create(['name' => 'Novela mexicana']));
    $book->addQuote('Vine a Comala porque me dijeron que acá vivía mi padre.', '7');

    $this->get(route('showcase.book', $book))
        ->assertOk()
        ->assertSee('Pedro Páramo')
        ->assertSee('Juan Rulfo')
        ->assertSee('Editorial RM')
        ->assertSee('2005')
        ->assertSee($book->isbn)
        ->assertSee('Un hombre llega a Comala buscando a su padre.')
        ->assertSee('Novela mexicana')
        ->assertSee('Vine a Comala porque me dijeron que acá vivía mi padre.');
});

it('marca la descripción que escribió una IA', function () {
    $book = Book::factory()->descriptionFromAi()->create();

    $this->get(route('showcase.book', $book))
        ->assertSee('Descripción generada por IA');
});

it('no marca la descripción que trajo la Fuente ni la que escribió el dueño', function (string $state) {
    $book = Book::factory()->{$state}()->create();

    $this->get(route('showcase.book', $book))
        ->assertDontSee('generada por IA');
})->with(['descriptionFromSource', 'descriptionFromOwner']);

/**
 * La tercera marca dice otra cosa que las otras dos: el contenido es el del
 * catálogo y el español lo escribió una máquina (BIB-45). Quien lee tiene que
 * poder distinguir eso de un resumen inventado por una IA.
 */
it('marca como traducida la descripción del catálogo que pasó por una IA', function () {
    $book = Book::factory()->descriptionTranslated()->create();

    $this->get(route('showcase.book', $book))
        ->assertSee('Descripción del catálogo, traducida al español por una IA')
        ->assertDontSee('Descripción generada por IA');
});

it('la descripción escrita por IA no se confunde con una traducida', function () {
    $book = Book::factory()->descriptionFromAi()->create();

    $this->get(route('showcase.book', $book))
        ->assertSee('Descripción generada por IA')
        ->assertDontSee('traducida al español');
});

it('sin descripción no deja hueco ni texto de relleno', function () {
    $book = Book::factory()->withoutDescription()->create(['title' => 'Sin contraportada']);

    $this->get(route('showcase.book', $book))
        ->assertOk()
        ->assertSee('Sin contraportada')
        ->assertDontSee('data-test="description"', escape: false);
});

it('dice que un Libro Por comprar todavía no está, con palabras de visitante', function () {
    $book = Book::factory()->wanted()->create();

    $this->get(route('showcase.book', $book))
        ->assertSee('Todavía no lo tengo')
        ->assertDontSee('Por comprar');
});

it('enseña todas las Citas de un Libro, sin tope', function () {
    $book = Book::factory()->create();

    foreach (range(1, 40) as $page) {
        $book->addQuote("La cita número {$page} de este Libro.", $page);
    }

    $response = $this->get(route('showcase.book', $book));

    $response->assertSee('40 citas');

    foreach (range(1, 40) as $page) {
        $response->assertSee("La cita número {$page} de este Libro.");
    }
});

it('no repite la Portada ni el título en cada tarjeta de Cita (BIB-41)', function () {
    $book = Book::factory()->create([
        'title' => 'Pedro Páramo',
        'authors' => ['Juan Rulfo'],
        'cover_path' => 'covers/9/abc.jpg',
        'cover_thumbnail_path' => 'covers/9/abc-thumbnail.jpg',
    ]);

    foreach (range(1, 3) as $page) {
        $book->addQuote("La cita número {$page} de este Libro.", $page);
    }

    $html = (string) $this->get(route('showcase.book', $book))->assertOk()->getContent();

    $quotes = Str::betweenFirst($html, 'data-test="quotes"', '</section>');

    // La Portada, el título y los autores están arriba, en la ficha; ninguna
    // tarjeta los repite.
    expect($quotes)->not->toContain($book->coverUrl())
        ->and($quotes)->not->toContain('Pedro Páramo')
        ->and($quotes)->not->toContain('Juan Rulfo')
        // Y lo que sí cambia de una Cita a otra sigue ahí, las tres veces.
        ->and(substr_count($quotes, 'data-test="quote-text"'))->toBe(3)
        ->and(substr_count($quotes, 'data-test="quote-page"'))->toBe(3);
});

it('no gasta una consulta por Cita', function () {
    $one = Book::factory()->create();
    $one->addQuote('La única cita de este Libro.', '1');

    DB::enableQueryLog();
    $this->get(route('showcase.book', $one))->assertOk();
    $withOne = count(DB::getQueryLog());

    $forty = Book::factory()->create();

    foreach (range(1, 40) as $page) {
        $forty->addQuote("La cita número {$page}.", $page);
    }

    DB::flushQueryLog();
    $this->get(route('showcase.book', $forty))->assertOk();
    $withForty = count(DB::getQueryLog());

    DB::disableQueryLog();

    // Two Books and not a ceiling: what this page may not do is grow with the
    // Quotes. What the page costs in total is another question, and a number
    // written down here goes stale the next time the layout gains anything.
    expect($withForty)->toBe($withOne);
});

it('no existe la página de un Libro oculto', function () {
    $book = Book::factory()->hidden()->create(['title' => 'El libro que no sale']);

    $this->get(route('showcase.book', $book))->assertNotFound();
});

it('tampoco deja ver las Citas de un Libro oculto', function () {
    $book = Book::factory()->hidden()->create();
    $book->addQuote('Esta cita hereda el ocultamiento de su Libro.');

    $this->get(route('showcase.book', $book))
        ->assertNotFound()
        ->assertDontSee('Esta cita hereda el ocultamiento de su Libro.');
});

it('no sirve la fecha de lectura, el apunte ni el préstamo en el HTML', function () {
    $book = Book::factory()->lent('Rosario')->read()->create([
        'title' => 'El llano en llamas',
        'read_on' => '2019-03-14',
        'note' => 'Lo que me dejó este libro y nadie más tiene por qué leer.',
    ]);

    $this->get(route('showcase.book', $book))
        ->assertSee('Leído')
        ->assertDontSee('Rosario')
        ->assertDontSee('nadie más tiene por qué leer')
        ->assertDontSee('2019-03-14')
        ->assertDontSee('14/03/2019');
});

it('no acepta que le escriban, ni con una petición armada a mano', function (string $method) {
    $book = Book::factory()->create();

    $this->call($method, route('showcase.book', $book))->assertMethodNotAllowed();
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

it('pone las Citas de páginas romanas antes que las de páginas arábigas', function () {
    $book = Book::factory()->create();

    $book->addQuote('La de la página uno.', '1');
    $book->addQuote('La del prólogo.', 'xiv');
    $book->addQuote('La de la portadilla.', 'ix');

    $this->get(route('showcase.book', $book))
        ->assertOk()
        ->assertSeeInOrder([
            'La de la portadilla.',
            'La del prólogo.',
            'La de la página uno.',
        ]);
});
