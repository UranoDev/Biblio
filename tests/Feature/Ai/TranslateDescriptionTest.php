<?php

use App\Ai\BookAgent;
use App\Ai\Proposals;
use App\Ai\TranslationAgent;
use App\Ai\UnusableAnswer;
use App\Enums\DescriptionLanguage;
use App\Enums\Origin;
use App\Enums\RegenerationTarget;
use App\Models\AiSetting;
use App\Models\Book;

beforeEach(function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');
});

function translations(): Proposals
{
    return app(Proposals::class);
}

/** La Description de Code complete, tal como la trae el catálogo. */
function englishDescription(): string
{
    return 'A practical handbook of software construction, with hundreds of techniques '
        .'that the people who write programs have found to work, and with the evidence for each one of them.';
}

it('traduce al español la Description que la Source trajo en inglés', function () {
    BookAgent::fake([['categories' => ['Programación']]]);
    TranslationAgent::fake([['description' => 'Un manual práctico de construcción de software.']]);

    $book = Book::factory()->descriptionInAnotherLanguage(englishDescription())->create();

    translations()->queueFor($book);

    $book->refresh();

    expect($book->description)->toBe('Un manual práctico de construcción de software.')
        ->and($book->description_origin)->toBe(Origin::Translated)
        ->and($book->description_language)->toBe(DescriptionLanguage::Spanish);
});

it('conserva el texto original', function () {
    BookAgent::fake([['categories' => ['Programación']]]);
    TranslationAgent::fake([['description' => 'Un manual práctico de construcción de software.']]);

    $book = Book::factory()->descriptionInAnotherLanguage(englishDescription())->create();

    translations()->queueFor($book);

    expect($book->fresh()->description_original)->toBe(englishDescription());
});

it('deja anotado qué modelo la tradujo', function () {
    BookAgent::fake([['categories' => ['Programación']]]);
    TranslationAgent::fake([['description' => 'Un manual práctico.']]);

    $book = Book::factory()->descriptionInAnotherLanguage(englishDescription())->create();

    translations()->queueFor($book);

    $book->refresh();

    expect($book->description_provider)->toBe('gemini')
        ->and($book->description_ai_model)->toBe('gemini-2.5-flash');
});

/**
 * El criterio del issue: «se usa el vocabulario de la materia», y las
 * Categories del propio Book son el contexto que lo dice.
 */
it('le pasa las Categories del Book como el vocabulario de la materia', function () {
    BookAgent::fake([['categories' => ['Programación', 'Ingeniería de software']]]);
    TranslationAgent::fake([['description' => 'Un manual práctico.']]);

    $book = Book::factory()->descriptionInAnotherLanguage(englishDescription())->create();

    translations()->queueFor($book);

    TranslationAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Programación')
        && str_contains($prompt->prompt, 'Ingeniería de software')
        && str_contains($prompt->prompt, 'vocabulary'));
});

/**
 * Las Categories que viajan en el prompt son las que la misma corrida acaba de
 * escribir, y no las que había antes de empezar: en un Book recién capturado no
 * había ninguna.
 */
it('traduce con las Categories que la misma corrida acaba de escribir', function () {
    BookAgent::fake([['categories' => ['Teología']]]);
    TranslationAgent::fake([['description' => 'Una lectura de las escrituras.']]);

    $book = Book::factory()->descriptionInAnotherLanguage(
        'This is a reading of the scriptures about what they say on money, and on what the people who wrote them meant by it.'
    )->create();

    expect($book->categories)->toBeEmpty();

    translations()->queueFor($book);

    TranslationAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Teología'));
});

it('le pide traducir y no resumir ni opinar', function () {
    expect((new TranslationAgent)->instructions())
        ->toContain('Do not summarise')
        ->toContain('do not comment')
        ->toContain('Mexico');
});

it('manda el texto entero a traducir', function () {
    BookAgent::fake([['categories' => ['Programación']]]);
    TranslationAgent::fake([['description' => 'Un manual práctico.']]);

    $book = Book::factory()->descriptionInAnotherLanguage(englishDescription())->create();

    translations()->queueFor($book);

    TranslationAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, englishDescription()));
});

it('no gasta una llamada cuando la Description ya está en español', function () {
    BookAgent::fake([['categories' => ['Novela mexicana']]]);
    TranslationAgent::fake([['description' => 'Esto no debería escribirse.']]);

    $book = Book::factory()->descriptionFromSource()->create([
        'description' => 'La historia de Comala, contada por sus muertos, en una novela que cambió lo que se podía hacer con el tiempo.',
    ]);

    translations()->queueFor($book);

    TranslationAgent::assertNotPrompted(fn () => true);

    expect($book->fresh()->description_origin)->toBe(Origin::Source);
});

it('no traduce la Description que escribí yo, en el idioma que sea', function () {
    BookAgent::fake([['categories' => ['Programación']]]);
    TranslationAgent::fake([['description' => 'Esto no debería escribirse.']]);

    $book = Book::factory()->descriptionFromOwner()->create(['description' => englishDescription()]);

    translations()->queueFor($book);

    TranslationAgent::assertNotPrompted(fn () => true);

    expect($book->fresh()->description)->toBe(englishDescription())
        ->and($book->fresh()->description_origin)->toBe(Origin::Owner);
});

it('no traduce la Description que escribió la IA: se le pidió en español', function () {
    BookAgent::fake([['categories' => ['Programación'], 'description' => 'La escribe la IA.']]);
    TranslationAgent::fake([['description' => 'Esto no debería escribirse.']]);

    $book = Book::factory()->descriptionFromAi()->create();

    translations()->queueFor($book);

    TranslationAgent::assertNotPrompted(fn () => true);
});

it('no pide la traducción cuando la regeneración es sólo de categorías', function () {
    BookAgent::fake([['categories' => ['Programación']]]);
    TranslationAgent::fake([['description' => 'Esto no debería escribirse.']]);

    $book = Book::factory()->descriptionInAnotherLanguage(englishDescription())->create();

    translations()->queueFor($book, RegenerationTarget::Categories);

    TranslationAgent::assertNotPrompted(fn () => true);

    expect($book->fresh()->description)->toBe(englishDescription());
});

/**
 * Rehacer una traducción parte siempre del original. Traducir la traducción
 * aleja el texto del catálogo una vuelta más en cada corrida.
 */
it('vuelve a traducir desde el original y no desde la traducción anterior', function () {
    BookAgent::fake([['categories' => ['Programación']]]);
    TranslationAgent::fake([['description' => 'La segunda traducción.']]);

    $book = Book::factory()->descriptionTranslated()->create();
    $original = (string) $book->description_original;

    translations()->queueFor($book, RegenerationTarget::Descriptions);

    TranslationAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, $original));

    expect($book->fresh()->description)->toBe('La segunda traducción.')
        ->and($book->fresh()->description_original)->toBe($original);
});

it('no escribe nada cuando la respuesta no trae texto', function () {
    BookAgent::fake([['categories' => ['Programación']]]);
    TranslationAgent::fake([['description' => '']]);

    $book = Book::factory()->descriptionInAnotherLanguage(englishDescription())->create();

    expect(fn () => translations()->askFor($book, 'gemini', 'gemini-2.5-flash'))
        ->toThrow(UnusableAnswer::class);

    expect($book->fresh()->description)->toBe(englishDescription())
        ->and($book->fresh()->description_origin)->toBe(Origin::Source);
});

it('la ficha sabe que la Description está esperando su traducción', function () {
    $english = Book::factory()->descriptionInAnotherLanguage(englishDescription())->create();
    $spanish = Book::factory()->descriptionFromSource()->create([
        'description' => 'La historia de Comala, contada por sus muertos, en una novela breve.',
    ]);

    expect($english->descriptionAwaitsTranslation())->toBeTrue()
        ->and($spanish->descriptionAwaitsTranslation())->toBeFalse()
        ->and(Book::factory()->withoutDescription()->create()->descriptionAwaitsTranslation())->toBeFalse();
});

it('deriva el idioma de la Description en cada escritura, venga de donde venga', function () {
    $book = Book::factory()->withoutDescription()->create();

    expect($book->description_language)->toBeNull();

    $book->writeDescription(englishDescription());

    expect($book->fresh()->description_language)->toBe(DescriptionLanguage::Other);

    $book->writeDescription('La historia de Comala, contada por sus muertos, en una novela breve que cambió todo.');

    expect($book->fresh()->description_language)->toBe(DescriptionLanguage::Spanish);

    $book->writeDescription(null);

    expect($book->fresh()->description_language)->toBeNull();
});
