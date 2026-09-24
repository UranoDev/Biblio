<?php

use App\Enums\ShareFormat;
use App\Enums\ShareImageStatus;
use App\Models\Book;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * The page of one Quote (BIB-31): the one made for being pasted somewhere, and
 * the one somebody receiving the link opens.
 */

beforeEach(function () {
    Storage::fake();
});

/** A Quote with its two images already drawn and stored on the disk. */
function quoteWithImages(array $attributes = []): Quote
{
    $quote = Book::factory()->create($attributes)->addQuote('Vine a Comala.', '7');

    Storage::disk()->put('quotes/1/card.png', 'wide');
    Storage::disk()->put('quotes/1/story.png', 'tall');

    $quote->imagesStored(
        card: 'quotes/1/card.png',
        story: 'quotes/1/story.png',
        fingerprint: $quote->imagesFingerprint(),
    );

    return $quote->fresh();
}

it('cada Cita tiene su propia dirección pública, corta y estable', function () {
    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    expect($quote->shareUrl())->toEndWith('/quote/'.$quote->getKey());

    $this->get($quote->shareUrl())->assertOk();
});

it('la página lleva la Cita, el libro y el camino de vuelta a su ficha', function () {
    $book = Book::factory()->create(['title' => 'Pedro Páramo', 'authors' => ['Juan Rulfo']]);
    $quote = $book->addQuote('Vine a Comala porque me dijeron que acá vivía mi padre.', '7');

    $this->get($quote->shareUrl())
        ->assertOk()
        ->assertSee('Vine a Comala porque me dijeron que acá vivía mi padre.')
        ->assertSee('Pedro Páramo')
        ->assertSee('Juan Rulfo')
        ->assertSee(route('showcase.book', $book), false);
});

it('la dirección de la Cita se queda igual aunque se corrija el texto', function () {
    $quote = Book::factory()->create()->addQuote('Vine a Comla.');

    $before = $quote->shareUrl();

    $quote->rewrite('Vine a Comala.', 7);

    expect($quote->fresh()->shareUrl())->toBe($before);
});

/*
 * Lo que una red lee al pegar el enlace. Ninguna de estas etiquetas se escribe
 * a mano: salen de la Cita y del Book.
 */

it('declara las etiquetas con que una red arma la tarjeta', function () {
    $book = Book::factory()->create(['title' => 'Pedro Páramo']);
    $quote = $book->addQuote('Vine a Comala.', '7');

    $html = $this->get($quote->shareUrl())->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:title" content="Pedro Páramo">')
        ->toContain('<meta property="og:description" content="Vine a Comala.">')
        ->toContain('<meta property="og:url" content="'.$quote->shareUrl().'">')
        ->toContain('<meta property="og:type" content="article">')
        ->toContain('<meta name="twitter:card" content=');
});

it('la imagen de la tarjeta es la de la Cita cuando ya está dibujada', function () {
    $quote = quoteWithImages();

    $this->get($quote->shareUrl())
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.$quote->imageUrlFor(ShareFormat::Card).'">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
});

// El criterio de BIB-31: un enlace compartido nunca sale pelado.
it('sin imagen todavía, la tarjeta cae en la Portada del libro', function () {
    $book = Book::factory()->create(['cover_path' => 'covers/1/abc.jpg']);
    $quote = $book->addQuote('Vine a Comala.');

    expect($quote->imageUrlFor(ShareFormat::Card))->toBeNull();

    $this->get($quote->shareUrl())
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.$book->coverUrl().'">', false);
});

it('mientras no hay imagen, el botón de descarga es un aviso y no un archivo roto', function () {
    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    $this->get($quote->shareUrl())
        ->assertOk()
        ->assertSee('todavía no está lista')
        ->assertDontSee(route('quote.download', $quote), false);
});

it('con la imagen lista, el botón la ofrece', function () {
    $quote = quoteWithImages();

    $this->get($quote->shareUrl())
        ->assertOk()
        ->assertSee(route('quote.download', $quote), false)
        ->assertDontSee('todavía no está lista');
});

/*
 * Y la otra mitad: «todavía no está lista» sólo se dice de lo que de verdad
 * está en camino (BIB-48). Las tres Citas escritas antes de que existiera la
 * cola nunca despacharon un job, y la página les prometía una imagen que nadie
 * había pedido.
 */

it('una Cita que nunca pasó por la cola no promete una imagen en camino', function () {
    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    // Así quedaron las que se guardaron antes de que el hook existiera: sin
    // imagen, sin marca de la cola, y sin nada que fuera a volver por ellas.
    DB::table('quotes')->where('id', $quote->id)->update(['images_queued_at' => null]);

    expect($quote->fresh()->shareImageStatus())->toBe(ShareImageStatus::NotAsked);

    $this->get($quote->shareUrl())
        ->assertOk()
        ->assertSee('Esta cita no tiene imagen para descargar.')
        ->assertDontSee('todavía no está lista')
        ->assertDontSee(route('quote.download', $quote), false);
});

it('la que sí está en la cola y la que nunca se pidió no dicen lo mismo', function () {
    $waiting = Book::factory()->create()->addQuote('Nada es para siempre.');

    $never = Book::factory()->create()->addQuote('Vine a Comala.');
    DB::table('quotes')->where('id', $never->id)->update(['images_queued_at' => null]);

    expect($waiting->fresh()->shareImageStatus())->toBe(ShareImageStatus::Queued)
        ->and($never->fresh()->shareImageStatus())->toBe(ShareImageStatus::NotAsked);

    $this->get($waiting->shareUrl())->assertOk()->assertSee('todavía no está lista');

    $this->get($never->shareUrl())->assertOk()->assertDontSee('todavía no está lista');
});

it('la Cita que el renderizador rechazó tampoco dice que su imagen viene en camino', function () {
    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    $quote->imagesFailed('The renderer has no API key.');

    // La marca de la cola sigue puesta —la deuda no se borra al fallar— y aun
    // así no hay nada en camino: el job ya se rindió.
    expect($quote->fresh()->images_queued_at)->not->toBeNull()
        ->and($quote->fresh()->shareImageStatus())->toBe(ShareImageStatus::Failed);

    $this->get($quote->shareUrl())
        ->assertOk()
        ->assertDontSee('todavía no está lista')
        // Lo que dijo el servicio es cosa del back office, no del visitante.
        ->assertDontSee('API key');
});

it('una Cita que no cabe en una imagen lo dice, en vez de decir que ya casi', function () {
    $book = Book::factory()->create(['title' => 'Ficciones']);
    $quote = $book->addQuote(str_repeat('La biblioteca de Babel es ilimitada y periódica. ', 20));

    $this->get($quote->shareUrl())
        ->assertOk()
        // La Cita se lee entera: la página nunca la recorta.
        ->assertSee('La biblioteca de Babel es ilimitada y periódica. La biblioteca')
        ->assertSee('es más larga de lo que cabe legible en una')
        // Y no hay imagen en camino, así que no dice que la haya.
        ->assertDontSee('todavía no está lista')
        ->assertDontSee(route('quote.download', $quote), false)
        // El enlace compartido cae en la Portada del Libro (ADR-008).
        ->assertSee('<meta property="og:image" content="'.$book->coverUrl().'">', false);
});

/*
 * Lo Oculto. Una Quote hereda del Book y no tiene visibilidad propia (ADR-003),
 * y la imagen obedece lo mismo: por eso se sirve por una ruta y no como archivo
 * suelto.
 */

it('la Cita de un libro oculto responde 404', function () {
    $quote = Book::factory()->hidden()->create()->addQuote('Vine a Comala.');

    $this->get(route('showcase.quote', $quote))->assertNotFound();
});

it('la imagen de la Cita de un libro oculto tampoco se sirve', function () {
    $quote = quoteWithImages();

    $url = $quote->imageUrlFor(ShareFormat::Card);

    $this->get($url)->assertOk();

    $quote->book->hide();

    $this->get($url)->assertNotFound();
});

it('la descarga de la Cita de un libro oculto tampoco se sirve', function () {
    $quote = quoteWithImages();

    $quote->book->hide();

    $this->get(route('quote.download', $quote))->assertNotFound();
});

it('sirve la imagen guardada por su dirección', function () {
    $quote = quoteWithImages();

    $response = $this->get($quote->imageUrlFor(ShareFormat::Story));

    $response->assertOk();

    expect($response->streamedContent())->toBe('tall');
});

it('una dirección con otra firma no existe, porque la imagen que nombraba ya no', function () {
    $quote = quoteWithImages();

    $stale = route('quote.image', [
        'quote' => $quote,
        'format' => ShareFormat::Card,
        'fingerprint' => str_repeat('0', 32),
    ]);

    $this->get($stale)->assertNotFound();
});

it('la descarga entrega el archivo con un nombre que se lee', function () {
    $quote = quoteWithImages(['title' => 'Pedro Páramo']);

    $response = $this->get(route('quote.download', $quote))->assertOk();

    // El acento viaja aparte, como manda el estándar, y el nombre sin acentos
    // es el respaldo para el navegador que no lo lee. Los dos dicen el libro y
    // la página: lo que no dice ninguno es un identificador.
    expect($response->headers->get('Content-Disposition'))
        ->toContain('filename="Pedro Paramo - pagina 7.png"')
        ->toContain("filename*=utf-8''Pedro%20P%C3%A1ramo%20-%20pagina%207.png");
});

it('la Cita entra al sitemap, y sale con su libro', function () {
    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    $this->get(route('sitemap'))
        ->assertOk()
        ->assertSee(route('showcase.quote', $quote), false);

    $quote->book->hide();

    $this->get(route('sitemap'))
        ->assertOk()
        ->assertDontSee(route('showcase.quote', $quote), false);
});
