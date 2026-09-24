<?php

/*
 * The Marginalia tokens are the single source of the visual direction, so this
 * test reads them out of the stylesheet instead of repeating their values.
 */

$root = dirname(__DIR__, 3);

/**
 * The value a token holds in resources/css/app.css.
 */
function designToken(string $name): string
{
    static $css = null;

    $css ??= (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/app.css');

    preg_match('/--'.preg_quote($name, '/').':\s*([^;]+);/', $css, $matches);

    return trim($matches[1] ?? '');
}

/**
 * The relative luminance of a colour, as WCAG 2.1 defines it.
 */
function relativeLuminance(string $hex): float
{
    /** @var list<float> $channels */
    $channels = array_map(
        fn (string $pair): float => (int) hexdec($pair) / 255,
        str_split(ltrim($hex, '#'), 2),
    );

    $linear = array_map(
        fn (float $channel): float => $channel <= 0.04045
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4,
        $channels,
    );

    return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
}

/**
 * The contrast between two colours, from 1:1 to 21:1.
 */
function contrastRatio(string $first, string $second): float
{
    $lighter = max(relativeLuminance($first), relativeLuminance($second));
    $darker = min(relativeLuminance($first), relativeLuminance($second));

    return ($lighter + 0.05) / ($darker + 0.05);
}

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
