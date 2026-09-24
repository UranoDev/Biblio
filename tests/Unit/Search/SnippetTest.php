<?php

use App\Search\Snippet;

/** The words as the Search hands them over: ya plegadas. */
function searched(string ...$words): array
{
    return array_values($words);
}

it('marca la palabra buscada y deja el resto sin marcar', function () {
    $fragments = Snippet::of('Vine a Comala porque me dijeron que acá vivía mi padre.', searched('comala'))
        ->fragments();

    expect(array_map(fn ($piece) => [$piece->text, $piece->found], $fragments))->toBe([
        ['Vine a ', false],
        ['Comala', true],
        [' porque me dijeron que acá vivía mi padre.', false],
    ]);
});

it('marca la palabra aunque el acento no coincida', function () {
    $fragments = Snippet::of('Vine a Comala porque me dijeron que acá vivía mi padre.', searched('aca'))
        ->fragments();

    $marked = array_values(array_filter($fragments, fn ($piece) => $piece->found));

    expect($marked)->toHaveCount(1)
        ->and($marked[0]->text)->toBe('acá');
});

it('marca todas las veces que aparece la palabra', function () {
    $fragments = Snippet::of('Comala, y otra vez Comala.', searched('comala'))->fragments();

    expect(array_filter($fragments, fn ($piece) => $piece->found))->toHaveCount(2);
});

it('marca las dos palabras buscadas', function () {
    $fragments = Snippet::of('Vine a Comala a buscar a mi padre.', searched('comala', 'padre'))
        ->fragments();

    expect(array_map(fn ($piece) => $piece->text, array_values(array_filter($fragments, fn ($piece) => $piece->found))))
        ->toBe(['Comala', 'padre']);
});

it('no anida una marca dentro de otra cuando las palabras se traslapan', function () {
    $fragments = Snippet::of('Las citas de un libro.', searched('cita', 'citas'))->fragments();

    $marked = array_values(array_filter($fragments, fn ($piece) => $piece->found));

    expect($marked)->toHaveCount(1)
        ->and($marked[0]->text)->toBe('citas');
});

it('recorta alrededor de lo encontrado y avisa con puntos suspensivos', function () {
    $text = str_repeat('palabra de relleno. ', 40).'Comala.'.str_repeat(' palabra de relleno.', 40);

    $fragments = Snippet::of($text, searched('comala'))->fragments();

    $whole = implode('', array_map(fn ($piece) => $piece->text, $fragments));

    expect($whole)->toStartWith('…')
        ->and($whole)->toEndWith('…')
        ->and($whole)->toContain('Comala')
        ->and(mb_strlen($whole))->toBeLessThan(mb_strlen($text));
});

it('muestra entera la cita que cabe', function () {
    $text = 'Nada puede durar tanto tiempo.';

    $fragments = Snippet::of($text, searched('durar'))->fragments();

    expect(implode('', array_map(fn ($piece) => $piece->text, $fragments)))->toBe($text);
});

it('junta los renglones de la cita en uno solo', function () {
    $fragments = Snippet::of("Primer verso\nSegundo verso", searched('segundo'))->fragments();

    expect(implode('', array_map(fn ($piece) => $piece->text, $fragments)))
        ->toBe('Primer verso Segundo verso');
});

it('devuelve el principio del texto cuando nada coincide', function () {
    $fragments = Snippet::of('Vine a Comala.', searched('otra'))->fragments();

    expect($fragments)->toHaveCount(1)
        ->and($fragments[0]->text)->toBe('Vine a Comala.')
        ->and($fragments[0]->found)->toBeFalse();
});
