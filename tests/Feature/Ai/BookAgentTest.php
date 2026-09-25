<?php

use App\Ai\BookAgent;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

it('siempre pide una lista de Categories con salida estructurada', function () {
    $schema = (new BookAgent)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('categories');

    $categories = $schema['categories']->toArray();

    expect($categories['type'])->toBe('array')
        ->and($categories['items']['type'])->toBe('string');
});

it('pide la Description sólo cuando se le pide', function () {
    expect((new BookAgent(withDescription: false))->schema(new JsonSchemaTypeFactory))
        ->not->toHaveKey('description');

    expect((new BookAgent(withDescription: true))->schema(new JsonSchemaTypeFactory))
        ->toHaveKey('description');
});

it('le pide al modelo que elija de la lista canónica de categorías', function () {
    expect((new BookAgent)->instructions())->toContain('provided list');
});
