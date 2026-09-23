<?php

use App\Models\Libro;
use Illuminate\Support\Facades\Storage;

it('arma la URL de la Portada con el disco configurado', function () {
    Storage::fake();

    $libro = Libro::factory()->create([
        'portada_ruta' => 'portadas/7/abc.jpg',
        'portada_miniatura_ruta' => 'portadas/7/abc-miniatura.jpg',
    ]);

    expect($libro->portadaUrl())->toBe(Storage::disk()->url('portadas/7/abc.jpg'))
        ->and($libro->portadaMiniaturaUrl())->toBe(Storage::disk()->url('portadas/7/abc-miniatura.jpg'));
});

it('no inventa una URL cuando el Libro no tiene Portada', function () {
    $libro = Libro::factory()->sinPortada()->create();

    expect($libro->tienePortada())->toBeFalse()
        ->and($libro->portadaUrl())->toBeNull()
        ->and($libro->portadaMiniaturaUrl())->toBeNull();
});

it('cae en la original cuando falta la versión reducida', function () {
    Storage::fake();

    $libro = Libro::factory()->create([
        'portada_ruta' => 'portadas/7/abc.jpg',
        'portada_miniatura_ruta' => null,
    ]);

    expect($libro->portadaMiniaturaUrl())->toBe($libro->portadaUrl());
});

it('sólo se puede reintentar el que no tiene Portada y sí tiene de dónde bajarla', function () {
    expect(Libro::factory()->portadaPendiente()->create()->puedeReintentarLaPortada())->toBeTrue()
        ->and(Libro::factory()->sinPortada()->create()->puedeReintentarLaPortada())->toBeFalse()
        ->and(Libro::factory()->create()->puedeReintentarLaPortada())->toBeFalse();
});

it('lista los Libros a los que les falta la Portada', function () {
    Libro::factory()->count(2)->create();
    Libro::factory()->portadaPendiente()->create();
    Libro::factory()->sinPortada()->create();

    expect(Libro::query()->sinPortada()->count())->toBe(2);
});
