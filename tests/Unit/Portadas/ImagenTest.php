<?php

use App\Portadas\Imagen;
use App\Portadas\ImagenInvalida;

it('lee las medidas y el formato de la imagen', function () {
    $imagen = Imagen::desde(imagenDePrueba(400, 600));

    expect($imagen->ancho)->toBe(400)
        ->and($imagen->alto)->toBe(600)
        ->and($imagen->extension())->toBe('jpg');
});

it('reconoce los formatos en los que llegan las Portadas', function (string $formato, string $extension) {
    expect(Imagen::desde(imagenDePrueba(120, 180, $formato))->extension())->toBe($extension);
})->with([
    ['jpeg', 'jpg'],
    ['png', 'png'],
    ['gif', 'gif'],
    ['webp', 'webp'],
]);

it('rechaza lo que no es una imagen', function () {
    Imagen::desde('<html><body>404 Not Found</body></html>');
})->throws(ImagenInvalida::class);

it('reduce la imagen al ancho pedido, conservando la proporción', function () {
    $reducida = Imagen::desde(imagenDePrueba(800, 1200))->reducida(320);

    $medidas = getimagesizefromstring($reducida);

    expect($medidas[0])->toBe(320)
        ->and($medidas[1])->toBe(480)
        ->and($medidas[2])->toBe(IMAGETYPE_JPEG);
});

it('no agranda una imagen más angosta que el ancho pedido', function () {
    $reducida = Imagen::desde(imagenDePrueba(200, 300))->reducida(320);

    $medidas = getimagesizefromstring($reducida);

    expect($medidas[0])->toBe(200)
        ->and($medidas[1])->toBe(300);
});
