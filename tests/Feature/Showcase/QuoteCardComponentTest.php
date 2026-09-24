<?php

use App\Models\Book;
use App\Models\Quote;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestView;

beforeEach(function () {
    Storage::fake();
});

/** The card standing on its own: the page of the Quote, and the shared image. */
function quoteCard(Quote $quote): TestView
{
    return test()->blade('<x-quote-card :quote="$quote" />', ['quote' => $quote]);
}

/** The card at home, inside the sheet of its own Book. */
function quoteCardAtHome(Quote $quote): TestView
{
    return test()->blade('<x-quote-card :quote="$quote" :standalone="false" />', ['quote' => $quote]);
}

/** A Book with a photographed Cover, which is the one the card crops. */
function bookWithPhotograph(array $attributes = []): Book
{
    return Book::factory()->create($attributes + [
        'cover_path' => 'covers/9/abc.jpg',
        'cover_thumbnail_path' => 'covers/9/abc-thumbnail.jpg',
    ]);
}

it('dibuja la Cita, el título y los autores', function () {
    $book = Book::factory()->create([
        'title' => 'Pedro Páramo',
        'authors' => ['Juan Rulfo'],
    ]);

    $quote = $book->addQuote('Vine a Comala porque me dijeron que acá vivía mi padre.', '7');

    quoteCard($quote)
        ->assertSee('Vine a Comala porque me dijeron que acá vivía mi padre.')
        ->assertSee('Pedro Páramo')
        ->assertSee('Juan Rulfo')
        ->assertSee('Página 7');
});

it('pone comillas angulares, que son las de primer nivel en español', function () {
    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    $html = quoteCard($quote)->__toString();

    expect($html)->toContain('«Vine a Comala.»')
        ->and($html)->not->toContain('“Vine a Comala.”')
        ->and($html)->not->toContain('"Vine a Comala."');
});

it('no repite las comillas de una Cita que ya se tecleó con ellas', function () {
    $quote = Book::factory()->create()->addQuote('«Vine a Comala.»');

    expect(quoteCard($quote)->__toString())
        ->toContain('«Vine a Comala.»')
        ->not->toContain('««');
});

it('sin página, la tarjeta no lleva encabezado', function () {
    $quote = Quote::factory()->withoutPage()->create();

    quoteCard($quote)->assertDontSee('Página');
});

it('sirve para la Cita de seis palabras y para la de cuarenta, con el tamaño de cada una', function () {
    $book = Book::factory()->create();

    $short = $book->addQuote('Todo lo sólido se desvanece en el aire.');
    $long = $book->addQuote(str_repeat('La biblioteca de Babel es ilimitada y periódica. ', 12));

    quoteCard($short)->assertSee('type-quote-large', false);
    quoteCard($long)->assertSee('type-quote-small', false);

    // The long one steps down; it is never cut.
    quoteCard($long)->assertSee('La biblioteca de Babel es ilimitada y periódica. La biblioteca');
});

/*
 * Las dos variantes (BIB-41). Es la misma tarjeta: lo que cambia es si la
 * tarjeta está dentro de la ficha de su propio Book o si viaja sola.
 */

it('dentro de la ficha de su Libro se queda con la Cita y su página, y nada más', function () {
    $book = bookWithPhotograph([
        'title' => 'Pedro Páramo',
        'authors' => ['Juan Rulfo'],
    ]);

    $quote = $book->addQuote('Vine a Comala porque me dijeron que acá vivía mi padre.', '7');

    quoteCardAtHome($quote)
        ->assertSee('Vine a Comala porque me dijeron que acá vivía mi padre.')
        ->assertSee('Página 7')
        // El título, los autores y la Portada están dos centímetros más arriba,
        // en la ficha; repetirlos en cada tarjeta los pone tres veces.
        ->assertDontSee('Pedro Páramo')
        ->assertDontSee('Juan Rulfo')
        ->assertDontSee('data-test="quote-book-title"', false)
        ->assertDontSee('data-test="quote-authors"', false)
        ->assertDontSee($book->coverUrl(), false);
});

it('fuera de ella lleva el Libro consigo, porque nada más dice de cuál es', function () {
    $book = bookWithPhotograph([
        'title' => 'Pedro Páramo',
        'authors' => ['Juan Rulfo'],
    ]);

    quoteCard($book->addQuote('Vine a Comala.', '7'))
        ->assertSee('Pedro Páramo')
        ->assertSee('Juan Rulfo')
        ->assertSee($book->coverUrl(), false);
});

it('la Portada ocupa alrededor de la cuarta parte del ancho de la tarjeta', function () {
    $book = bookWithPhotograph();

    // La referencia de la que salió el diseño la tiene en esa proporción
    // (docs/design/quote-card-reference.png). Medida contra la tarjeta y no en
    // píxeles propios: así la misma tarjeta sirve en la pantalla y en los dos
    // formatos de la imagen, que miden distinto.
    quoteCard($book->addQuote('Vine a Comala.'))->assertSee('md:w-[26%]', false);
});

it('dibuja la Portada igual en dos Citas de largos muy distintos', function () {
    $book = bookWithPhotograph();

    $short = $book->addQuote('Nunca puedes ganar.');
    $long = $book->addQuote(str_repeat('La biblioteca de Babel es ilimitada y periódica. ', 12));

    // El marco de la Portada mide una fracción del ancho de la tarjeta y su
    // alto sale de la proporción de un libro, así que no depende de lo larga
    // que sea la Cita. Lo que se adapta a la Cita es el tipo.
    $frame = fn (Quote $quote) => Str::betweenFirst(
        quoteCard($quote)->__toString(),
        'data-test="cover-cropped"',
        '</div>',
    );

    expect($frame($short))->toBe($frame($long))
        ->and($frame($short))->toContain('md:self-center');
});

it('dibuja el Cover con las piezas que ya existen, sin resolver imágenes por su cuenta', function () {
    $book = bookWithPhotograph();

    quoteCard($book->addQuote('Vine a Comala.'))
        ->assertSee($book->coverUrl(), false)
        // The proportion and the crop of the image are the ones of <x-cover>:
        // a Cover that comes back almost square is cropped by the same
        // `object-cover` as every other, and the card adds nothing of its own.
        ->assertSee('aspect-2/3', false)
        ->assertSee('object-cover', false)
        ->assertSee('cover-edge', false);
});

it('recorta el Cover por el borde de la tarjeta y lo inclina', function () {
    $book = bookWithPhotograph();

    quoteCard($book->addQuote('Vine a Comala.'))
        ->assertSee('data-test="cover-cropped"', false)
        ->assertSee('overflow-hidden', false)
        ->assertSee('rotate-(--cover-tilt)', false)
        ->assertSee('shadow-(--shadow-cover)', false);
});

it('deja entera la pieza generada, que si se recorta pierde su título', function () {
    $book = Book::factory()->coverGenerated()->create(['title' => 'El llano en llamas']);

    quoteCard($book->addQuote('Vine a Comala.'))
        ->assertSee('data-test="cover-whole"', false)
        ->assertDontSee('data-test="cover-cropped"', false)
        ->assertSee('<svg', false)
        ->assertSee('rotate-(--cover-tilt)', false);
});

it('en el teléfono el Cover va debajo del texto y no encima', function () {
    $book = bookWithPhotograph();

    $html = quoteCard($book->addQuote('Vine a Comala.'))->__toString();

    // The Cover is placed beside the text —and leaning off the edge— only from
    // md up. Below that it is in the flow, after the text.
    expect(strpos($html, 'quote-text'))->toBeLessThan((int) strpos($html, 'cover-cropped'))
        ->and($html)->toContain('md:flex');
});

it('no le lee la imagen a quien no la ve, porque el Libro ya está nombrado', function () {
    $book = bookWithPhotograph(['title' => 'Pedro Páramo']);

    quoteCard($book->addQuote('Vine a Comala.'))
        ->assertSee('alt=""', false)
        ->assertDontSee('alt="Portada de Pedro Páramo"', false);

    $generated = Book::factory()->coverGenerated()->create(['title' => 'El llano en llamas']);

    quoteCard($generated->addQuote('Nos matarán a todos.'))
        ->assertSee('aria-hidden="true"', false)
        ->assertDontSee('aria-label="Portada de El llano en llamas', false);
});

it('la tarjeta trae su papel y su tinta, porque se comparte fuera de la pantalla', function () {
    $quote = Quote::factory()->create();

    quoteCard($quote)
        ->assertSee('paper', false)
        ->assertSee('text-ink', false)
        ->assertSee('text-ink-muted', false);
});
