<?php

use App\Ai\BookAgent;
use App\Ai\Proposals;
use App\Enums\GenerationStatus;
use App\Enums\Origin;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Category;

beforeEach(function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');
});

function proposals(): Proposals
{
    return app(Proposals::class);
}

it('propone las Categories de un Book a partir de su Record', function () {
    BookAgent::fake([
        ['categories' => ['Novela mexicana', 'Realismo mágico']],
    ]);

    $book = Book::factory()->create(['title' => 'Pedro Páramo', 'authors' => ['Juan Rulfo']]);

    expect(proposals()->queueFor($book))->toBeTrue();

    expect($book->fresh()->categories->pluck('name')->all())
        ->toEqualCanonicalizing(['Novela mexicana', 'Realismo mágico']);
});

it('deja anotado con qué proveedor y qué modelo se generaron', function () {
    BookAgent::fake([['categories' => ['Novela mexicana']]]);

    $book = Book::factory()->create();

    proposals()->queueFor($book);

    expect($book->fresh()->categories_provider)->toBe('gemini')
        ->and($book->fresh()->categories_ai_model)->toBe('gemini-2.5-flash');
});

it('le pasa las Categories que ya existen y le pide reutilizarlas', function () {
    Category::factory()->named('Realismo mágico')->create();
    Category::factory()->named('Novela mexicana')->create();

    BookAgent::fake([['categories' => ['Novela mexicana']]]);

    proposals()->queueFor(Book::factory()->create());

    BookAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Realismo mágico')
        && str_contains($prompt->prompt, 'Novela mexicana')
        && str_contains($prompt->prompt, 'reuse'));
});

it('reutiliza la Category que ya existe en vez de crear otra igual', function () {
    $existing = Category::factory()->named('Realismo mágico')->create();

    BookAgent::fake([['categories' => ['realismo mágico']]]);

    $book = Book::factory()->create();

    proposals()->queueFor($book);

    expect(Category::count())->toBe(1)
        ->and($book->fresh()->categories->first()->id)->toBe($existing->id);
});

it('escribe la Description cuando ninguna Source la trajo', function () {
    BookAgent::fake([
        ['categories' => ['Novela mexicana'], 'description' => 'La historia de Comala, contada por sus muertos.'],
    ]);

    $book = Book::factory()->withoutDescription()->create();

    proposals()->queueFor($book);

    $book = $book->fresh();

    expect($book->description)->toBe('La historia de Comala, contada por sus muertos.')
        ->and($book->description_origin)->toBe(Origin::Ai)
        ->and($book->description_provider)->toBe('gemini')
        ->and($book->description_ai_model)->toBe('gemini-2.5-flash');
});

it('no le pide la Description cuando la Source ya la trajo', function () {
    BookAgent::fake([['categories' => ['Novela mexicana']]]);

    proposals()->queueFor(Book::factory()->descriptionFromSource()->create());

    BookAgent::assertPrompted(fn ($prompt) => ! str_contains($prompt->prompt, 'Write one.'));
});

it('nunca pisa la Description que trajo la Source', function () {
    BookAgent::fake([
        ['categories' => ['Novela mexicana'], 'description' => 'Escrita por la IA.'],
    ]);

    $book = Book::factory()->descriptionFromSource()->create();
    $original = $book->description;

    proposals()->queueFor($book);

    $book = $book->fresh();

    expect($book->description)->toBe($original)
        ->and($book->description_origin)->toBe(Origin::Source);
});

it('nunca pisa la Description que escribí yo', function () {
    BookAgent::fake([
        ['categories' => ['Novela mexicana'], 'description' => 'Escrita por la IA.'],
    ]);

    $book = Book::factory()->descriptionFromOwner()->create();
    $original = $book->description;

    proposals()->queueFor($book);

    $book = $book->fresh();

    expect($book->description)->toBe($original)
        ->and($book->description_origin)->toBe(Origin::Owner);
});

it('vuelve a escribir la Description que ya había escrito la IA', function () {
    BookAgent::fake([
        ['categories' => ['Novela mexicana'], 'description' => 'La nueva descripción.'],
    ]);

    $book = Book::factory()->descriptionFromAi()->create();

    proposals()->queueFor($book);

    expect($book->fresh()->description)->toBe('La nueva descripción.');
});

it('no encola nada mientras no haya un AiModel elegido', function () {
    AiSetting::query()->delete();

    BookAgent::fake();

    expect(proposals()->queueFor(Book::factory()->create()))->toBeFalse();

    BookAgent::assertNeverQueued();
    BookAgent::assertNeverPrompted();
});

it('no dejal Book a medias cuando la respuesta no trae Categories', function () {
    BookAgent::fake([
        ['categories' => [], 'description' => 'Una descripción suelta.'],
    ]);

    $book = Book::factory()->withoutDescription()->create();

    proposals()->queueFor($book);

    $book = $book->fresh();

    // La mitad de una respuesta no se guarda, y el job no se queda esperando a
    // que la próxima sea distinta: falla y se ve (BIB-33).
    expect($book->categories)->toBeEmpty()
        ->and($book->categories_ai_model)->toBeNull()
        ->and($book->description)->toBeNull()
        ->and($book->categoriesStatus())->toBe(GenerationStatus::Failed);
});

it('se puede volver a encolar después de una respuesta inservible', function () {
    BookAgent::fake([
        ['categories' => []],
        ['categories' => ['Novela mexicana']],
    ]);

    $book = Book::factory()->create();

    proposals()->queueFor($book);
    proposals()->queueFor($book->fresh());

    expect($book->fresh()->categories->pluck('name')->all())->toBe(['Novela mexicana']);
});

it('distingue el Book generado, el que está en la cola y el que espera un AiModel', function () {
    $generated = Book::factory()->categoriesGenerated()->create();
    $pending = Book::factory()->create();

    expect($generated->categoriesStatus())->toBe(GenerationStatus::Generated)
        ->and($pending->categoriesStatus())->toBe(GenerationStatus::Pending);

    AiSetting::query()->delete();

    expect($pending->fresh()->categoriesStatus())->toBe(GenerationStatus::Off)
        ->and($generated->fresh()->categoriesStatus())->toBe(GenerationStatus::Generated);
});

it('no dejal Book a medias cuando el proveedor truena, y se puede reintentar', function () {
    BookAgent::fake(function () {
        throw new RuntimeException('The provider is down.');
    });

    $book = Book::factory()->withoutDescription()->create();

    proposals()->queueFor($book);

    $book = $book->fresh();

    expect($book->categories)->toBeEmpty()
        ->and($book->categories_ai_model)->toBeNull()
        ->and($book->description)->toBeNull()
        ->and($book->categoriesStatus())->toBe(GenerationStatus::Failed);

    BookAgent::fake([
        ['categories' => ['Novela mexicana'], 'description' => 'La historia de Comala.'],
    ]);

    proposals()->queueFor($book);

    $book = $book->fresh();

    expect($book->categories->pluck('name')->all())->toBe(['Novela mexicana'])
        ->and($book->description)->toBe('La historia de Comala.')
        ->and($book->categoriesStatus())->toBe(GenerationStatus::Generated);
});
