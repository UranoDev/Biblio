<?php

/*
 * The Marginalia tokens are the single source of the visual direction, so this
 * test reads them out of the stylesheet instead of repeating their values.
 */

$root = dirname(__DIR__, 3);

// designToken(), relativeLuminance() and contrastRatio() live in tests/Pest.php:
// the generated Cover measures its fields against these same tokens.

it('guarda cada color de la dirección como token', function (string $name) {
    expect(designToken($name))->not->toBe('');
})->with([
    'color-paper',
    'color-paper-floor',
    'color-ink',
    'color-ink-muted',
    'color-rule',
    'color-divider',
    'color-cover-edge',
    'color-link',
]);

it('guarda la escala tipográfica como tokens, no como medidas sueltas', function (string $name) {
    expect(designToken($name))->not->toBe('');
})->with([
    'text-quote',
    'text-quote--line-height',
    'text-book-title',
    'text-book-title--letter-spacing',
    'text-authors',
    'text-authors--letter-spacing',
    'text-section-heading',
    'text-section-heading--letter-spacing',
]);

it('deja la tinta legible sobre el papel', function (string $ink) {
    expect(contrastRatio(designToken($ink), designToken('color-paper')))
        ->toBeGreaterThanOrEqual(4.5);
})->with(['color-ink', 'color-ink-muted', 'color-link']);

it('deja la tinta legible incluso donde el grano oscurece más el papel', function (string $ink) {
    // The grain multiplies, so it can only darken: --color-paper-floor is the
    // darkest pixel it produces. Measuring against the clean token would pass
    // for a paper nobody sees.
    expect(contrastRatio(designToken($ink), designToken('color-paper-floor')))
        ->toBeGreaterThanOrEqual(4.5);
})->with(['color-ink', 'color-ink-muted', 'color-link']);

it('sirve IBM Plex Mono por Bunny y no por un link a Google', function () use ($root) {
    $vite = (string) file_get_contents($root.'/vite.config.js');

    expect($vite)->toContain("bunny('IBM Plex Mono'")
        ->and(designToken('font-mono'))->toStartWith("'IBM Plex Mono'");

    foreach (glob($root.'/resources/views/**/*.blade.php') ?: [] as $view) {
        expect((string) file_get_contents($view))->not->toContain('fonts.googleapis.com');
    }
});
