<?php

use App\Enums\CoverSize;
use App\Models\Book;

it('arma la URL del Cover contra la ruta de la aplicación, no contra el disco', function () {
    $book = Book::factory()->create([
        'cover_path' => 'covers/7/abc.jpg',
        'cover_thumbnail_path' => 'covers/7/abc-thumbnail.jpg',
    ]);

    expect($book->coverUrl())->toBe(route('cover', ['book' => $book, 'size' => CoverSize::Full]))
        ->and($book->coverThumbnailUrl())->toBe(route('cover', ['book' => $book, 'size' => CoverSize::Thumbnail]));
});

it('no inventa una URL cuando el Cover es la pieza generada', function () {
    $book = Book::factory()->coverGenerated()->create();

    expect($book->hasCover())->toBeFalse()
        ->and($book->hasGeneratedCover())->toBeTrue()
        ->and($book->coverUrl())->toBeNull()
        ->and($book->coverThumbnailUrl())->toBeNull();
});

it('sirve la original cuando falta la versión reducida', function () {
    $book = Book::factory()->create([
        'cover_path' => 'covers/7/abc.jpg',
        'cover_thumbnail_path' => null,
    ]);

    expect($book->coverPathFor(CoverSize::Thumbnail))->toBe('covers/7/abc.jpg')
        ->and($book->coverPathFor(CoverSize::Full))->toBe('covers/7/abc.jpg');
});

it('sólo se puede reintentar el que tiene Cover generado y sí tiene de dónde bajarla', function () {
    expect(Book::factory()->coverPending()->create()->canRetryCover())->toBeTrue()
        ->and(Book::factory()->coverGenerated()->create()->canRetryCover())->toBeFalse()
        ->and(Book::factory()->create()->canRetryCover())->toBeFalse();
});

it('lista y cuenta los Books con Cover generado, que es la cola de lo que falta fotografiar', function () {
    Book::factory()->count(2)->create();
    Book::factory()->coverFromOwner()->create();
    Book::factory()->coverPending()->create();
    Book::factory()->coverGenerated()->create();

    expect(Book::query()->coverGenerated()->count())->toBe(2)
        ->and(Book::query()->coverGenerated()->get())
        ->each(fn ($book) => $book->hasGeneratedCover()->toBeTrue());
});

it('un reintento contra las Fuentes no alcanza a la foto del dueño', function () {
    expect(Book::factory()->coverGenerated()->create()->acceptsCoverFromSource())->toBeTrue()
        ->and(Book::factory()->create()->acceptsCoverFromSource())->toBeTrue()
        ->and(Book::factory()->coverFromOwner()->create()->acceptsCoverFromSource())->toBeFalse();
});
