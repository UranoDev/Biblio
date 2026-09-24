<?php

use App\Covers\GeneratedCover;

/*
 * The generated Cover (ADR-008). What is measured here is what the criteria of
 * BIB-29 ask for: that the colour is always the same one, that it comes from
 * the palette of the visual direction, and that no title spills over the piece.
 */

it('le da al mismo Book siempre el mismo color', function () {
    $first = GeneratedCover::of('Pedro Páramo', ['Juan Rulfo'], '9786073106382');
    $second = GeneratedCover::of('Pedro Páramo', ['Juan Rulfo'], '9786073106382');

    expect($first->field())->toBe($second->field());
});

it('deriva el color del ISBN, no del título', function () {
    $book = GeneratedCover::of('Pedro Páramo', ['Juan Rulfo'], '9786073106382');
    $corrected = GeneratedCover::of('Pedro Páramo (edición conmemorativa)', ['Juan Rulfo'], '9786073106382');

    expect($corrected->field())->toBe($book->field());
});

it('cae en el título cuando el Book no trae ISBN, que es lo que pasa en la ManualEntry', function () {
    $first = GeneratedCover::of('Las armas secretas', ['Julio Cortázar']);
    $second = GeneratedCover::of('Las armas secretas', ['Julio Cortázar']);

    expect($first->field())->toBe($second->field())
        ->and($first->field())->toBeIn(GeneratedCover::fields());
});

it('varía el color entre Books, para que la retícula no sea un solo tono', function () {
    $fields = [];

    foreach (range(1, 60) as $number) {
        $fields[] = GeneratedCover::of("Libro {$number}", [], "978000000{$number}")->field();
    }

    expect(array_unique($fields))->toHaveCount(count(GeneratedCover::fields()));
});

it('saca los colores de la paleta de la dirección y no de un tono al azar', function (string $field) {
    // The Ink is the one that has to read over the field, and it is the token
    // of the stylesheet that is measured, not a copy of its value.
    expect(contrastRatio($field, designToken('color-ink')))->toBeGreaterThanOrEqual(4.5)
        // Every neutral of Marginalia is pushed warm; a cold field would read
        // dirty next to the paper.
        ->and(hexdec(substr($field, 1, 2)))->toBeGreaterThanOrEqual(hexdec(substr($field, 5, 2)))
        // And it has to be told apart from the paper it sits on.
        ->and(contrastRatio($field, designToken('color-paper')))->toBeGreaterThanOrEqual(1.5);
})->with(GeneratedCover::fields());

it('acomoda un título largo sin desbordarse', function () {
    $piece = GeneratedCover::of(
        'Historia verdadera de la conquista de la Nueva España y de los trabajos que en ella pasaron',
        ['Bernal Díaz del Castillo'],
    );

    expect($piece->widestLine())->toBeLessThanOrEqual(GeneratedCover::column())
        ->and($piece->titleLines())->not->toBeEmpty();
});

it('corta el título que no cabe ni en el tamaño más chico, y se nota que está cortado', function () {
    $piece = GeneratedCover::of(str_repeat('Palabra larguísima ', 40));

    $lines = $piece->titleLines();

    expect($lines)->toHaveCount(6)
        ->and(end($lines))->toEndWith('…')
        ->and($piece->widestLine())->toBeLessThanOrEqual(GeneratedCover::column());
});

it('parte una palabra sola más ancha que la columna, en vez de dejarla salirse', function () {
    $piece = GeneratedCover::of(str_repeat('Supercalifragilisticoespialidoso', 3));

    expect($piece->widestLine())->toBeLessThanOrEqual(GeneratedCover::column())
        ->and($piece->titleLines())->not->toBeEmpty();
});

it('nunca encima el título con los Authors', function (string $title, array $authors) {
    $piece = GeneratedCover::of($title, $authors);

    $lastTitleLine = count($piece->titleLines()) - 1;

    if ($piece->authorLines() === []) {
        expect($piece->titleBaseline($lastTitleLine))->toBeLessThanOrEqual(GeneratedCover::HEIGHT);

        return;
    }

    expect($piece->authorBaseline(0) - $piece->titleBaseline($lastTitleLine))
        ->toBeGreaterThanOrEqual($piece->authorsSize());
})->with([
    'corto' => ['Aura', ['Carlos Fuentes']],
    'de cuatro renglones' => ['La región más transparente del aire que respiramos todos', ['Carlos Fuentes']],
    'cortado' => [str_repeat('Palabra larguísima ', 40), ['Carlos Fuentes']],
    'con dos autores' => ['Pedro Páramo', ['Juan Rulfo', 'Jorge Luis Borges']],
    'sin autores' => ['El libro sin autor conocido', []],
]);

it('se ve bien un Book sin Authors, que es lo que deja la ManualEntry', function () {
    $piece = GeneratedCover::of('Cuaderno de bitácora', []);

    expect($piece->authorLines())->toBe([])
        ->and($piece->titleLines())->not->toBeEmpty()
        ->and($piece->label())->toBe('Portada de Cuaderno de bitácora');
});

it('deja la pieza entera dentro de sus márgenes, con la regla incluida', function (string $title) {
    $piece = GeneratedCover::of($title, ['Alguien Que Firma Con Nombre Larguísimo', 'Y Otra Persona Más']);

    expect($piece->ruleBaseline())->toBeGreaterThanOrEqual(GeneratedCover::MARGIN)
        ->and($piece->authorBaseline(count($piece->authorLines()) - 1))
        ->toBeLessThanOrEqual(GeneratedCover::HEIGHT - GeneratedCover::MARGIN);
})->with([
    'Aura',
    'La región más transparente',
    'Historia verdadera de la conquista de la Nueva España y de los trabajos que pasaron',
    'Palabra larguísima repetida muchas veces para que no quepa de ninguna manera en la pieza que se dibuja',
]);

it('no se queda sin título cuando le llega uno vacío', function () {
    $piece = GeneratedCover::of('   ', ['Alguien']);

    expect($piece->titleLines())->toBe(['Sin título']);
});

it('descarta los Authors en blanco en vez de dibujar renglones vacíos', function () {
    $piece = GeneratedCover::of('Pedro Páramo', ['Juan Rulfo', '', '   ']);

    expect($piece->authorLines())->toBe(['JUAN RULFO']);
});
