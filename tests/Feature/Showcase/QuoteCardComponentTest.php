<?php

use App\Models\Book;
use App\Models\Quote;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestView;

beforeEach(function () {
    Storage::fake();
});

/** The card of a Quote, rendered the way any screen renders it. */
function quoteCard(Quote $quote): TestView
{
    return test()->blade('<x-quote-card :quote="$quote" />', ['quote' => $quote]);
}

it('dibuja la Cita, el título y los autores', function () {
    $book = Book::factory()->create([
        'title' => 'Pedro Páramo',
        'authors' => ['Juan Rulfo'],
    ]);

    $quote = $book->addQuote('Vine a Comala porque me dijeron que acá vivía mi padre.', 7);

    quoteCard($quote)
        ->assertSee('Vine a Comala porque me dijeron que acá vivía mi padre.')
        ->assertSee('Pedro Páramo')
        ->assertSee('Juan Rulfo')
        ->assertSee('Página 7');
});

it('pone comillas tipográficas y no las rectas', function () {
    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    $html = quoteCard($quote)->__toString();

    expect($html)->toContain('“Vine a Comala.”')
        ->and($html)->not->toContain('"Vine a Comala."');
});

it('no repite las comillas de una Cita que ya se tecleó con ellas', function () {
    $quote = Book::factory()->create()->addQuote('«Vine a Comala.»');

    expect(quoteCard($quote)->__toString())
        ->toContain('“Vine a Comala.”')
        ->not->toContain('“«');
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

it('dibuja el Cover con las piezas que ya existen, sin resolver imágenes por su cuenta', function () {
    $book = Book::factory()->create([
        'cover_path' => 'covers/9/abc.jpg',
        'cover_thumbnail_path' => 'covers/9/abc-thumbnail.jpg',
    ]);

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
    $book = Book::factory()->create([
        'cover_path' => 'covers/9/abc.jpg',
        'cover_thumbnail_path' => 'covers/9/abc-thumbnail.jpg',
    ]);

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
    $book = Book::factory()->create([
        'cover_path' => 'covers/9/abc.jpg',
        'cover_thumbnail_path' => 'covers/9/abc-thumbnail.jpg',
    ]);

    $html = quoteCard($book->addQuote('Vine a Comala.'))->__toString();

    // The Cover is placed against the card —and so over the paper the text is
    // on— only from md up. Below that it is in the flow, after the text.
    expect($html)->toContain('md:contents')
        // And the text keeps a gutter for it, so the leaning Cover does not
        // land on top of the Quote.
        ->and($html)->toContain('md:pr-[50%]')
        ->and(strpos($html, 'quote-text'))->toBeLessThan((int) strpos($html, 'cover-cropped'));
});

it('no le lee la imagen a quien no la ve, porque el Libro ya está nombrado', function () {
    $book = Book::factory()->create([
        'title' => 'Pedro Páramo',
        'cover_path' => 'covers/9/abc.jpg',
        'cover_thumbnail_path' => 'covers/9/abc-thumbnail.jpg',
    ]);

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
