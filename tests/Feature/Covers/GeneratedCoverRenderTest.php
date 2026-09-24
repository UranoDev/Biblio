<?php

use App\Covers\GeneratedCover;
use App\Enums\CoverOrigin;
use App\Intake\AddBook;
use App\Intake\NewBook;
use App\Models\Book;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
});

it('dibuja la pieza generada cuando el Book no tiene imagen guardada', function () {
    $book = Book::factory()->coverGenerated()->create([
        'title' => 'Pedro Páramo',
        'authors' => ['Juan Rulfo'],
    ]);

    $this->blade('<x-cover :book="$book" />', ['book' => $book])
        ->assertSee('<svg', false)
        ->assertSee('Pedro Páramo', false)
        ->assertSee('JUAN RULFO', false)
        ->assertSee($book->generatedCover()->field(), false);
});

it('no escribe nada en el disco al dibujarla', function () {
    $book = Book::factory()->coverGenerated()->create();

    $this->blade('<x-cover :book="$book" />', ['book' => $book]);
    $this->blade('<x-cover :book="$book" size="full" />', ['book' => $book]);

    expect(Storage::disk()->allFiles())->toBeEmpty();
});

it('sirve para los tres tamaños con el mismo dibujo, porque escala con su viewBox', function (string $size) {
    $book = Book::factory()->coverGenerated()->create();

    $rendered = $this->blade('<x-cover :book="$book" :size="$size" />', ['book' => $book, 'size' => $size]);

    // No fixed width or height on the <svg>: it fills whatever box the grid of
    // the Shelf, the sheet or the card of a Quote gives it.
    $rendered
        ->assertSee('viewBox="0 0 400 600"', false)
        ->assertDontSee('<svg width=', false)
        ->assertSee('aspect-2/3', false);
})->with(['thumbnail', 'full']);

it('lleva el filo de un píxel, igual que cualquier otro Cover', function () {
    $book = Book::factory()->coverGenerated()->create();

    $this->blade('<x-cover :book="$book" />', ['book' => $book])
        ->assertSee('cover-edge', false);
});

it('conserva las clases que le pasa la pantalla', function () {
    $book = Book::factory()->coverGenerated()->create();

    $this->blade('<x-cover :book="$book" class="w-40 shrink-0" />', ['book' => $book])
        ->assertSee('w-40 shrink-0', false);
});

it('dice al lector de pantalla de qué Libro es la Portada', function () {
    $book = Book::factory()->coverGenerated()->create([
        'title' => 'Aura',
        'authors' => ['Carlos Fuentes'],
    ]);

    $this->blade('<x-cover :book="$book" />', ['book' => $book])
        ->assertSee('aria-label="Portada de Aura, de Carlos Fuentes"', false);
});

it('escapa el título en lugar de meterlo crudo en el dibujo', function () {
    $book = Book::factory()->coverGenerated()->create([
        'title' => 'Cuentos <script> y otros',
        'authors' => [],
    ]);

    $this->blade('<x-cover :book="$book" />', ['book' => $book])
        ->assertDontSee('<script>', false);
});

it('muestra la imagen guardada, y no la pieza, cuando el Book sí tiene una', function () {
    $book = Book::factory()->create([
        'cover_path' => 'covers/9/abc.jpg',
        'cover_thumbnail_path' => 'covers/9/abc-thumbnail.jpg',
    ]);

    $this->blade('<x-cover :book="$book" />', ['book' => $book])
        ->assertSee($book->coverThumbnailUrl(), false)
        ->assertDontSee('<svg', false);
});

it('la pieza sigue al título cuando se corrige la ficha, sin nada que limpiar', function () {
    $book = Book::factory()->coverGenerated()->create(['title' => 'Pedro Pramo']);

    $book->forceFill(['title' => 'Pedro Páramo'])->save();

    $this->blade('<x-cover :book="$book" />', ['book' => $book->fresh()])
        ->assertSee('Pedro Páramo', false);

    expect(Storage::disk()->allFiles())->toBeEmpty();
});

it('el Book nace con el Cover generado, que es un Cover como cualquiera', function () {
    $book = Book::factory()->create([
        'cover_path' => null,
        'cover_thumbnail_path' => null,
        'cover_origin' => CoverOrigin::Generated,
    ]);

    expect($book->hasGeneratedCover())->toBeTrue()
        ->and($book->generatedCover()->field())->toBeIn(GeneratedCover::fields());
});

it('el Book que entra por cualquiera de las tres vías ya trae su Cover generado', function (string $path) {
    Queue::fake();

    $add = app(AddBook::class);

    $book = match ($path) {
        'record' => $add->fromRecord(recordOf('9786073106382')),
        'candidate' => $add->fromCandidate(candidateOf('9788437604176')),
        default => $add->byHand(new NewBook(title: 'Cuaderno de bitácora')),
    };

    // Not refreshed on purpose: the Book the screen hands on right after
    // registering it has to answer the same as the one read back.
    expect($book->hasGeneratedCover())->toBeTrue()
        ->and($book->fresh()->hasGeneratedCover())->toBeTrue();
})->with(['record', 'candidate', 'manual']);
