<?php

use App\Quotes\PageLabel;

it('lee una página en arábigos', function () {
    $page = PageLabel::parse('29');

    expect($page)->not->toBeNull()
        ->and($page->label())->toBe('29')
        ->and($page->isFrontMatter())->toBeFalse()
        ->and($page->number())->toBe(29);
});

it('lee una página en romanos y la reconoce como preliminar', function () {
    $page = PageLabel::parse('xiv');

    expect($page)->not->toBeNull()
        ->and($page->label())->toBe('xiv')
        ->and($page->isFrontMatter())->toBeTrue()
        ->and($page->number())->toBe(14);
});

it('conserva la etiqueta como la imprime el libro', function () {
    expect(PageLabel::parse('XIV')->label())->toBe('XIV')
        ->and(PageLabel::parse('XiV')->label())->toBe('XiV');
});

it('quita el espacio de alrededor, que es de quien escribe y no del libro', function () {
    expect(PageLabel::parse('  xiv  ')->label())->toBe('xiv')
        ->and(PageLabel::parse(' 29 ')->label())->toBe('29');
});

it('suma los romanos restando la letra que va antes de una mayor', function (string $roman, int $value) {
    expect(PageLabel::parse($roman)->number())->toBe($value);
})->with([
    ['i', 1],
    ['iv', 4],
    ['ix', 9],
    ['xi', 11],
    ['xiv', 14],
    ['xl', 40],
    ['xlii', 42],
    ['xc', 90],
    ['cd', 400],
    ['cm', 900],
    ['mcmxcix', 1999],
]);

it('rechaza lo que no es ni arábigo ni romano', function (string $value) {
    expect(PageLabel::parse($value))->toBeNull()
        ->and(PageLabel::isValid($value))->toBeFalse();
})->with([
    'una palabra' => ['siete'],
    'vacío' => [''],
    'sólo espacio' => ['   '],
    'letras que no son romanas' => ['abc'],
    'mezcla de los dos' => ['29b'],
    'un rango' => ['29-30'],
    'un signo' => ['-29'],
    'página cero' => ['0'],
    'romanos mal escritos' => ['iiii'],
    'romanos al revés' => ['xix i'],
    'demasiadas emes' => ['mmmm'],
]);

it('rechaza una página más alta que la de cualquier libro', function () {
    expect(PageLabel::parse('100000'))->not->toBeNull()
        ->and(PageLabel::parse('100001'))->toBeNull();
});
