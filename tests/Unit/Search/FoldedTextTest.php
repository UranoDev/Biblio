<?php

use App\Search\FoldedText;

it('baja el texto a minúsculas', function () {
    expect(FoldedText::of('Vine a COMALA'))->toBe('vine a comala');
});

it('quita los acentos', function () {
    expect(FoldedText::of('citá'))->toBe('cita')
        ->and(FoldedText::of('Pedro Páramo'))->toBe('pedro paramo')
        ->and(FoldedText::of('pingüino'))->toBe('pinguino');
});

it('deja la eñe en paz, porque es otra letra', function () {
    expect(FoldedText::of('año'))->toBe('año')
        ->and(FoldedText::of('AÑO'))->toBe('año');
});

it('devuelve un carácter por cada carácter, para que las posiciones cuadren', function () {
    $text = 'Él bebió café en Comala, año 1955.';

    expect(FoldedText::characters($text))->toHaveCount(mb_strlen($text));
});

it('parte lo que se escribió en palabras, ya plegadas', function () {
    expect(FoldedText::words('  Comala   PÁDRE '))->toBe(['comala', 'padre']);
});

it('no devuelve ninguna palabra cuando no se escribió nada', function () {
    expect(FoldedText::words('   '))->toBe([]);
});

it('escapa lo que en un patrón significaría otra cosa', function () {
    expect(FoldedText::pattern('50%'))->toBe('%50=%%')
        ->and(FoldedText::pattern('a_b'))->toBe('%a=_b%')
        ->and(FoldedText::pattern('a=b'))->toBe('%a==b%');
});
