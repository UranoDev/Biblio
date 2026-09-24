<?php

use App\Ai\Providers;
use App\Models\AiSetting;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The AiModel screen, one request per click
|--------------------------------------------------------------------------
|
| The tests in AiModelScreenTest drive the component directly, which exercises
| the logic but says nothing about what survives between one request and the
| next. Choosing a Provider and choosing an AiModel are two separate clicks, and
| between them the whole component is serialised, sent to the browser, and
| rebuilt from what comes back.
|
| These tests go through Livewire's own update endpoint, one POST per click,
| carrying the snapshot the previous response handed back — the same route the
| browser uses. What breaks only across requests shows up here and nowhere else.
|
*/

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

/** One of the AiModels the Provider names, which is what the screen offers. */
function offeredModel(string $provider, int $position = 0): string
{
    return Providers::find($provider)->modelNames()[$position];
}

/**
 * The screen as the browser first receives it: the rendered page, and the
 * snapshot every later request has to carry.
 *
 * @return array{snapshot: string, html: string}
 */
function screenAsLoaded(): array
{
    $html = (string) test()->get(route('ai-model.edit'))->assertOk()->getContent();

    preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);

    return [
        'snapshot' => html_entity_decode($matches[1], ENT_QUOTES),
        'html' => $html,
    ];
}

/**
 * One request to the screen, with the snapshot the previous one returned.
 *
 * `$updates` is what a `wire:model.live` control commits — choosing a Provider,
 * choosing an AiModel — and `$method` is a form being submitted.
 *
 * @param  array<string, mixed>  $updates
 * @return array{snapshot: string, data: array<string, mixed>, errors: array<string, list<string>>, html: string}
 */
function screenRequest(string $snapshot, array $updates = [], ?string $method = null): array
{
    $response = test()
        ->withHeaders(['X-Livewire' => 'true'])
        ->postJson(Livewire::getUpdateUri(), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => $updates,
                'calls' => $method === null
                    ? []
                    : [['path' => '', 'method' => $method, 'params' => []]],
            ]],
        ]);

    $response->assertOk();

    /** @var array{snapshot: string, effects: array{html?: string}} $component */
    $component = $response->json('components.0');

    /** @var array{data: array<string, mixed>, memo: array{errors?: array<string, list<string>>}} $decoded */
    $decoded = json_decode($component['snapshot'], associative: true);

    return [
        'snapshot' => $component['snapshot'],
        'data' => $decoded['data'],
        'errors' => $decoded['memo']['errors'] ?? [],
        'html' => $component['effects']['html'] ?? '',
    ];
}

/** The opening tags of the radio groups, in the order the screen draws them. */
function radioGroups(string $html): array
{
    preg_match_all('/<ui-radio-group[^>]*>/', $html, $matches);

    return $matches[0];
}

/** The keys the radios of a group carry, read off the markup. */
function radioKeys(string $html, string $prefix): array
{
    preg_match_all('/wire:key="('.preg_quote($prefix, '/').'[^"]*)"/', $html, $matches);

    return $matches[1];
}

/** What each radio of a group offers, read off the markup and not off the state. */
function radioValues(string $html, string $prefix): array
{
    preg_match_all('/data-test="'.preg_quote($prefix, '/').'([^"]+)"/', $html, $matches);

    return $matches[1];
}

it('el Provider elegido en una petición sigue elegido en la siguiente', function () {
    AiSetting::choose('gemini', offeredModel('gemini'));

    $loaded = screenAsLoaded();

    $afterProvider = screenRequest($loaded['snapshot'], ['provider' => 'anthropic']);

    expect($afterProvider['data']['provider'])->toBe('anthropic');

    // The request that matters: the next one, built from the snapshot the
    // previous response handed back.
    $next = screenRequest($afterProvider['snapshot']);

    expect($next['data']['provider'])->toBe('anthropic');
});

it('elegir Provider, elegir modelo y guardar, cada paso en su propia petición', function () {
    AiSetting::choose('gemini', offeredModel('gemini'));

    $model = offeredModel('anthropic');

    $loaded = screenAsLoaded();
    $afterProvider = screenRequest($loaded['snapshot'], ['provider' => 'anthropic']);
    $afterModel = screenRequest($afterProvider['snapshot'], ['model' => $model]);
    $afterSave = screenRequest($afterModel['snapshot'], method: 'save');

    expect($afterSave['errors'])->toBe([]);

    $chosen = AiSetting::chosen();

    expect($chosen->provider)->toBe('anthropic')
        ->and($chosen->model)->toBe($model);

    // And the screen says so without being reloaded.
    expect($afterSave['html'])->toContain('Anthropic')
        ->toContain($model);
});

it('cambiar de Provider y guardar sin elegir modelo no guarda el Provider anterior', function () {
    // Choosing a Provider empties the AiModel, so saving right afterwards has
    // nothing to save. What it must never do is save the previous pair and
    // report it as done: the screen would be saying it saved something the
    // person did not choose.
    $before = offeredModel('gemini');

    AiSetting::choose('gemini', $before);

    $loaded = screenAsLoaded();
    $afterProvider = screenRequest($loaded['snapshot'], ['provider' => 'anthropic']);
    $afterSave = screenRequest($afterProvider['snapshot'], method: 'save');

    expect($afterSave['errors'])->toHaveKey('model');

    $chosen = AiSetting::chosen();

    expect($chosen->provider)->toBe('gemini')
        ->and($chosen->model)->toBe($before);

    // Nothing was saved, so nothing may announce a saving.
    expect($afterSave['html'])->not->toContain('data-test="notice"');
});

it('cambiar de modelo dentro del mismo Provider, en peticiones separadas', function () {
    AiSetting::choose('gemini', offeredModel('gemini'));

    $other = offeredModel('gemini', 1);

    $loaded = screenAsLoaded();
    $afterModel = screenRequest($loaded['snapshot'], ['model' => $other]);
    $afterSave = screenRequest($afterModel['snapshot'], method: 'save');

    expect($afterSave['errors'])->toBe([]);

    $chosen = AiSetting::chosen();

    expect($chosen->provider)->toBe('gemini')
        ->and($chosen->model)->toBe($other);
});

it('escribir el nombre de otro modelo, en peticiones separadas', function () {
    AiSetting::choose('gemini', offeredModel('gemini'));

    $other = Livewire::test('pages::settings.ai-model')->instance()::OTHER;

    $loaded = screenAsLoaded();
    $afterProvider = screenRequest($loaded['snapshot'], ['provider' => 'anthropic']);
    $afterModel = screenRequest($afterProvider['snapshot'], ['model' => $other]);
    $afterName = screenRequest($afterModel['snapshot'], ['otherModel' => 'claude-9.9-experimental']);
    $afterSave = screenRequest($afterName['snapshot'], method: 'save');

    expect($afterSave['errors'])->toBe([]);

    $chosen = AiSetting::chosen();

    expect($chosen->provider)->toBe('anthropic')
        ->and($chosen->model)->toBe('claude-9.9-experimental');
});

it('la lista de modelos que se dibuja es la del Provider elegido', function () {
    // The complaint said the AiModel is not one the Provider names. This is the
    // other half of that sentence: what the screen offers after the Provider
    // changes has to be what the saving accepts.
    AiSetting::choose('gemini', offeredModel('gemini'));

    $loaded = screenAsLoaded();
    $afterProvider = screenRequest($loaded['snapshot'], ['provider' => 'anthropic']);

    $offered = array_diff(radioValues($afterProvider['html'], 'model-'), ['other']);

    expect(array_values($offered))->toBe(Providers::find('anthropic')->modelNames());
});

it('cada grupo de radios lleva su propia propiedad y ninguno comparte name', function () {
    // Two groups of radios with the same `name` are a single group for the
    // browser, and marking one unmarks the other. It does not show from the
    // state of the component, so the test reads the markup.
    AiSetting::choose('gemini', offeredModel('gemini'));

    $groups = radioGroups(screenAsLoaded()['html']);

    expect($groups)->toHaveCount(2)
        ->and($groups[0])->toContain('wire:model.live="provider"')
        ->and($groups[1])->toContain('wire:model.live="model"');

    foreach ($groups as $group) {
        expect($group)->not->toMatch('/(?<![-\w])name="/');
    }
});

it('los radios del Provider y los del modelo ofrecen cosas distintas', function () {
    AiSetting::choose('gemini', offeredModel('gemini'));

    $html = screenAsLoaded()['html'];

    expect(radioValues($html, 'provider-'))->toContain('gemini', 'anthropic')
        ->and(radioValues($html, 'model-'))->toContain(offeredModel('gemini'));
});

it('cada radio del modelo lleva una llave propia del Provider', function () {
    // A radio reads its value once, when the browser creates it, and never
    // looks at that attribute again. Without a key that changes with the
    // Provider, the list would be rewritten in place: the names of the new
    // Provider on screen, the names of the previous one on the way back.
    AiSetting::choose('gemini', offeredModel('gemini'));

    $loaded = screenAsLoaded();
    $afterProvider = screenRequest($loaded['snapshot'], ['provider' => 'anthropic']);

    $before = radioKeys($loaded['html'], 'model-');
    $after = radioKeys($afterProvider['html'], 'model-');

    expect($before)->not->toBeEmpty()
        ->and($after)->not->toBeEmpty();

    // The only key the two lists share is the one for «Otro», which is the same
    // option under every Provider.
    expect(array_values(array_intersect($before, $after)))->toBe(['model-other']);
});

it('cada radio del Provider lleva su llave', function () {
    AiSetting::choose('gemini', offeredModel('gemini'));

    expect(radioKeys(screenAsLoaded()['html'], 'provider-'))
        ->toContain('provider-gemini', 'provider-anthropic');
});
