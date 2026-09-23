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
        ->and($measurements[2])->toBe(IMAGETYPE_JPEG);
});

it('no agranda una imagen más angosta que el ancho pedido', function () {
    $scaled = Image::from(testImage(200, 300))->scaled(320);

    $measurements = getimagesizefromstring($scaled);

    expect($measurements[0])->toBe(200)
        ->and($measurements[1])->toBe(300);
});
