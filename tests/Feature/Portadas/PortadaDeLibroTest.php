<?php

use App\Jobs\DescargarPortada;
use App\Models\Libro;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake();
});

it('sube una Portada a mano', function () {
    $libro = Libro::factory()->sinPortada()->create();

    Livewire::test('portada-de-libro', ['libro' => $libro])
        ->set('archivo', UploadedFile::fake()->image('portada.jpg', 400, 600))
        ->call('guardar')
        ->assertHasNoErrors();

    $libro->refresh();

    expect($libro->tienePortada())->toBeTrue();

    Storage::disk()->assertExists($libro->portada_ruta);
    Storage::disk()->assertExists($libro->portada_miniatura_ruta);
});

it('reemplaza la Portada que ya tenía', function () {
    $libro = Libro::factory()->sinPortada()->create();

    Livewire::test('portada-de-libro', ['libro' => $libro])
        ->set('archivo', UploadedFile::fake()->image('primera.jpg', 400, 600))
        ->call('guardar');

    $primera = $libro->fresh()->portada_ruta;

    Livewire::test('portada-de-libro', ['libro' => $libro->fresh()])
        ->set('archivo', UploadedFile::fake()->image('segunda.jpg', 500, 750))
        ->call('guardar');

    expect($libro->fresh()->portada_ruta)->not->toBe($primera);

    Storage::disk()->assertMissing($primera);
});

it('no acepta un archivo que no es imagen', function () {
    $libro = Libro::factory()->sinPortada()->create();

    Livewire::test('portada-de-libro', ['libro' => $libro])
        ->set('archivo', UploadedFile::fake()->create('contrato.pdf', 12, 'application/pdf'))
        ->call('guardar')
        ->assertHasErrors('archivo');

    expect($libro->fresh()->tienePortada())->toBeFalse();
});

it('pone la descarga en cola otra vez cuando falló', function () {
    Queue::fake();

    $libro = Libro::factory()->portadaPendiente()->create();

    Livewire::test('portada-de-libro', ['libro' => $libro])
        ->call('reintentar')
        ->assertSet('aviso', 'La descarga de la Portada quedó en cola.');

    Queue::assertPushed(DescargarPortada::class);
});

it('avisa que no hay de dónde bajar la Portada', function () {
    Queue::fake();

    $libro = Libro::factory()->sinPortada()->create();

    Livewire::test('portada-de-libro', ['libro' => $libro])
        ->call('reintentar')
        ->assertSet('aviso', 'Este Libro no tiene de dónde bajar la Portada.');

    Queue::assertNothingPushed();
});

it('ofrece reintentar sólo cuando la descarga puede repetirse', function () {
    Livewire::test('portada-de-libro', ['libro' => Libro::factory()->portadaPendiente()->create()])
        ->assertSee('Reintentar la descarga')
        ->assertSee('Subir una Portada');

    Livewire::test('portada-de-libro', ['libro' => Libro::factory()->sinPortada()->create()])
        ->assertDontSee('Reintentar la descarga')
        ->assertSee('Este Libro no tiene Portada');
});

it('muestra el título del Libro en lugar de una imagen rota', function () {
    $libro = Libro::factory()->sinPortada()->create(['titulo' => 'El coronel no tiene quien le escriba']);

    $this->blade('<x-portada :libro="$libro" />', ['libro' => $libro])
        ->assertSee('El coronel no tiene quien le escriba')
        ->assertDontSee('<img', false);
});

it('muestra la miniatura cuando el Libro tiene Portada', function () {
    $libro = Libro::factory()->create([
        'portada_ruta' => 'portadas/9/abc.jpg',
        'portada_miniatura_ruta' => 'portadas/9/abc-miniatura.jpg',
    ]);

    $this->blade('<x-portada :libro="$libro" />', ['libro' => $libro])
        ->assertSee('abc-miniatura.jpg', false);

    $this->blade('<x-portada :libro="$libro" tamano="completa" />', ['libro' => $libro])
        ->assertSee('portadas/9/abc.jpg', false);
});
