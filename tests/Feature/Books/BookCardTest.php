<?php

use App\Books\ShareCard;
use App\Books\ShareImage;
use App\Enums\ShareFormat;
use App\Jobs\RenderBookImage;
use App\Models\Book;
use App\Quotes\Credits;
use App\Quotes\OutOfCredits;
use App\Quotes\RenderFailed;
use App\Showcase\Meta;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * La tarjeta horizontal con que se comparte un Libro (BIB-50).
 *
 * Pegar el enlace de un Libro en X sacaba una franja de la portada con las
 * letras cortadas: la portada es vertical y la tarjeta grande de las redes es
 * apaisada, así que la recortaban para llenar el marco. Lo que se dibuja ahora
 * es una tarjeta de 1200x630, donde la portada va en su propia proporción.
 *
 * Ninguna prueba de este archivo llama al servicio real: el renderizador vive
 * detrás de la interfaz del ADR-009 y aquí se sustituye por uno falso.
 */

beforeEach(function () {
    Storage::fake();
});

it('dibuja la tarjeta cuando llega la Portada y la guarda en el disco configurado', function () {
    $renderer = bookRendererDraws();

    $book = Book::factory()->coverGenerated()->create();

    $book->forceFill(['cover_path' => 'covers/1/abc.jpg'])->save();

    $book->refresh();

    expect($renderer->formats())->toBe([ShareFormat::shared()])
        ->and($book->share_image_path)->not->toBeNull()
        ->and($book->share_image_queued_at)->toBeNull();

    Storage::disk()->assertExists($book->share_image_path);
});

it('la dibuja en el lienzo apaisado que las redes esperan, y no en la proporción de un libro', function () {
    expect(ShareFormat::shared()->width())->toBe(1200)
        ->and(ShareFormat::shared()->height())->toBe(630);

    $book = Book::factory()->create();

    expect(ShareCard::of($book))
        ->toContain('width: 1200px')
        ->toContain('height: 630px');
});

it('guardar un Libro no espera a la tarjeta: la deja en la cola', function () {
    Queue::fake();

    $book = Book::factory()->coverGenerated()->create();

    $book->forceFill(['cover_path' => 'covers/1/abc.jpg'])->save();

    expect($book->fresh()->share_image_queued_at)->not->toBeNull()
        ->and($book->fresh()->hasShareImage())->toBeFalse();

    Queue::assertPushed(RenderBookImage::class);
});

it('cambiar el título o los Autores manda la tarjeta de vuelta a la cola', function (string $column, mixed $value) {
    Queue::fake();

    $book = Book::factory()->create();

    $book->forceFill(['share_image_queued_at' => null])->save();

    $book->forceFill([$column => $value])->save();

    expect($book->fresh()->share_image_queued_at)->not->toBeNull();

    Queue::assertPushed(RenderBookImage::class);
})->with([
    'el título' => ['title', 'Pedro Páramo'],
    'los Autores' => ['authors', ['Juan Rulfo']],
]);

it('la dirección de la tarjeta cambia cuando cambia lo que muestra', function () {
    bookRendererDraws();

    $book = Book::factory()->coverGenerated()->create(['title' => 'Pedro Paramo']);

    $book->forceFill(['cover_path' => 'covers/1/abc.jpg'])->save();

    $before = $book->fresh()->shareImageUrl();

    $book->fresh()->forceFill(['title' => 'Pedro Páramo'])->save();

    $after = $book->fresh()->shareImageUrl();

    expect($before)->not->toBeNull()
        ->and($after)->not->toBeNull()
        ->and($after)->not->toBe($before);
});

it('escribir dónde quedó el archivo no vuelve a encolar el Libro', function () {
    $renderer = bookRendererDraws();

    $book = Book::factory()->coverGenerated()->create();

    $book->forceFill(['cover_path' => 'covers/1/abc.jpg'])->save();

    // Un solo dibujo. Si escribir la ruta encolara otra vez, esto crecería sin
    // parar.
    expect($renderer->drawn)->toHaveCount(1);
});

/*
 * La tarjeta es el mismo diseño del sitio, rasterizado (ADR-009), y la página
 * que se le manda al renderizador no tiene nada que pedir por red.
 */

it('lleva la Portada, el título y los Autores, y ninguna otra cosa que leer', function () {
    $book = Book::factory()->create(['title' => 'Pedro Páramo', 'authors' => ['Juan Rulfo']]);

    expect(ShareCard::of($book))
        ->toContain('data-test="book-card"')
        ->toContain('data-test="book-card-title"')
        ->toContain('Pedro Páramo')
        ->toContain('Juan Rulfo')
        ->not->toContain($book->description);
});

it('manda la Portada y las tipografías dentro de la página, sin nada que pedir por red', function () {
    $book = Book::factory()->create(['cover_path' => 'covers/1/abc.jpg']);

    Storage::disk()->put('covers/1/abc.jpg', testImage());

    $html = ShareCard::of($book);

    expect($html)
        ->toContain('src="data:image/jpeg;base64,')
        ->toContain('font-family: "Instrument Sans"')
        ->toContain('src: url(data:font/woff2;base64,')
        ->not->toContain(route('cover', ['book' => $book, 'size' => 'full']));

    expect($html)
        ->not->toContain('fonts.bunny.net')
        ->not->toContain('url(http')
        ->not->toContain('url("/build/');
});

it('baja el tamaño del título largo en vez de cortarlo', function () {
    $long = 'Vida y opiniones del caballero Tristram Shandy, seguidas de un viaje sentimental';

    $short = Book::factory()->create(['title' => 'Pedro Páramo']);
    $wordy = Book::factory()->create(['title' => $long]);

    expect(ShareCard::for($short)->titleIsLong())->toBeFalse()
        ->and(ShareCard::for($wordy)->titleIsLong())->toBeTrue();

    // Largo o corto, se imprime entero: la tarjeta también es la imagen que se
    // comparte, y ahí unos puntos suspensivos no llevan a ninguna parte.
    expect(ShareCard::of($wordy))->toContain($long);
});

/*
 * Lo que dice la cabeza de la página pública.
 */

it('la ficha pública declara la tarjeta y la tarjeta grande', function () {
    bookRendererDraws();

    $book = Book::factory()->coverGenerated()->create();

    $book->forceFill(['cover_path' => 'covers/1/abc.jpg'])->save();

    $book->refresh();

    $meta = Meta::book($book->load('quotes'));

    expect($meta->image)->toBe($book->shareImageUrl())
        ->and($meta->card())->toBe('summary_large_image');

    $this->get(route('showcase.book', $book))
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.e($book->shareImageUrl()).'">', false);
});

it('un Libro sin Portada guardada sigue sin og:image y con la tarjeta chica', function () {
    $book = Book::factory()->coverGenerated()->create();

    $meta = Meta::book($book->load('quotes'));

    expect($book->hasShareImage())->toBeFalse()
        ->and($meta->image)->toBeNull()
        ->and($meta->card())->toBe('summary');

    $this->get(route('showcase.book', $book))
        ->assertOk()
        ->assertDontSee('og:image', false);
});

// Mientras la tarjeta está en la cola no se manda la Portada en su lugar: las
// redes guardan `og:image` por dirección y por días, así que un enlace
// compartido en esa ventana se quedaría justamente con el recorte que BIB-50
// vino a arreglar.
it('mientras la tarjeta se dibuja no manda la Portada en su lugar', function () {
    Queue::fake();

    $book = Book::factory()->coverGenerated()->create();

    $book->forceFill(['cover_path' => 'covers/1/abc.jpg'])->save();

    $meta = Meta::book($book->fresh()->load('quotes'));

    expect($meta->image)->toBeNull()
        ->and($book->fresh()->coverUrl())->not->toBeNull();
});

it('quitar la Portada deja al Libro sin tarjeta y borra el archivo', function () {
    bookRendererDraws();

    $book = Book::factory()->coverGenerated()->create();

    $book->forceFill(['cover_path' => 'covers/1/abc.jpg'])->save();

    $file = $book->fresh()->share_image_path;

    expect($file)->not->toBeNull();

    $book->fresh()->forceFill(['cover_path' => null])->save();

    expect($book->fresh()->share_image_path)->toBeNull()
        ->and($book->fresh()->hasShareImage())->toBeFalse();

    Storage::disk()->assertMissing($file);
});

/*
 * Se sirve por una ruta de la aplicación y no como archivo suelto, porque un
 * archivo estático sobrevive a que su Libro se oculte (ADR-003).
 */

it('sirve la tarjeta por su dirección firmada', function () {
    bookRendererDraws();

    $book = Book::factory()->coverGenerated()->create();

    $book->forceFill(['cover_path' => 'covers/1/abc.jpg'])->save();

    $book->refresh();

    $this->get($book->shareImageUrl())->assertOk();
});

it('no entrega la tarjeta de un Libro Oculto a quien no entró', function () {
    bookRendererDraws();

    $book = Book::factory()->coverGenerated()->create();

    $book->forceFill(['cover_path' => 'covers/1/abc.jpg'])->save();

    $address = $book->fresh()->shareImageUrl();

    $book->fresh()->forceFill(['is_hidden' => true])->save();

    $this->get($address)->assertNotFound();
});

it('no entrega la tarjeta bajo una firma que ya no corresponde', function () {
    bookRendererDraws();

    $book = Book::factory()->coverGenerated()->create();

    $book->forceFill(['cover_path' => 'covers/1/abc.jpg'])->save();

    $this->get(route('book.card', ['book' => $book, 'fingerprint' => str_repeat('0', 32)]))
        ->assertNotFound();
});

/*
 * Lo que pasa cuando el renderizador dice que no (ADR-009).
 */

it('no dibuja nada mientras no haya créditos', function () {
    $renderer = bookRendererDraws();

    Credits::ranOut('No credits remaining this month.');

    $book = Book::factory()->coverGenerated()->create();

    $book->forceFill(['cover_path' => 'covers/1/abc.jpg'])->save();

    expect($renderer->drawn)->toBeEmpty()
        ->and($book->fresh()->share_image_queued_at)->not->toBeNull();
});

it('al acabarse los créditos detiene la cola en vez de reintentar', function () {
    bookRendererDraws(OutOfCredits::said('No credits remaining this month.'));

    $book = Book::factory()->coverGenerated()->create();

    $book->forceFill(['cover_path' => 'covers/1/abc.jpg'])->save();

    expect(Credits::areOut())->toBeTrue()
        ->and(Credits::reason())->toBe('No credits remaining this month.');
});

it('un rechazo que esperar no arregla se anota en el Libro', function () {
    bookRendererDraws(RenderFailed::forGood('The renderer has no API key.'));

    $book = Book::factory()->coverGenerated()->create();

    $book->forceFill(['cover_path' => 'covers/1/abc.jpg'])->save();

    expect($book->fresh()->share_image_failed_at)->not->toBeNull()
        ->and($book->fresh()->share_image_failure)->toContain('The renderer has no API key.');
});

it('sin llave del servicio el Libro se queda esperando, sin fallar', function () {
    $book = Book::factory()->coverGenerated()->create();

    expect(app(ShareImage::class)->available())->toBeFalse();

    $book->forceFill(['cover_path' => 'covers/1/abc.jpg'])->save();

    expect($book->fresh()->share_image_queued_at)->not->toBeNull()
        ->and($book->fresh()->share_image_failed_at)->toBeNull();
});
