<?php

use App\Ai\BookAgent;
use App\Ai\Proposals;
use App\Dashboard\Workload;
use App\Enums\GenerationStatus;
use App\Enums\RegenerationTarget;
use App\Jobs\ProposeForBook;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\User;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\PretendJob;

/**
 * Lo que la ficha de un Libro dice después de que su petición a la IA no pasó
 * (BIB-46).
 *
 * El síntoma que trae el issue: un Libro anunciando «Está en la cola de
 * deepseek-v4-flash» doce horas después de que su job muriera con un 503 de
 * gemini-3.6-flash. Dos mentiras en una frase — la espera no existía y el
 * proveedor nombrado nunca vio esa petición— y ninguna superficie donde se
 * notara que algo había fallado.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/** La ficha de un Libro, como se llega a ella desde el Acervo. */
function failureSheetOf(Book $book): Testable
{
    return Livewire::test('pages::book', ['book' => $book]);
}

/**
 * Corre el job como lo corre un worker, con su middleware antes de handle().
 *
 * Lo que decide un job ante un fallo —volver a la cola o fallar— es una llamada
 * a release() o a fail(), y en la conexión `sync` esas llamadas no van a
 * ninguna parte. PretendJob es la otra mitad: recibe el fail() y le avisa al job
 * por su failed(), que es donde se anota el fallo en el Libro.
 */
function runUntilItFails(ProposeForBook $job): PretendJob
{
    $pretend = new PretendJob($job, 1);

    $job->setJob($pretend);

    (new Pipeline(app()))
        ->send($job)
        ->through($job->middleware())
        ->then(fn (ProposeForBook $each) => app()->call([$each, 'handle']));

    return $pretend;
}

// El recorrido completo: encolar, fallar, y qué dice la ficha después.

it('encola, falla, y la ficha deja de decir que está en la cola', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-3.6-flash');

    $book = Book::factory()->withoutDescription()->create([
        'categories_ai_model' => null,
        'categories_provider' => null,
    ]);

    // Encolado: la ficha lo dice desde que se apretó el botón, y nombra el
    // AiModel con el que se encoló.
    expect(app(Proposals::class)->queueFor($book))->toBeTrue();

    $book->refresh();

    expect($book->categoriesStatus())->toBe(GenerationStatus::Pending)
        ->and($book->descriptionStatus())->toBe(GenerationStatus::Pending)
        ->and($book->generation_ai_model)->toBe('gemini-3.6-flash');

    failureSheetOf($book)
        ->assertSee('Está en la cola de gemini-3.6-flash')
        ->assertDontSee('dejó de intentar');

    // El proveedor se cae, y el modelo elegido cambia mientras el job muere.
    BookAgent::fake(fn () => throw new RuntimeException('The model is overloaded. Please try again later.'));

    runUntilItFails(new ProposeForBook($book, 'gemini', 'gemini-3.6-flash'));

    AiSetting::choose('deepseek', 'deepseek-v4-flash');

    $book->refresh();

    expect($book->categoriesStatus())->toBe(GenerationStatus::Failed)
        ->and($book->descriptionStatus())->toBe(GenerationStatus::Failed)
        ->and($book->isGenerationQueued())->toBeFalse()
        ->and($book->generation_queued_at)->toBeNull();

    failureSheetOf($book)
        ->assertSee('dejó de intentar')
        ->assertSee('The model is overloaded')
        // El proveedor y el modelo con los que falló, no los elegidos ahora.
        ->assertSee('gemini-3.6-flash')
        ->assertDontSee('Está en la cola')
        ->assertDontSee('en la cola de deepseek-v4-flash');
});

it('nombra el proveedor y el AiModel con los que falló, y no los elegidos ahora', function () {
    AiSetting::choose('deepseek', 'deepseek-v4-flash');

    $book = Book::factory()->withoutDescription()->generationFailed(
        'The model is overloaded.',
        'gemini',
        'gemini-3.6-flash',
    )->create(['categories_ai_model' => null, 'categories_provider' => null]);

    failureSheetOf($book)
        ->assertSee('con gemini-3.6-flash, del proveedor gemini')
        ->assertDontSee('en la cola de deepseek-v4-flash');
});

it('dice cuándo falló, y hace cuánto', function () {
    $this->freezeTime();

    $book = Book::factory()->withoutDescription()->create();

    $book->generationFailed('Service unavailable.', 'gemini', 'gemini-3.6-flash');

    $this->travel(12)->hours();

    failureSheetOf($book->fresh())->assertSee('hace 12 horas');
});

// La espera se sostiene en la marca de la cola, y esa marca caduca.

it('un Book marcado como encolado más de lo que un job puede vivir deja de decir que está en la cola', function () {
    AiSetting::choose('gemini', 'gemini-3.6-flash');

    $book = Book::factory()->withoutDescription()->generationAbandoned('gemini', 'gemini-3.6-flash')
        ->create(['categories_ai_model' => null, 'categories_provider' => null]);

    expect($book->isGenerationQueued())->toBeFalse()
        ->and($book->generationWasAbandoned())->toBeTrue()
        ->and($book->categoriesStatus())->toBe(GenerationStatus::Failed)
        ->and($book->descriptionStatus())->toBe(GenerationStatus::Failed);

    failureSheetOf($book)
        ->assertDontSee('Está en la cola')
        ->assertSee('la cola nunca volvió a contestar');
});

it('la marca de la cola todavía fresca sí cuenta como una espera', function () {
    AiSetting::choose('gemini', 'gemini-3.6-flash');

    $book = Book::factory()->withoutDescription()->generationQueued('gemini', 'gemini-3.6-flash')->create();

    $this->travel(11)->hours();

    expect($book->fresh()->isGenerationQueued())->toBeTrue();

    $this->travel(2)->hours();

    expect($book->fresh()->isGenerationQueued())->toBeFalse();
});

it('el Acervo y el Dashboard leen la marca caduca igual que la ficha', function () {
    Book::factory()->generationQueued()->create(['title' => 'Todavía espera']);
    Book::factory()->generationAbandoned()->create(['title' => 'La cola se calló']);
    Book::factory()->generationFailed()->create(['title' => 'El proveedor dijo no']);

    expect(Book::query()->awaitingGeneration()->pluck('title')->all())->toBe(['Todavía espera'])
        ->and(Book::query()->withFailedGeneration()->pluck('title')->sort()->values()->all())
        ->toBe(['El proveedor dijo no', 'La cola se calló']);

    $workload = Workload::read();

    $counter = fn (string $key) => collect($workload->pending())->firstWhere('key', $key)?->total;

    expect($counter('generation-queued'))->toBe(1)
        ->and($counter('generation-failed'))->toBe(2);
});

// Un Book que nunca se le pidió no está esperando nada.

it('el Book que nunca se encoló dice eso, y no que está en la cola', function () {
    AiSetting::choose('gemini', 'gemini-3.6-flash');

    $book = Book::factory()->withoutDescription()->create([
        'categories_ai_model' => null,
        'categories_provider' => null,
    ]);

    expect($book->descriptionStatus())->toBe(GenerationStatus::NotAsked);

    failureSheetOf($book)
        ->assertSee('Todavía no se le ha pedido a la IA escribirla')
        ->assertDontSee('Está en la cola');
});

it('sin AiModel elegido la Description dice que falta elegirlo, y ofrece la pantalla', function () {
    $book = Book::factory()->withoutDescription()->create();

    expect($book->descriptionStatus())->toBe(GenerationStatus::Off);

    failureSheetOf($book)
        ->assertSee('Falta elegir el modelo para escribirla')
        ->assertSeeHtml('data-test="description-choose-ai-model"');
});

// Donde se anuncia el problema se ofrece la salida.

it('se reintenta la Description desde su propia sección, sin línea de comandos', function () {
    Queue::fake();
    AiSetting::choose('gemini', 'gemini-3.6-flash');

    $book = Book::factory()->withoutDescription()->generationFailed(
        'The model is overloaded.',
        'gemini',
        'gemini-3.6-flash',
    )->create();

    failureSheetOf($book)
        ->assertSeeHtml('data-test="regenerate-description"')
        ->call('regenerateDescription')
        ->assertSet('descriptionStatus', GenerationStatus::Pending)
        ->assertSee('volvió a la cola');

    expect($book->fresh()->generation_failed_at)->toBeNull();

    Queue::assertPushed(
        ProposeForBook::class,
        fn (ProposeForBook $job) => $job->book->is($book)
            && $job->target === RegenerationTarget::Descriptions,
    );
});

it('la Description que ninguna regeneración toca no ofrece reintentarla', function () {
    AiSetting::choose('gemini', 'gemini-3.6-flash');

    // La mía, y la que la Fuente trajo en español: el fallo era de las
    // Categorías, y esta sección no tiene por qué reclamarlo (ADR-007).
    $mine = Book::factory()->descriptionFromOwner()->generationFailed()->create();

    expect($mine->descriptionStatus())->toBe(GenerationStatus::Generated);

    failureSheetOf($mine)
        ->assertDontSeeHtml('data-test="regenerate-description"')
        ->assertSee('Ninguna regeneración la toca');
});

it('la Description en otro idioma que nunca se tradujo ofrece pedirla', function () {
    AiSetting::choose('gemini', 'gemini-3.6-flash');

    $book = Book::factory()->descriptionInAnotherLanguage()->create();

    expect($book->descriptionStatus())->toBe(GenerationStatus::NotAsked);

    failureSheetOf($book)
        ->assertSee('Todavía no se le ha pedido a la IA traducirla')
        ->assertSeeHtml('data-test="regenerate-description"');
});
