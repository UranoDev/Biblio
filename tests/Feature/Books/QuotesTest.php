<?php

use App\Models\Book;
use App\Models\Quote;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/** The sheet of one Book, which is where the Quotes get written. */
function quotesOf(Book $book): Testable
{
    return Livewire::test('pages::book', ['book' => $book]);
}

// Capturar.

it('agrega una Quote con su texto y su página', function () {
    $book = Book::factory()->create();

    quotesOf($book)
        ->set('quoteText', 'Vine a Comala porque me dijeron que acá vivía mi padre.')
        ->set('quotePage', '7')
        ->call('saveQuote')
        ->assertHasNoErrors();

    $quote = Quote::query()->sole();

    expect($quote->book_id)->toBe($book->getKey())
        ->and($quote->text)->toBe('Vine a Comala porque me dijeron que acá vivía mi padre.')
        ->and($quote->page)->toBe(7);
});

it('agrega una Quote sin página, porque la página es opcional', function () {
    $book = Book::factory()->create();

    quotesOf($book)
        ->set('quoteText', 'Nada puede durar tanto tiempo.')
        ->call('saveQuote')
        ->assertHasNoErrors();

    expect(Quote::query()->sole()->page)->toBeNull();
});

it('deja el campo listo para la siguiente cita', function () {
    $book = Book::factory()->create();

    quotesOf($book)
        ->set('quoteText', 'La primera.')
        ->set('quotePage', '11')
        ->call('saveQuote')
        ->assertSet('quoteText', '')
        ->assertSet('quotePage', '')
        ->assertSet('editingQuote', null)
        ->assertDispatched('quote-written');
});

it('captura varias seguidas sin recargar', function () {
    $book = Book::factory()->create();

    $sheet = quotesOf($book);

    foreach (['La primera.', 'La segunda.', 'La tercera.'] as $position => $text) {
        $sheet->set('quoteText', $text)
            ->set('quotePage', (string) ($position + 1))
            ->call('saveQuote')
            ->assertHasNoErrors();
    }

    expect($book->quotes()->pluck('text')->all())
        ->toBe(['La primera.', 'La segunda.', 'La tercera.']);
});

it('avisa cuando la cita quedó guardada', function () {
    $book = Book::factory()->create();

    quotesOf($book)
        ->set('quoteText', 'Una cita.')
        ->call('saveQuote')
        ->assertSet('notice', 'La cita quedó guardada.');
});

it('pide el texto de la cita', function () {
    $book = Book::factory()->create();

    quotesOf($book)
        ->set('quoteText', '   ')
        ->call('saveQuote')
        ->assertHasErrors('quoteText');

    expect(Quote::query()->count())->toBe(0);
});

it('no acepta una página que no sea un número', function () {
    $book = Book::factory()->create();

    quotesOf($book)
        ->set('quoteText', 'Una cita.')
        ->set('quotePage', 'siete')
        ->call('saveQuote')
        ->assertHasErrors('quotePage');

    expect(Quote::query()->count())->toBe(0);
});

it('no acepta una página menor que uno', function () {
    $book = Book::factory()->create();

    quotesOf($book)
        ->set('quoteText', 'Una cita.')
        ->set('quotePage', '0')
        ->call('saveQuote')
        ->assertHasErrors('quotePage');
});

// Los saltos de línea.

it('conserva los saltos de línea del texto capturado', function () {
    $book = Book::factory()->create();

    quotesOf($book)
        ->set('quoteText', "Primer verso\r\nSegundo verso\r\n\r\nSegunda estrofa")
        ->call('saveQuote');

    expect(Quote::query()->sole()->text)
        ->toBe("Primer verso\nSegundo verso\n\nSegunda estrofa");
});

it('quita el espacio de alrededor sin tocar el de adentro', function () {
    $book = Book::factory()->create();

    quotesOf($book)
        ->set('quoteText', "\n  Primer verso\nSegundo verso  \n\n")
        ->call('saveQuote');

    expect(Quote::query()->sole()->text)->toBe("Primer verso\nSegundo verso");
});

it('dibuja los saltos de línea en la pantalla', function () {
    $book = Book::factory()->create();
    Quote::factory()->for($book)->create(['text' => "Primer verso\nSegundo verso"]);

    $this->get(route('book', $book))
        ->assertOk()
        ->assertSee('whitespace-pre-line', escape: false);
});

// El orden.

it('ordena las Quotes por página, y las que no la traen al final', function () {
    $book = Book::factory()->create();

    $late = Quote::factory()->for($book)->create(['text' => 'La de la página 200', 'page' => 200]);
    $undatedFirst = Quote::factory()->for($book)->create(['text' => 'Sin página, la primera', 'page' => null]);
    $early = Quote::factory()->for($book)->create(['text' => 'La de la página 7', 'page' => 7]);
    $undatedSecond = Quote::factory()->for($book)->create(['text' => 'Sin página, la segunda', 'page' => null]);

    expect($book->quotes()->pluck('id')->all())->toBe([
        $early->getKey(),
        $late->getKey(),
        $undatedFirst->getKey(),
        $undatedSecond->getKey(),
    ]);
});

it('mantiene el orden después de agregar una cita de una página anterior', function () {
    $book = Book::factory()->create();

    quotesOf($book)
        ->set('quoteText', 'La de la página 200')
        ->set('quotePage', '200')
        ->call('saveQuote')
        ->set('quoteText', 'La de la página 7')
        ->set('quotePage', '7')
        ->call('saveQuote');

    expect($book->quotes()->pluck('text')->all())
        ->toBe(['La de la página 7', 'La de la página 200']);
});

// Editar.

it('edita una Quote existente', function () {
    $book = Book::factory()->create();
    $quote = Quote::factory()->for($book)->create(['text' => 'Vine a Comala', 'page' => 7]);

    quotesOf($book)
        ->call('editQuote', $quote->getKey())
        ->assertSet('editingQuote', $quote->getKey())
        ->assertSet('quoteText', 'Vine a Comala')
        ->assertSet('quotePage', '7')
        ->set('quoteText', 'Vine a Comala porque me dijeron que acá vivía mi padre.')
        ->set('quotePage', '9')
        ->call('saveQuote')
        ->assertHasNoErrors()
        ->assertSet('editingQuote', null)
        ->assertSet('quoteText', '')
        ->assertSet('notice', 'La cita quedó corregida.');

    expect(Quote::query()->count())->toBe(1)
        ->and($quote->refresh()->text)->toBe('Vine a Comala porque me dijeron que acá vivía mi padre.')
        ->and($quote->page)->toBe(9);
});

it('deja sin página una Quote que la tenía', function () {
    $book = Book::factory()->create();
    $quote = Quote::factory()->for($book)->create(['page' => 7]);

    quotesOf($book)
        ->call('editQuote', $quote->getKey())
        ->set('quotePage', '')
        ->call('saveQuote');

    expect($quote->refresh()->page)->toBeNull();
});

it('cancela la edición sin tocar la Quote', function () {
    $book = Book::factory()->create();
    $quote = Quote::factory()->for($book)->create(['text' => 'Vine a Comala']);

    quotesOf($book)
        ->call('editQuote', $quote->getKey())
        ->set('quoteText', 'Otra cosa')
        ->call('cancelQuote')
        ->assertSet('editingQuote', null)
        ->assertSet('quoteText', '');

    expect($quote->refresh()->text)->toBe('Vine a Comala');
});

it('no edita la Quote de otro Book', function () {
    $book = Book::factory()->create();
    $other = Quote::factory()->for(Book::factory())->create(['text' => 'De otro Libro']);

    quotesOf($book)
        ->call('editQuote', $other->getKey())
        ->assertStatus(404)
        ->assertSet('editingQuote', null);

    expect($other->refresh()->text)->toBe('De otro Libro');
});

// Borrar.

it('borra una Quote', function () {
    $book = Book::factory()->create();
    $quote = Quote::factory()->for($book)->create();
    $stays = Quote::factory()->for($book)->create();

    quotesOf($book)
        ->call('deleteQuote', $quote->getKey())
        ->assertSet('notice', 'La cita se borró.');

    expect(Quote::query()->pluck('id')->all())->toBe([$stays->getKey()]);
});

it('deja el formulario limpio si borra la Quote que estaba editando', function () {
    $book = Book::factory()->create();
    $quote = Quote::factory()->for($book)->create();

    quotesOf($book)
        ->call('editQuote', $quote->getKey())
        ->call('deleteQuote', $quote->getKey())
        ->assertSet('editingQuote', null)
        ->assertSet('quoteText', '');
});

it('no borra la Quote de otro Book', function () {
    $book = Book::factory()->create();
    $other = Quote::factory()->for(Book::factory())->create();

    quotesOf($book)
        ->call('deleteQuote', $other->getKey())
        ->assertStatus(404);

    expect(Quote::query()->whereKey($other->getKey())->exists())->toBeTrue();
});

it('borra las Quotes junto con su Book', function () {
    $book = Book::factory()->create();
    Quote::factory()->count(3)->for($book)->create();

    $survivor = Quote::factory()->for(Book::factory())->create();

    $book->delete();

    expect(Quote::query()->pluck('id')->all())->toBe([$survivor->getKey()]);
});

// Lo público y lo privado.

it('dice que las Citas se ven en la Vitrina y el apunte no', function () {
    $book = Book::factory()->create();

    $this->get(route('book', $book))
        ->assertOk()
        ->assertSeeInOrder(['Citas', 'Agregar cita'])
        ->assertSee('Se ve en la Vitrina')
        ->assertSeeInOrder(['No sale de aquí', 'Apunte']);
});
