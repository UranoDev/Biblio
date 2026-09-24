<?php

use App\Ai\BookAgent;
use App\Ai\Regenerations;
use App\Ai\TranslationAgent;
use App\Enums\Origin;
use App\Enums\RegenerationTarget;
use App\Jobs\ProposeForBook;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Category;
use App\Models\Regeneration;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    AiSetting::choose('gemini', 'gemini-3.6-flash');
});

function regenerations(): Regenerations
{
    return app(Regenerations::class);
}

/** What the AI answers for every Book of the run, unless the test says otherwise. */
function aiAnswers(string $description = 'Lo que escribió la IA.'): void
{
    BookAgent::fake(fn (): array => [
        'categories' => ['Novela mexicana'],
        'description' => $description,
    ]);
}

// La corrida y a quiénes alcanza.

it('le pide a la IA lo de todos los Books cuando no se acota al modelo anterior', function () {
    Queue::fake();

    Book::factory()->count(3)->create();

    $regeneration = regenerations()->start(RegenerationTarget::Both, onlyPrevious: false);

    expect($regeneration->total)->toBe(3);

    Queue::assertPushed(ProposeForBook::class, 3);
});

it('acota la corrida a los Books que quedaron con otro AiModel', function () {
    Queue::fake();

    $old = Book::factory()->categoriesGenerated('gemini', 'gemini-2.5-flash')->create();
    Book::factory()->categoriesGenerated('gemini', 'gemini-3.6-flash')->create();

    $regeneration = regenerations()->start(RegenerationTarget::Categories, onlyPrevious: true);

    expect($regeneration->total)->toBe(1);

    Queue::assertPushed(
        ProposeForBook::class,
        fn (ProposeForBook $job): bool => $job->book->is($old),
    );
});

it('acota la corrida de Descriptions a las que escribió el AiModel anterior', function () {
    Queue::fake();

    $old = Book::factory()->descriptionFromAi('gemini', 'gemini-2.5-flash')->create();
    Book::factory()->descriptionFromAi('gemini', 'gemini-3.6-flash')->create();

    $regeneration = regenerations()->start(RegenerationTarget::Descriptions, onlyPrevious: true);

    expect($regeneration->total)->toBe(1);

    Queue::assertPushed(
        ProposeForBook::class,
        fn (ProposeForBook $job): bool => $job->book->is($old),
    );
});

it('alcanza al Book cuando cualquiera de sus dos mitades quedó en el modelo anterior', function () {
    Queue::fake();

    Book::factory()
        ->categoriesGenerated('gemini', 'gemini-3.6-flash')
        ->descriptionFromAi('gemini', 'gemini-2.5-flash')
        ->create();

    expect(regenerations()->start(RegenerationTarget::Both, onlyPrevious: true)->total)->toBe(1);
});

// Lo que cada selección toca y lo que deja quieto.

it('regenerar sólo las Categories deja la Description como estaba', function () {
    aiAnswers('Otra descripción, la de ahora.');

    $book = Book::factory()
        ->descriptionFromAi('gemini', 'gemini-2.5-flash')
        ->create(['description' => 'La que escribió la IA antes.']);

    regenerations()->start(RegenerationTarget::Categories, onlyPrevious: false);

    $book = $book->fresh();

    expect($book->description)->toBe('La que escribió la IA antes.')
        ->and($book->description_ai_model)->toBe('gemini-2.5-flash')
        ->and($book->categories->pluck('name')->all())->toBe(['Novela mexicana'])
        ->and($book->categories_ai_model)->toBe('gemini-3.6-flash');
});

it('regenerar sólo las Descriptions deja las Categories como estaban', function () {
    aiAnswers('La descripción nueva.');

    $book = Book::factory()
        ->withoutDescription()
        ->categoriesGenerated('gemini', 'gemini-2.5-flash')
        ->create();

    $book->categories()->attach(Category::factory()->named('Poesía')->create());

    regenerations()->start(RegenerationTarget::Descriptions, onlyPrevious: false);

    $book = $book->fresh();

    expect($book->categories->pluck('name')->all())->toBe(['Poesía'])
        ->and($book->categories_ai_model)->toBe('gemini-2.5-flash')
        ->and($book->description)->toBe('La descripción nueva.')
        ->and($book->description_origin)->toBe(Origin::Ai);
});

// ADR-007: la Description de la Source y la mía no se tocan nunca.

it('no le pide a la IA la Description que trajo la Source', function () {
    Queue::fake();

    Book::factory()->descriptionFromSource()->create();

    expect(regenerations()->start(RegenerationTarget::Descriptions, onlyPrevious: false)->total)->toBe(0);

    Queue::assertNothingPushed();
});

it('no le pide a la IA la Description que escribí yo', function () {
    Queue::fake();

    Book::factory()->descriptionFromOwner()->create();

    expect(regenerations()->start(RegenerationTarget::Descriptions, onlyPrevious: false)->total)->toBe(0);

    Queue::assertNothingPushed();
});

it('una corrida completa deja intacta la Description de la Source', function () {
    aiAnswers('La que se atrevió a escribir la IA.');

    $book = Book::factory()->descriptionFromSource()->create(['description' => 'La de la Fuente.']);

    regenerations()->start(RegenerationTarget::Both, onlyPrevious: false);

    $book = $book->fresh();

    expect($book->description)->toBe('La de la Fuente.')
        ->and($book->description_origin)->toBe(Origin::Source)
        ->and($book->categories->pluck('name')->all())->toBe(['Novela mexicana']);
});

it('una corrida completa deja intacta la Description mía', function () {
    aiAnswers('La que se atrevió a escribir la IA.');

    $book = Book::factory()->descriptionFromOwner()->create(['description' => 'La que escribí yo.']);

    regenerations()->start(RegenerationTarget::Both, onlyPrevious: false);

    $book = $book->fresh();

    expect($book->description)->toBe('La que escribí yo.')
        ->and($book->description_origin)->toBe(Origin::Owner)
        ->and($book->categories->pluck('name')->all())->toBe(['Novela mexicana']);
});

// Lo que se dice antes de lanzar.

it('cuenta cuántos Books toca y cuántas Descriptions deja fuera por su Origin', function () {
    Book::factory()->count(2)->withoutDescription()->create();
    Book::factory()->descriptionFromSource()->create();
    Book::factory()->descriptionFromOwner()->create();

    $plan = regenerations()->plan(RegenerationTarget::Both, onlyPrevious: false);

    expect($plan->books)->toBe(4)
        ->and($plan->kept)->toBe(2)
        ->and($plan->reachesAnything())->toBeTrue();
});

it('no cuenta Descriptions fuera cuando sólo se regeneran las Categories', function () {
    Book::factory()->descriptionFromSource()->create();

    expect(regenerations()->plan(RegenerationTarget::Categories, onlyPrevious: false)->kept)->toBe(0);
});

// Las Descriptions en otro idioma, que la corrida sí alcanza (BIB-45).

/**
 * La que vino de la Fuente en otro idioma no se cuenta como una que se queda:
 * es justo la que la corrida va a tocar.
 */
it('no cuenta como Description que se queda la que la Fuente trajo en otro idioma', function () {
    Book::factory()->descriptionInAnotherLanguage()->create();
    Book::factory()->descriptionFromOwner()->create();
    Book::factory()->descriptionFromSource()->create([
        'description' => 'La historia de Comala, contada por sus muertos, en una novela breve.',
    ]);

    $plan = regenerations()->plan(RegenerationTarget::Both, onlyPrevious: false);

    expect($plan->kept)->toBe(2)
        ->and($plan->translated)->toBe(1);
});

it('una corrida de sólo descripciones alcanza la que vino en otro idioma', function () {
    Queue::fake();

    $english = Book::factory()->descriptionInAnotherLanguage()->create();
    $spanish = Book::factory()->descriptionFromSource()->create([
        'description' => 'La historia de Comala, contada por sus muertos, en una novela breve.',
    ]);
    $mine = Book::factory()->descriptionFromOwner()->create();

    $plan = regenerations()->plan(RegenerationTarget::Descriptions, onlyPrevious: false);

    expect($plan->books)->toBe(1)
        ->and($plan->translated)->toBe(1);

    regenerations()->start(RegenerationTarget::Descriptions, onlyPrevious: false);

    Queue::assertPushed(ProposeForBook::class, 1);
    Queue::assertPushed(
        fn (ProposeForBook $job): bool => (int) $job->book->getKey() === (int) $english->getKey()
    );

    expect($spanish->fresh()->generation_queued_at)->toBeNull()
        ->and($mine->fresh()->generation_queued_at)->toBeNull();
});

it('una corrida de sólo descripciones vuelve a traducir la ya traducida', function () {
    Queue::fake();

    Book::factory()->descriptionTranslated()->create();

    expect(regenerations()->plan(RegenerationTarget::Descriptions, onlyPrevious: false)->books)->toBe(1);

    regenerations()->start(RegenerationTarget::Descriptions, onlyPrevious: false);

    Queue::assertPushed(ProposeForBook::class, 1);
});

/**
 * Los tres Books que hoy están en inglés en producción: al correr la
 * regeneración quedan en español, con su original guardado.
 */
it('deja en español las Descriptions que hoy están en otro idioma', function () {
    BookAgent::fake(fn (): array => ['categories' => ['Programación']]);
    TranslationAgent::fake(fn (): array => ['description' => 'La traducción al español.']);

    $english = Book::factory()->count(3)->descriptionInAnotherLanguage()->create();
    $spanish = Book::factory()->descriptionFromSource()->create([
        'description' => 'La historia de Comala, contada por sus muertos, en una novela breve.',
    ]);

    regenerations()->start(RegenerationTarget::Descriptions, onlyPrevious: false);

    foreach ($english as $book) {
        expect($book->fresh()->description)->toBe('La traducción al español.')
            ->and($book->fresh()->description_origin)->toBe(Origin::Translated)
            ->and($book->fresh()->description_original)->not->toBeNull();
    }

    expect($spanish->fresh()->description_origin)->toBe(Origin::Source);
});

// El avance, que se lee de la cola y no del navegador.

it('deja el avance en la cola, donde se vuelve a leer después de recargar', function () {
    Queue::fake();

    Book::factory()->count(3)->create();

    regenerations()->start(RegenerationTarget::Both, onlyPrevious: false);

    $regeneration = Regeneration::last();

    expect($regeneration->isRunning())->toBeTrue()
        ->and($regeneration->total)->toBe(3)
        ->and($regeneration->done())->toBe(0)
        ->and($regeneration->remaining())->toBe(3)
        ->and($regeneration->target)->toBe(RegenerationTarget::Both);
});

// Un Book que falla no detiene al resto.

it('sigue con los demás cuando uno falla, y lo deja señalado', function () {
    $failures = 0;

    BookAgent::fake(function () use (&$failures): array {
        if ($failures++ === 0) {
            throw new RuntimeException('Algo que nadie ha visto.');
        }

        return ['categories' => ['Novela mexicana']];
    });

    $books = Book::factory()->count(3)->create();

    regenerations()->start(RegenerationTarget::Categories, onlyPrevious: false);

    expect($books[0]->fresh()->generation_failed_at)->not->toBeNull()
        ->and($books[1]->fresh()->categories_ai_model)->toBe('gemini-3.6-flash')
        ->and($books[2]->fresh()->categories_ai_model)->toBe('gemini-3.6-flash')
        ->and(Regeneration::last()->failed())->toBe(1);
});

it('reintenta sólo los Books que la cola dio por perdidos', function () {
    Queue::fake();

    $lost = Book::factory()->generationFailed()->create();
    Book::factory()->count(2)->create();

    expect(regenerations()->failed())->toBe(1);

    $regeneration = regenerations()->retryFailed();

    expect($regeneration->total)->toBe(1);

    Queue::assertPushed(
        ProposeForBook::class,
        fn (ProposeForBook $job): bool => $job->book->is($lost),
    );
});

it('borra la marca de fallo al volver a poner el Book en la cola', function () {
    Queue::fake();

    $lost = Book::factory()->generationFailed()->create();

    regenerations()->retryFailed();

    expect($lost->fresh()->generation_failed_at)->toBeNull()
        ->and($lost->fresh()->generation_failure)->toBeNull();
});

// Sin AiModel elegido no hay a quién pedirle nada.

it('no lanza nada mientras no haya AiModel elegido', function () {
    Queue::fake();

    AiSetting::query()->delete();

    Book::factory()->count(2)->create();

    expect(regenerations()->start(RegenerationTarget::Both, onlyPrevious: false))->toBeNull()
        ->and(regenerations()->retryFailed())->toBeNull();

    Queue::assertNothingPushed();
});
