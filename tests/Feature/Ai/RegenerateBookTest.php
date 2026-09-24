<?php

use App\Ai\BookAgent;
use App\Enums\GenerationStatus;
use App\Enums\Origin;
use App\Enums\RegenerationTarget;
use App\Jobs\ProposeForBook;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * El botón que le vuelve a pedir a la IA lo de un Book, en las dos pantallas
 * donde se necesita: la retícula del Acervo y la ficha del Libro (BIB-22).
 *
 * Ninguna prueba llama al proveedor real: o la cola está falsa y nada corre, o
 * el agente contesta lo que la prueba le dice.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function theShelf(): Testable
{
    return Livewire::test('pages::shelf');
}

function theSheetOf(Book $book): Testable
{
    return Livewire::test('pages::book', ['book' => $book]);
}

/** Lo que el agente contesta cuando la prueba deja correr la cola. */
function theAiAnswers(string $description = 'Lo que escribió la IA.'): void
{
    BookAgent::fake(fn (): array => [
        'categories' => ['Novela mexicana'],
        'description' => $description,
    ]);
}

// El botón, en las dos pantallas.

it('ofrece las tres cosas en la retícula del Acervo', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->create();

    theShelf()
        ->assertSeeHtml('data-test="regenerate-'.$book->getKey().'"')
        ->assertSeeHtml('data-test="regenerate-'.$book->getKey().'-categories"')
        ->assertSeeHtml('data-test="regenerate-'.$book->getKey().'-descriptions"')
        ->assertSeeHtml('data-test="regenerate-'.$book->getKey().'-both"');
});

it('ofrece las tres cosas en la ficha del Libro', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->create();

    theSheetOf($book)
        ->assertSeeHtml('data-test="regenerate-'.$book->getKey().'-categories"')
        ->assertSeeHtml('data-test="regenerate-'.$book->getKey().'-descriptions"')
        ->assertSeeHtml('data-test="regenerate-'.$book->getKey().'-both"');
});

it('encola sólo las Categories cuando eso es lo que se pidió', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->create();

    theShelf()->call('regenerate', $book->getKey(), 'categories');

    Queue::assertPushed(
        ProposeForBook::class,
        fn (ProposeForBook $job): bool => $job->book->is($book) && $job->target === RegenerationTarget::Categories,
    );
});

it('encola sólo la Description cuando eso es lo que se pidió', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->descriptionFromAi()->create();

    theSheetOf($book)->call('regenerate', $book->getKey(), 'descriptions');

    Queue::assertPushed(
        ProposeForBook::class,
        fn (ProposeForBook $job): bool => $job->target === RegenerationTarget::Descriptions,
    );
});

it('encola las dos cuando se piden las dos', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->descriptionFromAi()->create();

    theShelf()->call('regenerate', $book->getKey(), 'both');

    Queue::assertPushed(
        ProposeForBook::class,
        fn (ProposeForBook $job): bool => $job->target === RegenerationTarget::Both,
    );
});

it('la ficha ofrece regenerar sólo las Categories desde donde se corrigen', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->create();

    theSheetOf($book)->call('regenerateCategories')->assertSee('volvió a la cola');

    Queue::assertPushed(
        ProposeForBook::class,
        fn (ProposeForBook $job): bool => $job->target === RegenerationTarget::Categories,
    );
});

// La Description que no es de la IA no se toca nunca (ADR-007).

it('no pisa la Description que trajo la Fuente', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');
    theAiAnswers('Lo que inventó la IA.');

    $book = Book::factory()->descriptionFromSource()->create();
    $brought = $book->description;

    theShelf()->call('regenerate', $book->getKey(), 'both');

    expect($book->fresh()->description)->toBe($brought)
        ->and($book->fresh()->description_origin)->toBe(Origin::Source);
});

it('no pisa la Description que escribí yo', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');
    theAiAnswers('Lo que inventó la IA.');

    $book = Book::factory()->descriptionFromOwner()->create();
    $mine = $book->description;

    theShelf()->call('regenerate', $book->getKey(), 'both');

    expect($book->fresh()->description)->toBe($mine)
        ->and($book->fresh()->description_origin)->toBe(Origin::Owner);
});

it('dice que no la toca, y no encola nada, cuando se pide sólo esa Description', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->descriptionFromOwner()->create(['title' => 'Cartucho']);

    theSheetOf($book)
        ->call('regenerate', $book->getKey(), 'descriptions')
        ->assertSee('no la escribió la IA');

    Queue::assertNothingPushed();
});

it('pidiendo las dos, encola las Categories y avisa que la Description se queda', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->descriptionFromSource()->create();

    theShelf()
        ->call('regenerate', $book->getKey(), 'both')
        ->assertSee('Su descripción se queda como está');

    Queue::assertPushed(
        ProposeForBook::class,
        fn (ProposeForBook $job): bool => $job->target === RegenerationTarget::Categories,
    );
});

it('sí reescribe la Description que había escrito la IA', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');
    theAiAnswers('La que escribió el modelo nuevo.');

    $book = Book::factory()->descriptionFromAi('gemini', 'gemini-2.0-flash')->create();

    theSheetOf($book)->call('regenerate', $book->getKey(), 'descriptions');

    expect($book->fresh()->description)->toBe('La que escribió el modelo nuevo.')
        ->and($book->fresh()->description_origin)->toBe(Origin::Ai);
});

// Mientras va en camino.

it('el Book dice que está en la cola desde que se aprieta el botón', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->categoriesGenerated()->create();

    expect($book->isGenerationQueued())->toBeFalse();

    theSheetOf($book)->call('regenerateCategories')->assertSet('categoriesStatus', GenerationStatus::Pending);

    expect($book->fresh()->isGenerationQueued())->toBeTrue();

    theShelf()->assertSeeHtml('data-test="generation-queued-'.$book->getKey().'"');
});

it('deja de decirlo cuando la respuesta llega', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');
    theAiAnswers();

    $book = Book::factory()->categoriesGenerated()->create();

    theShelf()->call('regenerate', $book->getKey(), 'categories');

    expect($book->fresh()->isGenerationQueued())->toBeFalse();

    theShelf()->assertDontSeeHtml('data-test="generation-queued-'.$book->getKey().'"');
});

it('la retícula se queda mirando mientras haya algo en la cola', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    Book::factory()->generationQueued()->create();

    theShelf()->assertSet('awaiting', true)->assertSeeHtml('wire:poll.5s');

    Book::query()->update(['generation_queued_at' => null]);

    theShelf()->assertSet('awaiting', false)->assertDontSeeHtml('wire:poll.5s');
});

// Las Categories corregidas a mano.

it('corregir las Categories a mano queda anotado en el Book', function () {
    $book = Book::factory()->create();
    $category = Category::factory()->named('Poesía')->create();

    expect($book->categoriesEditedByHand())->toBeFalse();

    theSheetOf($book)->set('category', 'Poesía')->call('addCategory');

    expect($book->fresh()->categoriesEditedByHand())->toBeTrue();

    $book->forceFill(['categories_edited_at' => null])->save();

    theSheetOf($book->fresh())->call('removeCategory', $category->getKey());

    expect($book->fresh()->categoriesEditedByHand())->toBeTrue();
});

it('avisa antes de reemplazar unas Categories corregidas a mano', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->categoriesEditedByHand()->create(['title' => 'Cartucho']);

    theShelf()
        ->call('regenerate', $book->getKey(), 'categories')
        ->assertSeeHtml('data-test="confirm-regeneration"')
        ->assertSee('Cartucho');

    Queue::assertNothingPushed();
});

it('encola cuando se contesta que sí', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->categoriesEditedByHand()->create();

    theShelf()
        ->call('regenerate', $book->getKey(), 'categories')
        ->call('confirmRegeneration')
        ->assertSee('volvió a la cola')
        ->assertDontSeeHtml('data-test="confirm-regeneration"');

    Queue::assertPushed(ProposeForBook::class);
});

it('no encola nada cuando se contesta que no', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->categoriesEditedByHand()->create();

    theShelf()
        ->call('regenerate', $book->getKey(), 'categories')
        ->call('cancelRegeneration')
        ->assertDontSeeHtml('data-test="confirm-regeneration"');

    Queue::assertNothingPushed();

    expect($book->fresh()->categoriesEditedByHand())->toBeTrue();
});

it('no pregunta cuando lo que se pidió fue sólo la Description', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->categoriesEditedByHand()->descriptionFromAi()->create();

    theShelf()
        ->call('regenerate', $book->getKey(), 'descriptions')
        ->assertDontSeeHtml('data-test="confirm-regeneration"');

    Queue::assertPushed(ProposeForBook::class);
});

it('lo que la IA escribe vuelve a ser suyo: la marca de corregido a mano se borra', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');
    theAiAnswers();

    $book = Book::factory()->categoriesEditedByHand()->create();

    theShelf()
        ->call('regenerate', $book->getKey(), 'categories')
        ->call('confirmRegeneration');

    expect($book->fresh()->categoriesEditedByHand())->toBeFalse();
});

// Sin AiModel elegido.

it('sin AiModel el botón sigue ahí, lo explica y lleva a la configuración', function () {
    $book = Book::factory()->create();

    theShelf()
        ->assertSeeHtml('data-test="regenerate-'.$book->getKey().'"')
        ->assertSeeHtml('data-test="regenerate-'.$book->getKey().'-off"')
        ->assertSee('La generación está apagada')
        ->assertSeeHtml(route('ai-model.edit'));

    theSheetOf($book)
        ->assertSeeHtml('data-test="regenerate-'.$book->getKey().'-off"')
        ->assertSeeHtml(route('ai-model.edit'));
});

it('sin AiModel no encola nada, y lo dice si se lo piden', function () {
    Queue::fake();

    $book = Book::factory()->create();

    theShelf()
        ->call('regenerate', $book->getKey(), 'categories')
        ->assertSee('La generación está apagada');

    Queue::assertNothingPushed();
});

// Los candidatos a regenerar.

it('la retícula filtra los Books que quedaron con otro AiModel', function () {
    AiSetting::choose('gemini', 'gemini-3.6-flash');

    $stale = Book::factory()->categoriesGenerated('gemini', 'gemini-2.5-flash')->create(['title' => 'Cartucho']);
    $current = Book::factory()->categoriesGenerated('gemini', 'gemini-3.6-flash')->create(['title' => 'Pedro Páramo']);

    theShelf()
        ->assertSee($stale->title)
        ->assertSee($current->title)
        ->set('fromAnotherAiModel', true)
        ->assertSee($stale->title)
        ->assertDontSee($current->title);
});

it('también cuenta como candidato el que quedó con otra Description', function () {
    AiSetting::choose('gemini', 'gemini-3.6-flash');

    $stale = Book::factory()
        ->categoriesGenerated('gemini', 'gemini-3.6-flash')
        ->descriptionFromAi('gemini', 'gemini-2.5-flash')
        ->create(['title' => 'Cartucho']);

    theShelf()->set('fromAnotherAiModel', true)->assertSee($stale->title);
});

it('sin AiModel elegido no se ofrece ese filtro', function () {
    Book::factory()->create();

    theShelf()->assertDontSeeHtml('data-test="another-ai-model-filter"');

    AiSetting::choose('gemini', 'gemini-3.6-flash');

    theShelf()->assertSeeHtml('data-test="another-ai-model-filter"');
});

it('quitar los filtros también quita el del AiModel', function () {
    AiSetting::choose('gemini', 'gemini-3.6-flash');

    Book::factory()->create();

    theShelf()
        ->set('fromAnotherAiModel', true)
        ->assertSet('filtering', true)
        ->call('clearFilters')
        ->assertSet('fromAnotherAiModel', false);
});

// El fallo del proveedor.

it('el Book que falló queda señalado en la retícula y se puede reintentar', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->generationFailed('la API no contestó')->create();

    theShelf()->assertSeeHtml('data-test="generation-failed-'.$book->getKey().'"');

    theShelf()->call('regenerate', $book->getKey(), 'both');

    expect($book->fresh()->generation_failed_at)->toBeNull()
        ->and($book->fresh()->isGenerationQueued())->toBeTrue();

    Queue::assertPushed(ProposeForBook::class);
});

it('la ficha sólo se deja regenerar su propio Book', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $book = Book::factory()->create();
    $another = Book::factory()->create();

    theSheetOf($book)->call('regenerate', $another->getKey(), 'both');

    Queue::assertNothingPushed();
});
