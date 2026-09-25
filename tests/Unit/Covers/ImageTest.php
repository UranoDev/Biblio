<?php

use App\Covers\Image;
use App\Covers\InvalidImage;

it('lee las medidas y el formato de la imagen', function () {
    $image = Image::from(testImage(400, 600));

    expect($image->width)->toBe(400)
        ->and($image->height)->toBe(600)
        ->and($image->extension())->toBe('jpg');
});

it('reconoce los formatos en los que llegan los Covers', function (string $format, string $extension) {
    expect(Image::from(testImage(120, 180, $format))->extension())->toBe($extension);
})->with([
    ['jpeg', 'jpg'],
    ['png', 'png'],
    ['gif', 'gif'],
    ['webp', 'webp'],
]);

it('rechaza lo que no es una imagen', function () {
    Image::from('<html><body>404 Not Found</body></html>');
})->throws(InvalidImage::class);

it('reduce la imagen al ancho pedido, conservando la proporción', function () {
    $scaled = Image::from(testImage(800, 1200))->scaled(320);

    $measurements = getimagesizefromstring($scaled);

    expect($measurements[0])->toBe(320)
        ->and($measurements[1])->toBe(480)
        ->and($measurements[2])->toBe(IMAGETYPE_WEBP);
});

it('no agranda una imagen más angosta que el ancho pedido', function () {
    $scaled = Image::from(testImage(200, 300))->scaled(320);

    $measurements = getimagesizefromstring($scaled);

    expect($measurements[0])->toBe(200)
        ->and($measurements[1])->toBe(300);
});

it('mide el lado corto, que es el que decide si sirve de Portada', function () {
    expect(Image::from(testImage(400, 600))->shortestSide())->toBe(400)
        ->and(Image::from(testImage(600, 400))->shortestSide())->toBe(400);
});

it('reconoce como color plano los marcadores que devuelven los catálogos', function (string $file) {
    expect(Image::from(recordedCover($file))->isFlat())->toBeTrue();
})->with([
    'google-books-not-available.png',
    'google-books-not-available-128x170.png',
    'open-library-no-cover.gif',
]);

// El respaldo no alcanza para todos, y por eso la lista de marcadores es la vía
// principal: el mosaico rojo que Google manda en JPEG llega con suficiente ruido
// de compresión como para no medir plano.
it('no alcanza a reconocer el mosaico rojo cuando viene en JPEG', function () {
    expect(Image::from(recordedCover('google-books-not-available-128x198.jpg'))->isFlat())->toBeFalse();
});

it('no llama color plano a una imagen con contenido', function () {
    expect(Image::from(testImage(400, 600))->isFlat())->toBeFalse();
});

// Lo medido el 2026-09-24 sobre el binario real: 0.915 de color parejo —más
// que el 0.93 de un marcador y muy lejos del 0.60 que medían las portadas de
// la muestra del 2026-09-23— y 27 colores, contra los 9 del marcador más
// variado. Lo que la salva es el conteo de colores, no la proporción (BIB-53).
it('no llama color plano a una portada real de fondo minimalista', function () {
    expect(Image::from(recordedCover('open-library-minimal-cover.jpg'))->isFlat())->toBeFalse();
});

it('sigue llamando color plano a lo que además es de pocos colores', function () {
    // La misma proporción que la portada minimalista, 0.915 de fondo parejo,
    // pero dibujada con dos colores: eso es la forma de un marcador.
    expect(Image::from(mostlyFlatImage(386, 500, 0.915, 2))->isFlat())->toBeTrue();
});

it('no llama color plano a lo que tiene esa misma proporción y muchos colores', function () {
    expect(Image::from(mostlyFlatImage(386, 500, 0.915, 40))->isFlat())->toBeFalse();
});
