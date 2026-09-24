<?php

use App\Enums\QuoteScale;
use App\Quotes\QuoteCard;

/*
 * The size of a Quote on its card is read off the Quote, so it is measured
 * here and not by looking at a screen.
 */

it('sube de escalón la Cita de seis palabras', function () {
    $card = QuoteCard::of('El que lee mucho y anda mucho, ve mucho y sabe mucho.');

    expect($card->scale())->toBe(QuoteScale::Large);
});

it('deja en su tamaño la Cita de cuarenta palabras', function () {
    $card = QuoteCard::of(
        'Dado lo rápido y lo impredeciblemente que cambia el panorama de la tecnología, '.
        'la ventaja competitiva de cualquier producto está en su capacidad de salir al '.
        'mercado pronto, y esa capacidad no se compra: se construye durante años y se '.
        'pierde en una sola decisión tomada con prisa.',
    );

    expect($card->scale())->toBe(QuoteScale::Regular);
});

it('baja de escalón la Cita larga, y no la recorta', function () {
    $long = str_repeat('La biblioteca de Babel es ilimitada y periódica. ', 12);

    $card = QuoteCard::of($long);

    expect($card->scale())->toBe(QuoteScale::Small)
        // Cutting the Quote is cutting the one thing the card exists to show.
        ->and($card->text())->toBe(trim($long));
});

it('cuenta los renglones que la Cita trae por su cuenta', function () {
    // Eight short lines of verse take eight lines, even though their characters
    // would fit in two.
    $verse = implode("\n", array_fill(0, 12, 'Verde que te quiero verde.'));

    expect(QuoteCard::of($verse)->lines())->toBe(12)
        ->and(QuoteCard::of($verse)->scale())->toBe(QuoteScale::Small);
});

it('cuenta como renglón el que separa dos estrofas', function () {
    expect(QuoteCard::of("Uno\n\nDos")->lines())->toBe(3);
});

it('quita las comillas con que se tecleó la Cita, porque la tarjeta pone las suyas', function (string $typed) {
    expect(QuoteCard::of($typed)->text())->toBe('Al final del camino no había nadie.');
})->with([
    '"Al final del camino no había nadie."',
    '“Al final del camino no había nadie.”',
    '«Al final del camino no había nadie.»',
    '  “Al final del camino no había nadie.”  ',
]);

it('deja en paz las comillas de adentro, que son del Libro', function () {
    $quoting = 'Y entonces dijo: “no vuelvo”, y no volvió.';

    expect(QuoteCard::of($quoting)->text())->toBe($quoting);
});

it('no se come el apóstrofo con que termina una palabra', function () {
    expect(QuoteCard::of("It was the boys' turn")->text())->toBe("It was the boys' turn");
});
