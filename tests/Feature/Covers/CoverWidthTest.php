<?php

use App\Models\Book;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake();
});

/**
 * The stored image and the generated piece are both «la Portada», so they are
 * measured the same and every case here runs against the two of them.
 */
function coverOf(string $kind): Book
{
    return $kind === 'guardada'
        ? Book::factory()->create([
            'cover_path' => 'covers/9/abc.jpg',
            'cover_thumbnail_path' => 'covers/9/abc-thumbnail.jpg',
        ])
        : Book::factory()->coverGenerated()->create();
}

dataset('las dos Portadas', ['guardada', 'generada']);

it('se deja medir por quien la usa, en vez de imponer su ancho', function (string $kind) {
    $book = coverOf($kind);

    $this->blade('<x-cover :book="$book" class="w-40 shrink-0" />', ['book' => $book])
        ->assertSee('w-40', false)
        ->assertSee('shrink-0', false)
        // The defect: `$attributes->class()` adds instead of replacing, so both
        // widths landed on the element and the compiled stylesheet decided.
        ->assertDontSee('w-full', false);
})->with('las dos Portadas');

it('sigue ocupando el ancho disponible cuando no le piden uno', function (string $kind) {
    $book = coverOf($kind);

    $this->blade('<x-cover :book="$book" class="rotate-(--cover-tilt)" />', ['book' => $book])
        ->assertSee('w-full', false)
        ->assertSee('rotate-(--cover-tilt)', false);
})->with('las dos Portadas');

it('conserva la proporción de un libro sea cual sea el ancho', function (string $kind) {
    $book = coverOf($kind);

    $this->blade('<x-cover :book="$book" class="w-40" />', ['book' => $book])
        ->assertSee('aspect-2/3', false)
        ->assertSee('cover-edge', false);

    $this->blade('<x-cover :book="$book" />', ['book' => $book])
        ->assertSee('aspect-2/3', false)
        ->assertSee('cover-edge', false);
})->with('las dos Portadas');

it('reconoce el ancho aunque venga con variante o en la forma de size', function (string $kind, string $class) {
    $book = coverOf($kind);

    $this->blade('<x-cover :book="$book" :class="$class" />', ['book' => $book, 'class' => $class])
        ->assertSee($class, false)
        ->assertDontSee('w-full', false);
})->with('las dos Portadas')->with([
    'md:w-40',
    'w-[10rem]',
    'w-1/2',
    'size-40',
]);

it('no confunde acotar un ancho con fijarlo', function (string $kind, string $class) {
    $book = coverOf($kind);

    // `min-w-0` and `max-w-xs` bound a width without setting one, so the Cover
    // still has to fill the box it is given.
    $this->blade('<x-cover :book="$book" :class="$class" />', ['book' => $book, 'class' => $class])
        ->assertSee('w-full', false);
})->with('las dos Portadas')->with([
    'min-w-0',
    'max-w-xs',
]);

it('la pieza generada se mide igual cuando se usa por su cuenta', function () {
    $book = Book::factory()->coverGenerated()->create();

    $this->blade('<x-generated-cover :piece="$piece" class="w-40" />', ['piece' => $book->generatedCover()])
        ->assertSee('w-40', false)
        ->assertDontSee('w-full', false);

    $this->blade('<x-generated-cover :piece="$piece" />', ['piece' => $book->generatedCover()])
        ->assertSee('w-full', false);
});

it('la Portada de la ficha mide los 160 px que la ficha le pide', function (string $kind) {
    $html = Livewire::test('book-cover', ['book' => coverOf($kind)])->html();

    // Only the Cover is looked at: the rest of the block has controls with
    // widths of their own, and they are not what is being measured here.
    preg_match('/<(?:img|svg)\b[^>]*cover-edge[^>]*>/', $html, $cover);

    expect($cover[0] ?? '')
        ->toContain('w-40')
        ->not->toContain('w-full');
})->with('las dos Portadas');
