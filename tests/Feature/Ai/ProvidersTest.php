<?php

use App\Ai\ApiKeyVariables;
use App\Ai\Provider;
use App\Ai\Providers;
use Laravel\Ai\Ai;

/** @return list<string> */
function providerNames(array $providers): array
{
    return array_map(static fn (Provider $provider): string => $provider->name, $providers);
}

it('ofrece los Providers que el SDK dice que escriben texto', function () {
    $names = providerNames(Providers::all());

    expect($names)->toContain('openai', 'anthropic', 'gemini', 'azure', 'groq', 'xai', 'deepseek', 'mistral', 'ollama')
        // These are configured too and they embed, rerank or transcribe: there
        // is nothing they could write.
        ->and($names)->not->toContain('cohere', 'jina', 'voyageai', 'eleven', 'typesafe');
});

it('nombra la variable de entorno de la que cada Provider lee su llave', function () {
    expect(ApiKeyVariables::of('gemini'))->toBe('GEMINI_API_KEY')
        ->and(ApiKeyVariables::of('openai'))->toBe('OPENAI_API_KEY')
        // Not every Provider names its variable after itself, so the name is
        // read from the configuration instead of being guessed.
        ->and(ApiKeyVariables::of('azure'))->toBe('AZURE_OPENAI_API_KEY')
        ->and(ApiKeyVariables::of('bedrock'))->toBe('AWS_BEARER_TOKEN_BEDROCK');
});

it('separa los Providers con llave de los que la esperan', function () {
    config([
        'ai.providers.gemini.key' => 'llave-de-prueba',
        'ai.providers.openai.key' => null,
    ]);

    expect(providerNames(Providers::choosable()))->toContain('gemini')
        ->and(providerNames(Providers::choosable()))->not->toContain('openai')
        ->and(providerNames(Providers::waitingForKey()))->toContain('openai')
        ->and(providerNames(Providers::waitingForKey()))->not->toContain('gemini');
});

it('Ollama se puede elegir sin llave, y no espera ninguna', function () {
    // Corre en la máquina de quien lo usa y no autentica nada: la variable de
    // entorno existe en el SDK para ponerlo detrás de un proxy, no para usarlo
    // en casa.
    config(['ai.providers.ollama.key' => null]);

    expect(providerNames(Providers::choosable()))->toContain('ollama')
        ->and(providerNames(Providers::waitingForKey()))->not->toContain('ollama');

    $ollama = Providers::find('ollama');

    expect($ollama->local)->toBeTrue()
        ->and($ollama->canBeChosen())->toBeTrue()
        // No hay variable que nombrar, porque no falta ninguna.
        ->and($ollama->keyVariable)->toBeNull();
});

it('lo que condiciona a Ollama es la URL de su servidor', function () {
    config(['ai.providers.ollama.url' => 'http://localhost:11434']);

    expect(Providers::find('ollama')->url)->toBe('http://localhost:11434');

    config(['ai.providers.ollama.url' => '']);

    expect(Providers::find('ollama')->url)->toBeNull();
});

it('ningún otro Provider deja de depender de su llave', function () {
    config([
        'ai.providers.openai.key' => null,
        'ai.providers.gemini.key' => null,
        'ai.providers.anthropic.key' => null,
    ]);

    $waiting = providerNames(Providers::waitingForKey());

    expect($waiting)->toContain('openai', 'gemini', 'anthropic');

    foreach (Providers::all() as $provider) {
        if ($provider->name === 'ollama') {
            continue;
        }

        expect($provider->local)->toBeFalse()
            ->and($provider->url)->toBeNull()
            ->and($provider->canBeChosen())->toBe($provider->hasKey);
    }
});

it('los AiModels que ofrece son los que nombra el SDK, sin repetirlos', function () {
    $gemini = Providers::find('gemini');

    expect($gemini)->not->toBeNull();

    $sdk = Ai::textProvider('gemini');

    expect($gemini->modelNames())->toContain(
        $sdk->defaultTextModel(),
        $sdk->cheapestTextModel(),
        $sdk->smartestTextModel(),
    )
        // A Provider often points at the same model for two roles, and that is
        // one option and not two.
        ->and($gemini->modelNames())->toBe(array_values(array_unique($gemini->modelNames())));
});

it('el Provider no carga la llave por ningún lado', function () {
    config(['ai.providers.gemini.key' => 'esta-es-la-llave']);

    $gemini = Providers::find('gemini');

    expect(json_encode($gemini))->not->toContain('esta-es-la-llave')
        ->and(get_object_vars($gemini))->not->toHaveKey('key');
});
