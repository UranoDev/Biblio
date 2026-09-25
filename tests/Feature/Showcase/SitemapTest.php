<?php

use App\Models\Book;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;

/*
 * The sitemap is the list of what a crawler is invited to walk, and so it is
 * one more surface where a Hidden Book has to be absent (ADR-003). Not listed
 * with a flag: not in the file.
 */

beforeEach(function () {
    Storage::fake();
});

it('lista la portada, cada Libro visible y cada Categoría', function () {
    $books = Book::factory()->count(5)->create();
    $books->first()->fill(['title' => 'Pedro Páramo'])->save();
    $category = Category::factory()->create(['name' => 'Novela mexicana']);
    $category->books()->attach($books->map->getKey()->all());

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('showcase'), escape: false)
        ->assertSee(route('showcase.book', $books->first()), escape: false)
        ->assertSee(route('showcase.category', $category), escape: false);
});

it('no lista un Libro Oculto, y lo agrega en cuanto se deja de ocultar', function () {
    $book = Book::factory()->hidden()->create();

    $this->get('/sitemap.xml')->assertDontSee(route('showcase.book', $book), escape: false);

    $book->show();

    $this->get('/sitemap.xml')->assertSee(route('showcase.book', $book), escape: false);
});

it('no lista una Categoría cuyos Libros están todos Ocultos', function () {
    $book = Book::factory()->hidden()->create();
    $category = Category::factory()->create(['name' => 'Novela mexicana']);
    $book->categories()->attach($category);

    $this->get('/sitemap.xml')->assertDontSee(route('showcase.category', $category), escape: false);
});

it('dice cuándo cambió por última vez cada dirección', function () {
    $book = Book::factory()->create();

    $this->get('/sitemap.xml')
        ->assertSee('<lastmod>'.$book->updated_at?->format('Y-m-d').'</lastmod>', escape: false);
});

it('es XML que se puede leer, con el acervo vacío también', function () {
    $response = $this->get('/sitemap.xml');

    $xml = simplexml_load_string($response->getContent() ?: '');

    expect($xml)->not->toBeFalse()
        ->and($xml->getName())->toBe('urlset');
});
