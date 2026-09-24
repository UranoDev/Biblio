<?php

use App\Ai\BookAgent;
use App\Ai\Proposals;
use App\Ai\Providers;
use App\Jobs\ProposeForBook;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
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

    // Ollama corre en la computadora de quien lo usa, y la pantalla le pregunta
    // si está encendido en vez de buscarle una llave (BIB-27). Aquí contesta
    // que sí; la prueba que lo apaga baja la bandera antes de abrir la pantalla.
    $this->ollamaRunning = true;

    Http::fake(function () {
        if ($this->ollamaRunning !== true) {
            throw new ConnectionException('El servidor de Ollama no responde.');
        }

        return Http::response('Ollama is running');
    });

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

    Queue::assertPushed(
        ProposeForBook::class,
        fn (ProposeForBook $job): bool => $job->provider === 'anthropic' && $job->aiModel === $model,
    );
});

it('no ofrece un Provider que sigue esperando su llave', function () {
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

    Queue::assertNotPushed(ProposeForBook::class);
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

it('borra el reclamo del modelo en cuanto se elige uno', function () {
    // La secuencia que lo delató: cambiar de Proveedor vacía el modelo, guardar
    // reclama que falta, y el reclamo se quedaba en pantalla señalando un radio
    // que ya estaba marcado.
    screen()
        ->set('provider', 'anthropic')
        ->call('save')
        ->assertHasErrors('model')
        ->set('model', namedModel('anthropic'))
        ->assertHasNoErrors();
});

/*
|--------------------------------------------------------------------------
| Ollama, el que corre en tu computadora (BIB-27)
|--------------------------------------------------------------------------
|
| Es el único de la lista que no depende de un servicio de afuera: su servidor
| escucha en la red local y no autentica nada, así que no lee ninguna llave. Lo
| que sí lo condiciona es que haya algo encendido en la URL configurada, y eso
| es lo que la pantalla le pregunta.
|
*/

it('ofrece a Ollama sin exigirle llave', function () {
    config(['ai.providers.ollama.key' => null]);

    screen()->assertSeeHtml('data-test="provider-ollama"');
});

it('no mete a Ollama en la lista de los que esperan su llave', function () {
    config(['ai.providers.ollama.key' => null]);

    screen()
        ->assertDontSee('OLLAMA_API_KEY')
        ->assertDontSee('Ollama — falta');
});

it('dice en una línea que Ollama corre en tu computadora, con su URL', function () {
    config(['ai.providers.ollama.url' => 'http://localhost:11434']);

    screen()
        ->assertSee('Corre en tu computadora en http://localhost:11434')
        ->assertSee('no necesita llave');
});

it('dice que el servidor no responde, y lo deja elegir igual', function () {
    $this->ollamaRunning = false;

    screen()
        ->assertSee('El servidor no responde ahí')
        ->assertSee('puedes elegirlo ahora y encenderlo después')
        // Y nunca le reclama una llave, que es lo que no le falta.
        ->assertDontSee('OLLAMA_API_KEY')
        ->assertSeeHtml('data-test="provider-ollama"');
});

it('guarda a Ollama aunque su servidor esté apagado', function () {
    $this->ollamaRunning = false;

    choose('ollama')->assertHasNoErrors();

    expect(AiSetting::chosen()->provider)->toBe('ollama');
});

it('avisa que el servidor de Ollama no responde cuando es el elegido', function () {
    AiSetting::choose('ollama', namedModel('ollama'));

    $this->ollamaRunning = false;

    screen()
        ->assertSeeHtml('data-test="chosen-server-down"')
        ->assertSee('El servidor no responde en http://localhost:11434')
        ->assertDontSee('Falta su llave en el entorno')
        ->assertDontSeeHtml('data-test="chosen-without-key"');
});

it('no avisa nada cuando Ollama es el elegido y su servidor contesta', function () {
    AiSetting::choose('ollama', namedModel('ollama'));

    screen()
        ->assertDontSeeHtml('data-test="chosen-server-down"')
        ->assertDontSeeHtml('data-test="chosen-without-key"');
});
