<?php

use App\Enums\GenerationStatus;
use App\Enums\Origin;
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
        ->assertSet('reading', Status::ANY_READ)
        ->assertSet('readOn', $book->read_on->format('Y-m-d'))
        ->assertSet('lentTo', 'Mariana')
        ->assertSet('isHidden', false);
});

// El Reading, que son cuatro estados y no dos.

it('marca leído sin poner fecha', function () {
    $book = Book::factory()->create(['read_on' => null]);

    sheetOf($book)
        ->set('reading', Status::ANY_READ)
        ->call('saveReading')
        ->assertHasNoErrors();

    $book->refresh();

    expect($book->status)->toBe(Status::Read)
        ->and($book->read_on)->toBeNull();
});

it('marca leído con fecha', function () {
    $book = Book::factory()->create(['read_on' => null]);

    sheetOf($book)
        ->set('reading', Status::ANY_READ)
        ->set('readOn', '2019-03-04')
        ->call('saveReading')
        ->assertHasNoErrors();

    $book->refresh();

    expect($book->read_on?->format('Y-m-d'))->toBe('2019-03-04')
        ->and($book->status)->toBe(Status::Read);
});

it('quita la fecha sin desmarcar la lectura', function () {
    $book = Book::factory()->read()->create();

    sheetOf($book)
        ->set('readOn', '')
        ->call('saveReading')
        ->assertHasNoErrors();

    $book->refresh();

    expect($book->status)->toBe(Status::Read)
        ->and($book->read_on)->toBeNull();
});

it('desmarca la lectura y se lleva la fecha', function () {
    $book = Book::factory()->read()->create();

    sheetOf($book)
        ->set('reading', Status::Owned->value)
        ->call('saveReading');

    $book->refresh();

    expect($book->status)->toBe(Status::Owned)
        ->and($book->read_on)->toBeNull();
});

it('no acepta una fecha de lectura en el futuro', function () {
    $book = Book::factory()->create();

    sheetOf($book)
        ->set('reading', Status::ANY_READ)
        ->set('readOn', now()->addYear()->format('Y-m-d'))
        ->call('saveReading')
        ->assertHasErrors('readOn');

    expect($book->fresh()->status)->toBe(Status::Owned);
});

it('marca el Libro que estoy leyendo', function () {
    $book = Book::factory()->create();

    sheetOf($book)
        ->set('reading', Status::Reading->value)
        ->call('saveReading')
        ->assertHasNoErrors()
        ->assertSet('reading', Status::Reading->value);

    expect($book->fresh()->status)->toBe(Status::Reading);
});

it('saca del buró el Libro que se marca leído', function () {
    $book = Book::factory()->beingRead()->create();

    sheetOf($book)
        ->assertSet('reading', Status::Reading->value)
        ->set('reading', Status::ANY_READ)
        ->set('readOn', '2026-01-15')
        ->call('saveReading')
        ->assertHasNoErrors();

    $book->refresh();

    expect($book->status)->toBe(Status::Read);
});

it('saca del buró el Libro que se marca no leído', function () {
    $book = Book::factory()->beingRead()->create();

    sheetOf($book)
        ->set('reading', Status::Owned->value)
        ->call('saveReading')
        ->assertHasNoErrors();

    $book->refresh();

    expect($book->status)->toBe(Status::Owned);
});

it('deja sin fecha de lectura al Libro que se pone en curso', function () {
    $book = Book::factory()->read()->create();

    sheetOf($book)
        ->set('reading', Status::Reading->value)
        ->call('saveReading')
        ->assertHasNoErrors()
        ->assertSet('readOn', '');

    $book->refresh();

    expect($book->status)->toBe(Status::Reading)
        ->and($book->read_on)->toBeNull();
});

it('no guarda un estado de lectura que no existe', function () {
    $book = Book::factory()->create();

    sheetOf($book)
        ->set('reading', 'read_dated')
        ->call('saveReading')
        ->assertHasErrors('reading');

    expect($book->fresh()->status)->toBe(Status::Owned);
});

it('ofrece los tres estados de lectura en la ficha', function () {
    $book = Book::factory()->create();

    $html = $this->get(route('book', $book))->assertOk()->getContent();

    expect($html)->toContain('data-test="reading-unread"')
        ->and($html)->toContain('data-test="reading-in-progress"')
        ->and($html)->toContain('data-test="reading-read"');
});

it('sólo pide la fecha cuando el Libro está leído', function () {
    sheetOf(Book::factory()->read()->create())
        ->assertSee('Cuándo lo leí');

    sheetOf(Book::factory()->beingRead()->create())
        ->assertDontSee('Cuándo lo leí');
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

// La Description en otro idioma, y su traducción (BIB-45).

/**
 * El texto viejo decía «Ninguna regeneración la toca» de toda Description de la
 * Fuente, y eso dejó de ser cierto: la que vino en otro idioma sí se toca.
 */
it('distingue la Description de la Fuente que se queda de la que se va a traducir', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $spanish = Book::factory()->descriptionFromSource()->create([
        'description' => 'La historia de Comala, contada por sus muertos, en una novela breve.',
    ]);
    $english = Book::factory()->descriptionInAnotherLanguage()->create();

    sheetOf($spanish)
        ->assertSee('La trajo Open Library. Ninguna regeneración la toca.');

    sheetOf($english)
        ->assertSee('en otro idioma')
        ->assertSee('gemini-2.5-flash')
        ->assertDontSee('Ninguna regeneración la toca');
});

it('sin modelo elegido dice que falta elegirlo para traducirla', function () {
    $book = Book::factory()->descriptionInAnotherLanguage()->create();

    sheetOf($book)
        ->assertSee('Falta elegir el modelo para traducirla')
        ->assertDontSee('Ninguna regeneración la toca');
});

it('dice quién tradujo la Description, y que se vuelve a traducir desde el original', function () {
    $book = Book::factory()->descriptionTranslated('gemini', 'gemini-3.1-flash-lite')->create();

    sheetOf($book)
        ->assertSee('la tradujo gemini-3.1-flash-lite')
        ->assertSee('siempre desde el original');
});

it('enseña el texto original de una Description traducida', function () {
    $book = Book::factory()->descriptionTranslated()->create();

    sheetOf($book)->assertSee((string) $book->description_original);
});

it('no enseña ningún original cuando no hay traducción', function () {
    $book = Book::factory()->descriptionFromSource()->create();

    sheetOf($book)->assertDontSee('Ver el texto original');
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

    expect(Category::query()->where('is_canonical', false)->count())->toBe(1)
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

    expect(Category::query()->where('is_canonical', false)->count())->toBe(0);
});

it('quita una Category del Book sin borrarla', function () {
    $category = Category::factory()->named('Ensayo')->create();
    $book = Book::factory()->create();
    $book->categories()->attach($category);

    sheetOf($book)->call('removeCategory', $category->getKey());

    expect($book->fresh()->categories)->toHaveCount(0)
        ->and(Category::query()->where('is_canonical', false)->count())->toBe(1);
});

// Qué se sabe de la generación (ADR-006).

it('dice con qué AiModel se generaron las Categories', function () {
    $book = Book::factory()->categoriesGenerated('gemini', 'gemini-2.5-flash')->create();

    expect($book->categoriesStatus())->toBe(GenerationStatus::Generated);

    sheetOf($book)->assertSee('gemini-2.5-flash');
});

it('dice que las Categories están en la cola, y nombra el AiModel con el que se encolaron', function () {
    // Otro modelo es el elegido ahora: lo que la ficha nombra es el que viaja
    // con la petición, no el de hoy (BIB-46).
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->generationQueued('deepseek', 'deepseek-v4-flash')
        ->create(['categories_ai_model' => null, 'categories_provider' => null]);

    sheetOf($book)
        ->assertSet('categoriesStatus', GenerationStatus::Pending)
        // El modelo elegido hoy sí aparece en el botón de regenerar, que es lo
        // que se le va a pedir ahora; lo que no aparece es en la frase del
        // estado, que habla de la petición que ya está en la cola.
        ->assertSee('Están en la cola de deepseek-v4-flash');
});

it('el Book que nunca se le pidió a la IA no dice que está en la cola', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->create(['categories_ai_model' => null, 'categories_provider' => null]);

    sheetOf($book)
        ->assertSet('categoriesStatus', GenerationStatus::NotAsked)
        ->assertSee('Todavía no se le han pedido a la IA')
        ->assertDontSee('Están en la cola');
});

it('distingue al Book que espera turno del que falló', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $waiting = Book::factory()->generationQueued()
        ->create(['categories_ai_model' => null, 'categories_provider' => null]);
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
    Quote::factory()->for($book)->create(['text' => 'Vine a Comala porque me dijeron que acá vivía mi padre.', 'page' => '7']);

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
