<?php

use App\Ai\BookAgent;
use App\Ai\Proposals;
use App\Ai\Providers;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();

    // What the environment has and what it does not, said out loud: the screen
    // reads it, and a test that depends on the local .env answers something
    // different on another machine.
    config([
        'ai.providers.gemini.key' => 'llave-de-gemini',
        'ai.providers.anthropic.key' => 'llave-de-anthropic',
        'ai.providers.openai.key' => null,
    ]);

    $this->actingAs(User::factory()->create());
});

function screen(): Testable
{
    return Livewire::test('pages::settings.ai-model');
}

/** One of the AiModels the Provider names, which is what the screen offers. */
function namedModel(string $provider = 'gemini', int $position = 0): string
{
    return Providers::find($provider)->modelNames()[$position];
}

/** Choosing the Provider and one of its AiModels, as it is done on the screen. */
function choose(string $provider = 'gemini', ?string $model = null): Testable
{
    return screen()
        ->set('provider', $provider)
        ->set('model', $model ?? namedModel($provider))
        ->call('save');
}

it('no es alcanzable sin sesión', function () {
    auth()->logout();

    $this->get(route('ai-model.edit'))->assertRedirect(route('login'));
});

it('abre desde la barra de Settings', function () {
    $this->get(route('ai-model.edit'))
        ->assertOk()
        ->assertSee('Modelo de IA');
});

it('dice que la generación está apagada mientras no haya AiModel elegido', function () {
    screen()
        ->assertSee('Todavía no hay modelo elegido')
        ->assertSee('La generación está apagada');
});

it('guarda el Provider y el AiModel elegidos', function () {
    choose('gemini')->assertHasNoErrors();

    $chosen = AiSetting::chosen();

    expect($chosen)->not->toBeNull()
        ->and($chosen->provider)->toBe('gemini')
        ->and($chosen->model)->toBe(namedModel('gemini'));
});

it('el agente escribe con el AiModel guardado y no con uno del entorno', function () {
    // What the environment would impose if the choice lived there.
    config(['ai.default' => 'openai']);

    $model = namedModel('anthropic');

    choose('anthropic', $model)->assertHasNoErrors();

    BookAgent::fake();

    app(Proposals::class)->queueFor(Book::factory()->create());

    BookAgent::assertQueued(
        fn ($prompt): bool => $prompt->provider === 'anthropic' && $prompt->model === $model
    );
});

it('sólo ofrece los Providers que tienen su llave en el entorno', function () {
    screen()
        ->assertSeeHtml('data-test="provider-gemini"')
        ->assertSeeHtml('data-test="provider-anthropic"')
        ->assertDontSeeHtml('data-test="provider-openai"');
});

it('nombra la variable que le falta al Provider sin llave', function () {
    screen()
        ->assertSee('Proveedores sin llave')
        ->assertSee('OPENAI_API_KEY');
});

it('no deja elegir un Provider sin llave', function () {
    screen()
        ->set('provider', 'openai')
        ->set('model', screen()->instance()::OTHER)
        ->set('otherModel', 'gpt-4o')
        ->call('save')
        ->assertHasErrors('provider');

    expect(AiSetting::chosen())->toBeNull();
});

it('no muestra ninguna llave, ni completa ni recortada', function () {
    config(['ai.providers.gemini.key' => 'AIzaSyEstaEsLaLlaveSecreta']);

    $screen = screen();

    $screen->assertDontSee('AIzaSyEstaEsLaLlaveSecreta')
        ->assertDontSee('AIzaSy');
});

it('deja escribir el nombre de un AiModel que el Provider no nombra', function () {
    $other = screen()->instance()::OTHER;

    screen()
        ->set('provider', 'gemini')
        ->set('model', $other)
        ->set('otherModel', 'gemini-9.9-experimental')
        ->call('save')
        ->assertHasNoErrors();

    expect(AiSetting::chosen()->model)->toBe('gemini-9.9-experimental');
});

it('pide el nombre cuando se elige escribir el AiModel y no se escribe', function () {
    screen()
        ->set('provider', 'gemini')
        ->set('model', screen()->instance()::OTHER)
        ->call('save')
        ->assertHasErrors('otherModel');
});

it('vuelve a la elección guardada al abrir la pantalla', function () {
    AiSetting::choose('anthropic', namedModel('anthropic'));

    screen()
        ->assertSet('provider', 'anthropic')
        ->assertSet('model', namedModel('anthropic'));
});

it('vuelve al AiModel escrito a mano al abrir la pantalla', function () {
    AiSetting::choose('gemini', 'gemini-9.9-experimental');

    screen()
        ->assertSet('otherModel', 'gemini-9.9-experimental')
        ->assertSeeHtml('data-test="other-model-field"');
});

it('avisa cuando el Provider elegido se quedó sin su llave', function () {
    AiSetting::choose('openai', 'gpt-4o');

    screen()
        ->assertSee('Falta su llave en el entorno')
        ->assertSee('OPENAI_API_KEY');
});

it('dice cuántos Books quedaron con el AiModel anterior', function () {
    Book::factory()->count(2)->categoriesGenerated('gemini', 'gemini-de-antes')->create();
    Book::factory()->descriptionFromAi('gemini', 'gemini-de-antes')->create();

    choose('gemini')
        ->assertSee('2 libros tienen sus categorías escritas con otro modelo')
        ->assertSee('Un libro tiene su descripción escrita con otro modelo')
        ->assertSee('Cambiar de modelo no vuelve a escribir lo que ya está escrito');
});

it('no cuenta los Books que ya están con el AiModel elegido', function () {
    $model = namedModel('gemini');

    Book::factory()->categoriesGenerated('gemini', $model)->create();

    choose('gemini', $model)->assertDontSee('libros tienen sus categorías escritas con otro modelo');
});

it('cambiar de AiModel no regenera nada por sí solo', function () {
    $book = Book::factory()->categoriesGenerated('gemini', 'gemini-de-antes')->create();

    BookAgent::fake();

    choose('gemini')->assertHasNoErrors();

    expect($book->fresh()->categories_ai_model)->toBe('gemini-de-antes');

    BookAgent::assertNeverQueued();
});

it('ofrece ir a regenerar en cuanto exista esa pantalla', function () {
    // The mass regeneration arrives with BIB-21. Until then the screen says how
    // many Books are on the previous AiModel and there is nowhere to send
    // anybody.
    Route::middleware('web')->get('regenerate', fn () => '')->name('regenerate');

    Book::factory()->categoriesGenerated('gemini', 'gemini-de-antes')->create();

    choose('gemini')->assertSee('Ir a regenerar');
});

it('no ofrece ir a regenerar cuando no hay nada de otro AiModel', function () {
    Route::middleware('web')->get('regenerate', fn () => '')->name('regenerate');

    choose('gemini')->assertDontSee('Ir a regenerar');
});
