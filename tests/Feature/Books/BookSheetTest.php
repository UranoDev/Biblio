<?php

use App\Enums\GenerationStatus;
use App\Enums\Origin;
use App\Enums\ReadingStatus;
use App\Enums\Source;
use App\Enums\Status;
use App\Jobs\ProposeForBook;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Category;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/** The sheet of one Book, as it is reached from the Shelf. */
function sheetOf(Book $book): Testable
{
    return Livewire::test('pages::book', ['book' => $book]);
}

it('pide sesión', function () {
    $book = Book::factory()->create();

    auth()->logout();

    $this->get(route('book', $book))->assertRedirect(route('login'));
});

it('abre la ficha del Book', function () {
    $book = Book::factory()->create(['title' => 'Pedro Páramo']);

    $this->get(route('book', $book))
        ->assertOk()
        ->assertSee('Pedro Páramo');
});

it('llena los campos con lo que está guardado', function () {
    $book = Book::factory()->lent('Mariana')->read()->create([
        'title' => 'Cartucho',
        'authors' => ['Nellie Campobello'],
        'publisher' => 'Era',
        'year' => 2000,
        'note' => 'Lo más seco que he leído.',
    ]);

    sheetOf($book)
        ->assertSet('title', 'Cartucho')
        ->assertSet('authors', ['Nellie Campobello'])
        ->assertSet('publisher', 'Era')
        ->assertSet('year', '2000')
        ->assertSet('note', 'Lo más seco que he leído.')
        ->assertSet('isRead', true)
        ->assertSet('readOn', $book->read_on->format('Y-m-d'))
        ->assertSet('lentTo', 'Mariana')
        ->assertSet('isHidden', false);
});

// El Reading, que son tres estados y no dos.

it('marca leído sin poner fecha', function () {
    $book = Book::factory()->create(['is_read' => false, 'read_on' => null]);

    sheetOf($book)
        ->set('isRead', true)
        ->call('saveReading')
        ->assertHasNoErrors();

    $book->refresh();

    expect($book->is_read)->toBeTrue()
        ->and($book->read_on)->toBeNull()
        ->and($book->readingStatus())->toBe(ReadingStatus::ReadUndated);
});

it('marca leído con fecha', function () {
    $book = Book::factory()->create(['is_read' => false, 'read_on' => null]);

    sheetOf($book)
        ->set('isRead', true)
        ->set('readOn', '2019-03-04')
        ->call('saveReading')
        ->assertHasNoErrors();

    $book->refresh();

    expect($book->read_on?->format('Y-m-d'))->toBe('2019-03-04')
        ->and($book->readingStatus())->toBe(ReadingStatus::ReadDated);
});

it('quita la fecha sin desmarcar la lectura', function () {
    $book = Book::factory()->read()->create();

    sheetOf($book)
        ->set('readOn', '')
        ->call('saveReading')
        ->assertHasNoErrors();

    $book->refresh();

    expect($book->is_read)->toBeTrue()
        ->and($book->read_on)->toBeNull()
        ->and($book->readingStatus())->toBe(ReadingStatus::ReadUndated);
});

it('desmarca la lectura y se lleva la fecha', function () {
    $book = Book::factory()->read()->create();

    sheetOf($book)
        ->set('isRead', false)
        ->call('saveReading');

    $book->refresh();

    expect($book->is_read)->toBeFalse()
        ->and($book->read_on)->toBeNull()
        ->and($book->readingStatus())->toBe(ReadingStatus::Unread);
});

it('no acepta una fecha de lectura en el futuro', function () {
    $book = Book::factory()->create();

    sheetOf($book)
        ->set('isRead', true)
        ->set('readOn', now()->addYear()->format('Y-m-d'))
        ->call('saveReading')
        ->assertHasErrors('readOn');

    expect($book->fresh()->is_read)->toBeFalse();
});

// La Note: una sola, editable en su lugar.

it('escribe el apunte y lo edita en su lugar', function () {
    $book = Book::factory()->create(['note' => null]);

    sheetOf($book)
        ->set('note', 'Me dejó la voz del narrador.')
        ->call('saveNote')
        ->assertHasNoErrors()
        ->assertSet('note', 'Me dejó la voz del narrador.');

    expect($book->fresh()->note)->toBe('Me dejó la voz del narrador.');

    sheetOf($book->fresh())
        ->set('note', 'Ya no me dejó lo mismo.')
        ->call('saveNote');

    expect($book->fresh()->note)->toBe('Ya no me dejó lo mismo.')
        ->and(Book::count())->toBe(1);
});

it('vacía el apunte', function () {
    $book = Book::factory()->create(['note' => 'Algo']);

    sheetOf($book)->set('note', '   ')->call('saveNote');

    expect($book->fresh()->note)->toBeNull();
});

// La Description y su Origin (ADR-007).

it('muestra la Description con su Origin', function () {
    $fromSource = Book::factory()->descriptionFromSource()->create();
    $fromAi = Book::factory()->descriptionFromAi('gemini', 'gemini-2.5-flash')->create();
    $mine = Book::factory()->descriptionFromOwner()->create();

    sheetOf($fromSource)->assertSee('La trajo Open Library');
    sheetOf($fromAi)->assertSee('gemini-2.5-flash');
    sheetOf($mine)->assertSee('La escribiste tú');
});

it('dice que una Description mía ya no se regenera', function () {
    $book = Book::factory()->descriptionFromOwner()->create();

    sheetOf($book)->assertSee('Ninguna regeneración la toca');
});

it('al editar la Description queda como mía', function () {
    $book = Book::factory()->descriptionFromAi()->create();

    sheetOf($book)
        ->set('description', 'Un pueblo al que se llega preguntando por un padre.')
        ->call('saveDescription')
        ->assertHasNoErrors();

    $book->refresh();

    expect($book->description)->toBe('Un pueblo al que se llega preguntando por un padre.')
        ->and($book->description_origin)->toBe(Origin::Owner)
        ->and($book->description_ai_model)->toBeNull()
        ->and($book->acceptsAiDescription())->toBeFalse();
});

it('borrar la Description la deja sin Origin', function () {
    $book = Book::factory()->descriptionFromOwner()->create();

    sheetOf($book)->set('description', '')->call('saveDescription');

    $book->refresh();

    expect($book->description)->toBeNull()
        ->and($book->description_origin)->toBeNull();
});

// El préstamo, que es un campo y no una entidad.

it('anota el préstamo con su fecha', function () {
    $book = Book::factory()->create();

    sheetOf($book)
        ->set('lentTo', 'Mi hermana')
        ->set('lentOn', '2026-08-01')
        ->call('saveLending')
        ->assertHasNoErrors();

    $book->refresh();

    expect($book->lent_to)->toBe('Mi hermana')
        ->and($book->lent_on?->format('Y-m-d'))->toBe('2026-08-01');
});

it('anota el préstamo sin fecha', function () {
    $book = Book::factory()->create();

    sheetOf($book)->set('lentTo', 'Un amigo')->call('saveLending');

    $book->refresh();

    expect($book->lent_to)->toBe('Un amigo')
        ->and($book->lent_on)->toBeNull();
});

it('vacía el préstamo cuando el Book regresa', function () {
    $book = Book::factory()->lent('Mariana')->create();

    sheetOf($book)->call('returned')->assertSet('lentTo', '');

    $book->refresh();

    expect($book->lent_to)->toBeNull()
        ->and($book->lent_on)->toBeNull();
});

it('vacía el préstamo al guardarlo en blanco', function () {
    $book = Book::factory()->lent('Mariana')->create();

    sheetOf($book)->set('lentTo', '')->call('saveLending');

    expect($book->fresh()->lent_to)->toBeNull();
});

// El Status y el interruptor de Hidden.

it('cambia el Status en los dos sentidos', function () {
    $book = Book::factory()->owned()->create();

    sheetOf($book)
        ->set('status', Status::Wanted->value)
        ->call('saveShelving')
        ->assertHasNoErrors();

    expect($book->fresh()->status)->toBe(Status::Wanted);

    sheetOf($book->fresh())
        ->set('status', Status::Owned->value)
        ->call('saveShelving');

    expect($book->fresh()->status)->toBe(Status::Owned);
});

it('prende y apaga el interruptor de oculto', function () {
    $book = Book::factory()->create(['is_hidden' => false]);

    sheetOf($book)->set('isHidden', true)->call('saveShelving');

    expect($book->fresh()->is_hidden)->toBeTrue();

    sheetOf($book->fresh())->set('isHidden', false)->call('saveShelving');

    expect($book->fresh()->is_hidden)->toBeFalse();
});

it('dice qué implica que el Book esté oculto', function () {
    $book = Book::factory()->create();

    sheetOf($book)->assertSee('saca el Libro de la Vitrina');
});

// El Record que la Source se equivocó.

it('corrige los campos del Record', function () {
    $book = Book::factory()->create([
        'title' => 'Pedro Paramo',
        'authors' => ['Juan Rulfo'],
        'publisher' => 'Fondo',
        'year' => 1955,
    ]);

    sheetOf($book)
        ->set('title', 'Pedro Páramo')
        ->set('authors', ['Juan Rulfo', ''])
        ->set('publisher', 'Editorial RM')
        ->set('year', '2005')
        ->set('edition', 'Segunda')
        ->call('saveRecord')
        ->assertHasNoErrors();

    $book->refresh();

    expect($book->title)->toBe('Pedro Páramo')
        ->and($book->authors)->toBe(['Juan Rulfo'])
        ->and($book->publisher)->toBe('Editorial RM')
        ->and($book->year)->toBe(2005)
        ->and($book->edition)->toBe('Segunda');
});

it('no deja el Record sin título', function () {
    $book = Book::factory()->create(['title' => 'Cartucho']);

    sheetOf($book)->set('title', '  ')->call('saveRecord')->assertHasErrors('title');

    expect($book->fresh()->title)->toBe('Cartucho');
});

it('deja claro cuál Source llenó el Record', function () {
    $book = Book::factory()->create(['source' => Source::GoogleBooks]);

    sheetOf($book)->assertSee('Estos campos los llenó Google Books');
});

it('no le pone a otro Book un ISBN que ya está registrado', function () {
    $mine = Book::factory()->create(['isbn' => '9788437604176']);
    $other = Book::factory()->create(['isbn' => '9788420471839', 'title' => 'El llano en llamas']);

    sheetOf($mine)
        ->set('isbn', '9788420471839')
        ->call('saveRecord')
        ->assertHasErrors('isbn');

    expect($mine->fresh()->isbn)->toBe('9788437604176')
        ->and($other->fresh()->isbn)->toBe('9788420471839');
});

it('deja guardar el mismo ISBN que ya traía', function () {
    $book = Book::factory()->create(['isbn' => '9788437604176']);

    sheetOf($book)
        ->set('title', 'Otro título')
        ->call('saveRecord')
        ->assertHasNoErrors();

    expect($book->fresh()->isbn)->toBe('9788437604176');
});

// Las Categories del Book.

it('agrega una Category y reusa la que ya existe', function () {
    Category::factory()->named('Novela mexicana')->create();
    $book = Book::factory()->create();

    sheetOf($book)
        ->set('category', 'novela mexicana')
        ->call('addCategory')
        ->assertHasNoErrors();

    expect(Category::count())->toBe(1)
        ->and($book->fresh()->categories->pluck('name')->all())->toBe(['Novela mexicana']);
});

it('agrega una Category que no existía', function () {
    $book = Book::factory()->create();

    sheetOf($book)->set('category', 'Crónica')->call('addCategory');

    expect($book->fresh()->categories->pluck('name')->all())->toBe(['Crónica']);
});

it('no agrega una Category en blanco', function () {
    $book = Book::factory()->create();

    sheetOf($book)->set('category', '  ')->call('addCategory')->assertHasErrors('category');

    expect(Category::count())->toBe(0);
});

it('quita una Category del Book sin borrarla', function () {
    $category = Category::factory()->named('Ensayo')->create();
    $book = Book::factory()->create();
    $book->categories()->attach($category);

    sheetOf($book)->call('removeCategory', $category->getKey());

    expect($book->fresh()->categories)->toHaveCount(0)
        ->and(Category::count())->toBe(1);
});

// Qué se sabe de la generación (ADR-006).

it('dice con qué AiModel se generaron las Categories', function () {
    $book = Book::factory()->categoriesGenerated('gemini', 'gemini-2.5-flash')->create();

    expect($book->categoriesStatus())->toBe(GenerationStatus::Generated);

    sheetOf($book)->assertSee('gemini-2.5-flash');
});

it('dice que las Categories están en la cola cuando hay AiModel elegido', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->create(['categories_ai_model' => null, 'categories_provider' => null]);

    sheetOf($book)
        ->assertSet('categoriesStatus', GenerationStatus::Pending)
        ->assertSee('Están en la cola');
});

it('distingue al Book que espera turno del que falló', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $waiting = Book::factory()->create(['categories_ai_model' => null, 'categories_provider' => null]);
    $failed = Book::factory()->generationFailed('gemini-3.6-flash is currently experiencing high demand')
        ->create(['categories_ai_model' => null, 'categories_provider' => null]);

    sheetOf($waiting)
        ->assertSet('categoriesStatus', GenerationStatus::Pending)
        ->assertSee('Están en la cola')
        ->assertDontSee('dejó de intentar');

    sheetOf($failed)
        ->assertSet('categoriesStatus', GenerationStatus::Failed)
        ->assertSee('dejó de intentar')
        ->assertSee('high demand')
        ->assertDontSee('Están en la cola');
});

it('dice que falta elegir el AiModel cuando no hay ninguno', function () {
    $book = Book::factory()->create(['categories_ai_model' => null, 'categories_provider' => null]);

    sheetOf($book)
        ->assertSet('categoriesStatus', GenerationStatus::Off)
        ->assertSee('Falta elegir el modelo');
});

it('vuelve a pedirle las Categories a la IA', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->categoriesGenerated('gemini', 'gemini-2.5-flash')->create();

    sheetOf($book)
        ->call('regenerateCategories')
        ->assertSee('volvió a la cola');

    Queue::assertPushed(ProposeForBook::class, fn (ProposeForBook $job) => $job->book->is($book));
});

it('el Book que había fallado deja de estar señalado al volver a la cola', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->generationFailed('la API no contestó')->create();

    sheetOf($book)
        ->call('regenerateCategories')
        ->assertSet('categoriesStatus', GenerationStatus::Pending);

    expect($book->fresh()->generation_failed_at)->toBeNull();
});

it('sin AiModel elegido el botón sigue ahí y dice que la generación está apagada', function () {
    Queue::fake();

    $book = Book::factory()->create(['categories_ai_model' => null, 'categories_provider' => null]);

    sheetOf($book)
        ->assertSeeHtml('data-test="regenerate-'.$book->getKey().'"')
        ->assertSee('La generación está apagada')
        ->call('regenerateCategories')
        ->assertSee('La generación está apagada');

    Queue::assertNothingPushed();
});

it('abre con un Book sin Categories y sin Description', function () {
    $book = Book::factory()->withoutDescription()->create([
        'categories_ai_model' => null,
        'categories_provider' => null,
    ]);

    $this->get(route('book', $book))
        ->assertOk()
        ->assertSee('Este Libro no tiene Categorías')
        ->assertSee('Ninguna Fuente trajo descripción');
});

// Lo público y lo que no sale de aquí.

it('distingue lo que se ve en la Vitrina de lo que no', function () {
    $book = Book::factory()->create(['is_hidden' => false]);

    $this->get(route('book', $book))
        ->assertOk()
        ->assertSee('Se ve en la Vitrina')
        ->assertSee('No sale de aquí');
});

it('no dice que algo se ve en la Vitrina cuando el Book está oculto', function () {
    $book = Book::factory()->hidden()->create();

    $this->get(route('book', $book))
        ->assertOk()
        ->assertSee('este Libro está oculto')
        ->assertDontSee('>Se ve en la Vitrina', escape: false);
});

// Las Quotes son del Book y son públicas. Capturarlas, editarlas y borrarlas
// vive en QuotesTest; aquí sólo se verifica que la ficha las muestre.

it('muestra las Quotes del Book', function () {
    $book = Book::factory()->create();
    Quote::factory()->for($book)->create(['text' => 'Vine a Comala porque me dijeron que acá vivía mi padre.', 'page' => 7]);

    $this->get(route('book', $book))
        ->assertOk()
        ->assertSee('Vine a Comala')
        ->assertSee('p. 7');
});

it('abre con un Book sin Quotes', function () {
    $book = Book::factory()->create();

    $this->get(route('book', $book))
        ->assertOk()
        ->assertSee('no tiene Citas guardadas');
});
