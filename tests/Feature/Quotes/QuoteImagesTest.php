<?php

use App\Enums\CoverOrigin;
use App\Enums\ShareFormat;
use App\Jobs\RenderQuoteImages;
use App\Models\Book;
use App\Models\Quote;
use App\Quotes\Credits;
use App\Quotes\Fonts;
use App\Quotes\OutOfCredits;
use App\Quotes\RenderFailed;
use App\Quotes\ShareCard;
use App\Quotes\ShareImages;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * Las dos imágenes con que se comparte una Cita (ADR-009).
 *
 * Ninguna prueba de este archivo —ni de ninguno— llama al servicio real: el
 * renderizador vive detrás de una interfaz y aquí se sustituye por uno falso.
 * Fuera de las que lo sustituyen, el renderizador se queda sin llave y no
 * dibuja nada, así que la suite entera no gasta un crédito.
 */

beforeEach(function () {
    Storage::fake();
});

it('guardar una Cita no espera a la imagen: la deja en la cola', function () {
    Queue::fake();

    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    expect($quote->fresh()->images_queued_at)->not->toBeNull()
        ->and($quote->fresh()->hasImageFor(ShareFormat::Card))->toBeFalse();

    Queue::assertPushed(RenderQuoteImages::class);
});

it('dibuja los dos formatos y los guarda en el disco configurado', function () {
    $renderer = rendererDraws();

    $quote = Book::factory()->create()->addQuote('Vine a Comala.')->fresh();

    expect($renderer->formats())->toBe([ShareFormat::Card, ShareFormat::Story]);

    expect($quote->hasImageFor(ShareFormat::Card))->toBeTrue()
        ->and($quote->hasImageFor(ShareFormat::Story))->toBeTrue()
        ->and($quote->images_queued_at)->toBeNull();

    foreach (ShareFormat::cases() as $format) {
        Storage::disk()->assertExists($quote->imagePathFor($format));
    }
});

it('pide cada formato con su medida', function () {
    $renderer = rendererDraws();

    Book::factory()->create()->addQuote('Vine a Comala.');

    expect(ShareFormat::Card->width())->toBe(1200)
        ->and(ShareFormat::Card->height())->toBe(630)
        ->and(ShareFormat::Story->width())->toBe(1080)
        ->and(ShareFormat::Story->height())->toBe(1350);

    expect($renderer->drawn)->toHaveCount(2);
});

it('la dirección de la imagen cambia cuando cambia lo que muestra', function () {
    rendererDraws();

    $quote = Book::factory()->create()->addQuote('Vine a Comla.')->fresh();

    $before = $quote->imageUrlFor(ShareFormat::Card);

    $quote->rewrite('Vine a Comala.', 7);

    $after = $quote->fresh()->imageUrlFor(ShareFormat::Card);

    expect($after)->not->toBeNull()->and($after)->not->toBe($before);
});

// La consecuencia cara del ADR-008: fotografiar un libro invalida las imágenes
// de todas sus citas.
it('cambiar la Portada del libro manda sus Citas de vuelta a la cola', function () {
    rendererDraws();

    $book = Book::factory()->create();
    $quote = $book->addQuote('Vine a Comala.')->fresh();

    expect($quote->images_queued_at)->toBeNull();

    $book->forceFill([
        'cover_path' => 'covers/1/foto.jpg',
        'cover_origin' => CoverOrigin::Owner,
    ])->save();

    expect($quote->fresh()->imagesFingerprint())->not->toBe($quote->images_fingerprint);
});

it('cambiar el título del libro manda sus Citas de vuelta a la cola', function () {
    Queue::fake();

    $book = Book::factory()->create(['title' => 'Pedro Paramo']);
    $quote = $book->addQuote('Vine a Comala.');

    $quote->forceFill(['images_queued_at' => null])->save();

    $book->forceFill(['title' => 'Pedro Páramo'])->save();

    expect($quote->fresh()->images_queued_at)->not->toBeNull();
});

it('escribir dónde quedaron los archivos no vuelve a encolar la Cita', function () {
    $renderer = rendererDraws();

    Book::factory()->create()->addQuote('Vine a Comala.');

    // Dos dibujos: los dos formatos de una sola corrida. Si escribir las rutas
    // encolara otra vez, esto crecería sin parar.
    expect($renderer->drawn)->toHaveCount(2);
});

/*
 * Las tipografías. Si el renderizador las busca por red y falla, la imagen sale
 * en otra tipografía y nadie se entera: no falla, nada más se ve de otro modo.
 */

it('manda las tipografías dentro de la página, sin nada que pedir por red', function () {
    $renderer = rendererDraws();

    Book::factory()->create()->addQuote('Vine a Comala.');

    $html = $renderer->lastHtml();

    expect($html)
        ->toContain('font-family: "IBM Plex Mono"')
        ->toContain('font-family: "Instrument Sans"')
        ->toContain('src: url(data:font/woff2;base64,');

    // Ni una sola referencia a la red: ni a Bunny, ni al /build de Vite, ni a
    // una dirección de este sitio, que el renderizador tampoco alcanza.
    expect($html)
        ->not->toContain('fonts.bunny.net')
        ->not->toContain('url(http')
        ->not->toContain('url("/build/');
});

it('no dibuja nada si falta un archivo de tipografía, en vez de usar una de respaldo', function () {
    foreach (Fonts::files() as $file) {
        expect(Fonts::path($file))->toBeReadableFile();
    }

    expect(Fonts::faces())->toContain('font-display: block');
});

it('manda la Portada guardada dentro de la página, y no su dirección', function () {
    $renderer = rendererDraws();

    $book = Book::factory()->create(['cover_path' => 'covers/1/abc.jpg']);

    Storage::disk()->put('covers/1/abc.jpg', testImage());

    $book->addQuote('Vine a Comala.');

    expect($renderer->lastHtml())
        ->toContain('src="data:image/jpeg;base64,')
        ->not->toContain(route('cover', ['book' => $book, 'size' => 'full']));
});

it('la imagen es el mismo diseño de la página y no una segunda versión', function () {
    $renderer = rendererDraws();

    $book = Book::factory()->create(['title' => 'Pedro Páramo', 'authors' => ['Juan Rulfo']]);
    $book->addQuote('Vine a Comala.', '7');

    $html = $renderer->lastHtml();

    // La misma tarjeta que dibuja el Showcase: el mismo componente, con sus
    // mismas marcas.
    expect($html)
        ->toContain('data-test="quote-card"')
        ->toContain('data-test="quote-text"')
        ->toContain('«Vine a Comala.»')
        ->toContain('Pedro Páramo')
        ->toContain('Página 7');
});

it('arma la página a la medida exacta del formato', function () {
    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    expect(ShareCard::of($quote, ShareFormat::Story))
        ->toContain('width: 1080px')
        ->toContain('height: 1350px');
});

it('la tarjeta llena el lienzo en vez de quedar flotando en medio', function () {
    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    // El alto de la tarjeta lo fijaba la Portada, no el lienzo, y el resto se
    // repartía arriba y abajo como aire (BIB-49). Lo que queda de papel a la
    // vista es la banda de la página, que se escribe aquí.
    foreach ([ShareFormat::Card, ShareFormat::Story] as $format) {
        expect(ShareCard::of($quote, $format))
            ->toContain('h-full w-full')
            ->not->toContain('items-center justify-center');
    }
});

it('la vertical se apila, y no lo decide el ancho del lienzo', function () {
    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    // Los dos lienzos pasan del punto de quiebre `md:`, así que preguntarle al
    // ancho le daba a la vertical el diseño de dos columnas — una tarjeta corta
    // y ancha dentro de un lienzo mucho más alto que ancho.
    expect(ShareFormat::Story->width())->toBeGreaterThan(768);

    // La hoja de estilos viaja escrita dentro de la página, así que los nombres
    // de las clases se buscan donde la tarjeta los pone y no sueltos: la hoja
    // define las dos familias de tamaños en las dos páginas.
    expect(ShareCard::of($quote, ShareFormat::Story))
        ->toContain('data-test="quote-rule"')
        ->toContain('whitespace-pre-line type-quote-story-large')
        ->not->toContain('md:items-stretch');

    expect(ShareCard::of($quote, ShareFormat::Card))
        ->toContain('md:items-stretch')
        ->toContain('whitespace-pre-line type-quote-large')
        ->not->toContain('whitespace-pre-line type-quote-story');
});

/*
 * La Cita que no cabe en el lienzo.
 */

it('una Cita más larga que el lienzo no se encola ni cuenta como pendiente', function () {
    Queue::fake();

    $quote = Book::factory()->create()
        ->addQuote(str_repeat('La biblioteca de Babel es ilimitada y periódica. ', 20))
        ->fresh();

    // No hay tamaño más chico al que bajar, así que no hay nada que esperar:
    // el enlace compartido sale con la Portada del Libro (ADR-008).
    expect($quote->imagesFit())->toBeFalse()
        ->and($quote->images_queued_at)->toBeNull()
        ->and($quote->hasImageFor(ShareFormat::Card))->toBeFalse()
        ->and(Quote::query()->awaitingImages()->count())->toBe(0);

    Queue::assertNothingPushed();
});

it('tampoco se le pide al renderizador, que cada intento se paga', function () {
    $renderer = rendererDraws();

    Book::factory()->create()->addQuote(str_repeat('La biblioteca de Babel es ilimitada y periódica. ', 20));

    expect($renderer->drawn)->toBeEmpty()
        ->and(Storage::disk()->allFiles())->toBeEmpty();
});

it('una Cita que se alarga de más deja de servir las imágenes que ya tenía', function () {
    rendererDraws();

    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    expect($quote->fresh()->hasImageFor(ShareFormat::Card))->toBeTrue();

    $quote->rewrite(str_repeat('La biblioteca de Babel es ilimitada y periódica. ', 20));

    expect($quote->fresh()->hasImageFor(ShareFormat::Card))->toBeFalse()
        ->and($quote->fresh()->imageUrlFor(ShareFormat::Card))->toBeNull()
        ->and($quote->fresh()->images_queued_at)->toBeNull();
});

/*
 * Lo que pasa cuando el renderizador dice que no.
 */

it('un error de cuota detiene la cola en vez de reintentar', function () {
    $renderer = rendererDraws(OutOfCredits::said('No credits remaining this month.'));

    $first = Book::factory()->create()->addQuote('Vine a Comala.');

    expect(Credits::areOut())->toBeTrue()
        ->and(Credits::reason())->toContain('No credits remaining');

    // La segunda Cita ni siquiera se le pide: la cola está detenida.
    Book::factory()->create()->addQuote('Nada es para siempre.');

    expect($renderer->drawn)->toHaveCount(1)
        ->and($first->fresh()->images_queued_at)->not->toBeNull();
});

it('la cola detenida se retoma cuando hay con qué', function () {
    rendererDraws(OutOfCredits::said('No credits remaining this month.'));

    Book::factory()->create()->addQuote('Vine a Comala.');

    expect(Credits::areOut())->toBeTrue();

    Credits::restored();

    expect(Credits::areOut())->toBeFalse()->and(Credits::reason())->toBeNull();
});

it('un error transitorio sí se reintenta', function () {
    Queue::fake();

    $quote = Book::factory()->create()->addQuote('Vine a Comala.');

    $renderer = rendererDraws(RenderFailed::forNow('The renderer answered 503.'));

    // Volver a lanzar es lo que hace que la cola lo reintente: el trabajo no da
    // la falla por perdida, y la cuota no se declara agotada por un 503.
    expect(fn () => (new RenderQuoteImages($quote))->handle(app(ShareImages::class)))
        ->toThrow(RenderFailed::class);

    expect($renderer->drawn)->not->toBeEmpty()
        ->and(Credits::areOut())->toBeFalse()
        ->and($quote->fresh()->images_failed_at)->toBeNull();

    // Y el reintento tiene tope y espera creciente, no mil intentos seguidos.
    expect((new RenderQuoteImages($quote))->tries())->toBeGreaterThan(1)
        ->and((new RenderQuoteImages($quote))->backoff())->not->toBeEmpty();
});

it('un error que no mejora esperando no se reintenta y queda marcado', function () {
    rendererDraws(RenderFailed::forGood('The renderer has no API key.'));

    $quote = Book::factory()->create()->addQuote('Vine a Comala.')->fresh();

    expect($quote->images_failed_at)->not->toBeNull()
        ->and($quote->images_failure)->toContain('no API key')
        // Sigue debiendo su imagen: la falla no borra la deuda.
        ->and($quote->images_queued_at)->not->toBeNull();
});

it('sin renderizador configurado no se le pide nada y la Cita queda esperando', function () {
    // El renderizador de la suite no tiene llave: es el estado por omisión.
    $quote = Book::factory()->create()->addQuote('Vine a Comala.')->fresh();

    expect($quote->images_queued_at)->not->toBeNull()
        ->and($quote->images_failed_at)->toBeNull()
        ->and($quote->hasImageFor(ShareFormat::Card))->toBeFalse();

    expect(Storage::disk()->allFiles())->toBeEmpty();
});

it('se puede ver cuántas imágenes están pendientes', function () {
    Book::factory()->create()->addQuote('Vine a Comala.');
    Book::factory()->create()->addQuote('Nada es para siempre.');

    expect(Quote::query()->awaitingImages()->count())->toBe(2);

    rendererDraws();

    Quote::query()->awaitingImages()->get()
        ->each(fn (Quote $quote) => (new RenderQuoteImages($quote))->handle(app(ShareImages::class)));

    expect(Quote::query()->awaitingImages()->count())->toBe(0);
});

it('al reemplazar la imagen borra la anterior del disco', function () {
    rendererDraws();

    $quote = Book::factory()->create()->addQuote('Vine a Comla.')->fresh();

    $before = $quote->imageFiles();

    expect($before)->toHaveCount(2);

    $quote->rewrite('Vine a Comala.', 7);

    foreach ($before as $file) {
        Storage::disk()->assertMissing($file);
    }

    foreach ($quote->fresh()->imageFiles() as $file) {
        Storage::disk()->assertExists($file);
    }
});
