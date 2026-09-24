<?php

use App\Descriptions\TextLanguage;
use App\Enums\DescriptionLanguage;

it('reconoce una Description en español', function () {
    expect(TextLanguage::of(
        'La historia de Comala, contada por sus muertos. Una novela breve que cambió lo que se podía hacer con el tiempo y con la voz en la literatura de este país.'
    ))->toBe(DescriptionLanguage::Spanish);
});

it('reconoce una Description en inglés', function () {
    expect(TextLanguage::of(
        'A widely read handbook on software construction, with hundreds of techniques that the people who write programs have found to work, and a great deal of evidence for each one of them.'
    ))->toBe(DescriptionLanguage::Other);
});

it('reconoce el inglés aunque el texto traiga un nombre propio en español', function () {
    expect(TextLanguage::of(
        'This is the story of a man from Comala who goes back to the town where his father, Pedro Páramo, once ruled over everyone in it.'
    ))->toBe(DescriptionLanguage::Other);
});

it('no llama otro idioma a un español con algún término en inglés', function () {
    expect(TextLanguage::of(
        'Un manual sobre el oficio de escribir software, con cientos de técnicas de testing y de code review que quienes programan han encontrado que funcionan, y con la evidencia de cada una.'
    ))->toBe(DescriptionLanguage::Spanish);
});

it('contesta español cuando no hay texto que juzgar', function () {
    expect(TextLanguage::of(null))->toBe(DescriptionLanguage::Spanish)
        ->and(TextLanguage::of(''))->toBe(DescriptionLanguage::Spanish)
        ->and(TextLanguage::of('   '))->toBe(DescriptionLanguage::Spanish);
});

/**
 * Sin evidencia no se manda a traducir: equivocarse hacia el español deja el
 * texto como está, y equivocarse al revés lo reescribe con un modelo.
 */
it('contesta español ante un texto demasiado corto para decidir', function () {
    expect(TextLanguage::of('Novela.'))->toBe(DescriptionLanguage::Spanish)
        ->and(TextLanguage::of('Pedro Páramo'))->toBe(DescriptionLanguage::Spanish);
});

it('no llama inglés al texto de relleno en latín con el que se escriben las pruebas', function () {
    expect(TextLanguage::of(fake()->paragraphs(3, true)))->toBe(DescriptionLanguage::Spanish);
})->repeat(20);

it('contesta lo mismo con acentos que sin ellos', function () {
    expect(TextLanguage::of('Como ganarse a la gente. Un libro sobre las relaciones entre las personas y sobre lo que hace que una conversacion funcione.'))
        ->toBe(DescriptionLanguage::Spanish);
});
