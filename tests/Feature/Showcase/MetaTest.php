<?php

use App\Models\Book;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;

/*
 * What every public page says about itself: the title, the description, the
 * canonical address and the card a network draws (BIB-37).
 *
 * The reason this is tested and not looked at once: what a page does not
 * declare, a network scrapes. Measured on 2026-09-24, a Book sheet with no tags
 * came out announcing that the book was read and half a Quote cut mid-sentence,
 * and nothing on the screen said so.
 */

beforeEach(function () {
    Storage::fake();
});

it('la portada dice de qué es y cuánto hay, y es su propia canónica', function () {
    $book = Book::factory()->create(['title' => 'Pedro Páramo']);
    $book->addQuote('Vine a Comala porque me dijeron que acá vivía mi padre.', 7);

    $this->get(route('showcase'))
        ->assertSee('<title>'.config('app.name').'</title>', escape: false)
        ->assertSee('<link rel="canonical" href="'.route('showcase').'">', escape: false)
        ->assertSee('Un libro y una cita.', escape: false);
});

it('la ficha de un Libro lleva su título, su descripción y su canónica', function () {
    $book = Book::factory()->descriptionFromSource()->create([
        'title' => 'Pedro Páramo',
        'description' => 'Un hombre llega a Comala buscando a su padre.',
    ]);

    $this->get(route('showcase.book', $book))
        ->assertSee('<title>Pedro Páramo · '.config('app.name').'</title>', escape: false)
        ->assertSee('<link rel="canonical" href="'.route('showcase.book', $book).'">', escape: false)
        ->assertSee('content="Un hombre llega a Comala buscando a su padre.', escape: false);
});

it('la descripción de un Libro sin Description sale de sus autores, su editorial y su año', function () {
    $book = Book::factory()->withoutDescription()->create([
        'authors' => ['Juan Rulfo'],
        'publisher' => 'Editorial RM',
        'year' => 2005,
    ]);

    $this->get(route('showcase.book', $book))
        ->assertSee('content="Juan Rulfo · Editorial RM · 2005', escape: false);
});

it('la descripción de un Libro dice cuántas Citas tiene, y no las transcribe', function () {
    $book = Book::factory()->read()->descriptionFromSource()->create([
        'description' => 'Un hombre llega a Comala buscando a su padre.',
    ]);

    $book->addQuote('Vine a Comala porque me dijeron que acá vivía mi padre.', 7);
    $book->addQuote('Nada puede durar tanto, no existe ningún recuerdo por intenso que sea que no se apague.', 9);

    $response = $this->get(route('showcase.book', $book));

    $description = metaOf($response->getContent() ?: '', 'og:description');

    expect($description)->toContain('2 citas')
        // Whether I read it is nobody else's business, and a Quote cut
        // mid-sentence wastes the one line the card has.
        ->and($description)->not->toContain('Leído')
        ->and($description)->not->toContain('Vine a Comala');
});

it('un Libro sin Citas no dice «0 citas»', function () {
    $book = Book::factory()->descriptionFromSource()->create([
        'description' => 'Un hombre llega a Comala buscando a su padre.',
    ]);

    expect(metaOf($this->get(route('showcase.book', $book))->getContent() ?: '', 'og:description'))
        ->not->toContain('cita');
});

it('la ficha de un Libro declara su tarjeta entera, sin dejar que la red adivine', function () {
    $book = Book::factory()->create(['title' => 'Pedro Páramo']);

    $this->get(route('showcase.book', $book))
        ->assertSee('property="og:type" content="book"', escape: false)
        ->assertSee('property="og:title" content="Pedro Páramo"', escape: false)
        ->assertSee('property="og:url" content="'.route('showcase.book', $book).'"', escape: false)
        ->assertSee('property="og:site_name" content="'.config('app.name').'"', escape: false)
        ->assertSee('property="og:image" content="'.$book->coverUrl().'"', escape: false)
        ->assertSee('property="og:image:alt" content="Portada de Pedro Páramo"', escape: false)
        ->assertSee('name="twitter:card" content="summary_large_image"', escape: false);
});

it('un Libro cuya Portada es la pieza generada no anuncia una imagen que no existe', function () {
    $book = Book::factory()->coverGenerated()->create();

    $this->get(route('showcase.book', $book))
        ->assertDontSee('property="og:image"', escape: false)
        ->assertSee('name="twitter:card" content="summary"', escape: false);
});

it('la página de una Categoría dice cuántos Libros hay en ella', function () {
    $category = Category::factory()->create(['name' => 'Novela mexicana']);
    Book::factory()->count(3)->create()->each(fn (Book $book) => $book->categories()->attach($category));

    $this->get(route('showcase.category', $category))
        ->assertSee('<title>Novela mexicana · '.config('app.name').'</title>', escape: false)
        ->assertSee('<link rel="canonical" href="'.route('showcase.category', $category).'">', escape: false)
        ->assertSee('content="3 libros de Novela mexicana en '.config('app.name').'."', escape: false);
});

it('ninguna página pública pide que no la indexen', function () {
    $book = Book::factory()->create();
    $category = Category::factory()->create();
    $book->categories()->attach($category);

    foreach ([route('showcase'), route('showcase.book', $book), route('showcase.category', $category)] as $address) {
        $this->get($address)->assertDontSee('noindex');
    }
});

/** What one meta tag of the served HTML says. */
function metaOf(string $html, string $name): string
{
    preg_match('/(?:property|name)="'.preg_quote($name, '/').'" content="([^"]*)"/', $html, $found);

    return $found[1] ?? '';
}
