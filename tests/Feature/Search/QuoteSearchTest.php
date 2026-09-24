<?php

use App\Models\Book;
use App\Models\Quote;
use App\Models\User;
use App\Search\FoldedText;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/** The screen, already looking for something. */
function searchingQuotes(string $term = ''): Testable
{
    return Livewire::test('pages::quote-search')->set('search', $term);
}

/**
 * The screen as it really reaches the browser, without the markers Livewire
 * leaves around each block: they are comments and draw nothing, but they sit
 * between one piece of the fragment and the next.
 */
function renderedQuotes(string $term): string
{
    return str_replace(
        ['<!--[if BLOCK]><![endif]-->', '<!--[if ENDBLOCK]><![endif]-->'],
        '',
        searchingQuotes($term)->html(),
    );
}

/** A Quote of a Book, with the text it is going to be looked for by. */
function quoteSaying(string $text, ?Book $book = null): Quote
{
    return Quote::factory()->for($book ?? Book::factory())->create(['text' => $text]);
}

// Lo que encuentra.

it('encuentra la Quote que tiene la palabra', function () {
    $found = quoteSaying('Vine a Comala porque me dijeron que acá vivía mi padre.');
    quoteSaying('Nada puede durar tanto tiempo.');

    searchingQuotes('comala')
        ->assertSee('Comala')
        ->assertDontSee('Nada puede durar');

    expect(Quote::query()->matching('comala')->pluck('id')->all())->toBe([$found->getKey()]);
});

it('con dos palabras devuelve las Quotes que tienen las dos', function () {
    $both = quoteSaying('Vine a Comala a buscar a mi padre.');
    quoteSaying('Comala está sobre las brasas de la tierra.');
    quoteSaying('Mi padre me lo dijo una vez.');

    expect(Quote::query()->matching('comala padre')->pluck('id')->all())->toBe([$both->getKey()]);
});

it('no cambia el resultado por los acentos, en los dos sentidos', function () {
    $accented = quoteSaying('La citá que recuerdo.');
    $plain = quoteSaying('La cita que recuerdo.');

    expect(Quote::query()->matching('cita')->pluck('id')->all())
        ->toEqualCanonicalizing([$accented->getKey(), $plain->getKey()])
        ->and(Quote::query()->matching('citá')->pluck('id')->all())
        ->toEqualCanonicalizing([$accented->getKey(), $plain->getKey()]);
});

it('distingue la eñe de la ene', function () {
    quoteSaying('Aquel año en Comala.');

    expect(Quote::query()->matching('ano')->count())->toBe(0)
        ->and(Quote::query()->matching('año')->count())->toBe(1);
});

it('encuentra una palabra que está a la mitad de otra', function () {
    quoteSaying('Los murmullos de Comala.');

    expect(Quote::query()->matching('murmullo')->count())->toBe(1);
});

it('trata el porcentaje como un signo y no como «todo»', function () {
    quoteSaying('Vine a Comala.');

    expect(Quote::query()->matching('%')->count())->toBe(0);
});

// La Quote corregida.

it('encuentra la Quote por el texto con el que quedó corregida', function () {
    $quote = quoteSaying('Vine a Comala.');

    $quote->rewrite('Vine a Sayula.');

    expect(Quote::query()->matching('comala')->count())->toBe(0)
        ->and(Quote::query()->matching('sayula')->count())->toBe(1);
});

it('encuentra la Quote recién capturada desde la ficha del Book', function () {
    $book = Book::factory()->create();

    Livewire::test('pages::book', ['book' => $book])
        ->set('quoteText', 'Vine a Comala porque me dijeron que acá vivía mi padre.')
        ->call('saveQuote');

    expect(Quote::query()->matching('comala')->count())->toBe(1);
});

// La pantalla.

it('no busca nada hasta que se escribe algo', function () {
    quoteSaying('Vine a Comala.');

    searchingQuotes()
        ->assertSee('Escribe una palabra')
        ->assertDontSee('Vine a Comala');
});

it('lo dice cuando ninguna Quote tiene esas palabras', function () {
    quoteSaying('Vine a Comala.');

    searchingQuotes('sayula')->assertSee('Ninguna Cita tiene esas palabras.');
});

it('resalta la palabra buscada dentro de su contexto', function () {
    quoteSaying('Vine a Comala porque me dijeron que acá vivía mi padre.');

    searchingQuotes('comala')
        ->assertSee('Vine a ')
        ->assertSeeHtml('>Comala</mark>')
        ->assertSee('porque me dijeron que acá vivía mi padre.');
});

it('no mete espacios de más al partir la Cita en pedazos', function () {
    quoteSaying('Vine a Comalapa temprano.');

    // La palabra queda entera aunque la marca la corte por la mitad: entre un
    // pedazo y el siguiente no va nada.
    expect(renderedQuotes('comala'))
        ->toContain('Vine a <mark')
        ->toContain('>Comala</mark>pa temprano.');
});

it('lleva de un resultado al Book de esa Quote', function () {
    $book = Book::factory()->create(['title' => 'Pedro Páramo']);
    quoteSaying('Vine a Comala.', $book);

    searchingQuotes('comala')
        ->assertSee('Pedro Páramo')
        ->assertSeeHtml(route('book', $book));
});

it('muestra la página de la Quote cuando la trae', function () {
    Quote::factory()->for(Book::factory())->create(['text' => 'Vine a Comala.', 'page' => '7']);

    searchingQuotes('comala')->assertSee('p. 7');
});

it('marca las Quotes de un Book oculto, porque esta pantalla las muestra igual', function () {
    $book = Book::factory()->create(['is_hidden' => true]);
    quoteSaying('Vine a Comala.', $book);

    searchingQuotes('comala')
        ->assertSee('Comala')
        ->assertSee('Oculto');
});

it('cuenta las Quotes encontradas', function () {
    $book = Book::factory()->create();
    quoteSaying('Comala, una.', $book);
    quoteSaying('Comala, dos.', $book);

    searchingQuotes('comala')->assertSee('2 Citas.');
});

it('vuelve a la primera página al cambiar la búsqueda', function () {
    $book = Book::factory()->create();

    foreach (range(1, 25) as $number) {
        Quote::factory()->for($book)->create(['text' => 'Comala número '.$number.'.', 'page' => $number]);
    }

    searchingQuotes('comala')
        ->call('setPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('search', 'comala numero')
        ->assertSet('paginators.page', 1);
});

it('pasa a la página siguiente y regresa', function () {
    $book = Book::factory()->create();

    foreach (range(1, 25) as $number) {
        Quote::factory()->for($book)->create(['text' => 'Comala número '.$number.'.', 'page' => $number]);
    }

    searchingQuotes('comala')
        ->assertSee('Página 1 de 2')
        ->call('nextPage')
        ->assertSee('Página 2 de 2')
        ->assertSee('número 25.')
        ->call('previousPage')
        ->assertSee('Página 1 de 2');
});

it('limpia la búsqueda', function () {
    quoteSaying('Vine a Comala.');

    searchingQuotes('comala')
        ->call('clearSearch')
        ->assertSet('search', '')
        ->assertSee('Escribe una palabra');
});

it('pide la sesión', function () {
    auth()->logout();

    $this->get(route('quote-search'))->assertRedirect(route('login'));
});

// El volumen.

it('responde con miles de Quotes', function () {
    $filler = Book::factory()->create(['title' => 'El relleno']);
    $needle = Book::factory()->create(['title' => 'Pedro Páramo']);

    // Las Quotes de relleno se insertan en bloque, sin pasar por el modelo:
    // cinco mil altas de una en una medirían el alta y no la búsqueda.
    foreach (array_chunk(range(1, 5000), 500) as $chunk) {
        DB::table('quotes')->insert(array_map(function (int $number) use ($filler): array {
            $text = 'Relleno número '.$number.' sin nada que buscar.';

            return [
                'book_id' => $filler->getKey(),
                'text' => $text,
                'text_folded' => FoldedText::of($text),
                'page' => $number,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $chunk));
    }

    $planted = quoteSaying('Vine a Comala porque me dijeron que acá vivía mi padre.', $needle);

    expect(Quote::query()->count())->toBe(5001);

    $started = microtime(true);

    $results = Quote::query()->matching('comala padre')->with('book')->paginate(20);

    $elapsed = microtime(true) - $started;

    expect($results->pluck('id')->all())->toBe([$planted->getKey()])
        ->and($elapsed)->toBeLessThan(2.0);
});

it('no trae todas las Quotes a la pantalla de una vez', function () {
    $book = Book::factory()->create();

    foreach (range(1, 45) as $number) {
        Quote::factory()->for($book)->create(['text' => 'Comala número '.$number.'.', 'page' => $number]);
    }

    $screen = searchingQuotes('comala');

    expect($screen->instance()->results->count())->toBe(20)
        ->and($screen->instance()->results->total())->toBe(45);
});
