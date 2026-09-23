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

    expect(providerNames(Providers::withKey()))->toContain('gemini')
        ->and(providerNames(Providers::withKey()))->not->toContain('openai')
        ->and(providerNames(Providers::withoutKey()))->toContain('openai')
        ->and(providerNames(Providers::withoutKey()))->not->toContain('gemini');
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
