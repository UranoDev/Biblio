<?php

use App\Models\Book;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
    $book->addQuote('Vine a Comala porque me dijeron que acá vivía mi padre.', 7);

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
        ->assertSee('Descripción escrita por una inteligencia artificial; puede equivocarse');
});

it('no marca la descripción que trajo la Fuente ni la que escribió el dueño', function (string $state) {
    $book = Book::factory()->{$state}()->create();

    $this->get(route('showcase.book', $book))
        ->assertDontSee('inteligencia artificial');
})->with(['descriptionFromSource', 'descriptionFromOwner']);

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

it('no gasta una consulta por Cita', function () {
    $book = Book::factory()->create();

    foreach (range(1, 40) as $page) {
        $book->addQuote("La cita número {$page}.", $page);
    }

    DB::enableQueryLog();
    $this->get(route('showcase.book', $book))->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(4);
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
    $book = Book::factory()->lent('Rosario')->create([
        'title' => 'El llano en llamas',
        'is_read' => true,
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
