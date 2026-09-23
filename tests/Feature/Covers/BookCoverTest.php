<?php

use App\Models\Book;
use Illuminate\Support\Facades\Storage;

it('arma la URL de la Portada con el disco configurado', function () {
    Storage::fake();

    $book = Book::factory()->create([
        'cover_path' => 'covers/7/abc.jpg',
        'cover_thumbnail_path' => 'covers/7/abc-thumbnail.jpg',
    ]);

    expect($book->coverUrl())->toBe(Storage::disk()->url('covers/7/abc.jpg'))
        ->and($book->coverThumbnailUrl())->toBe(Storage::disk()->url('covers/7/abc-thumbnail.jpg'));
});

it('no inventa una URL cuando el Libro no tiene Portada', function () {
    $book = Book::factory()->withoutCover()->create();

    expect($book->hasCover())->toBeFalse()
        ->and($book->coverUrl())->toBeNull()
        ->and($book->coverThumbnailUrl())->toBeNull();
});

it('cae en la original cuando falta la versión reducida', function () {
    Storage::fake();

    $book = Book::factory()->create([
        'cover_path' => 'covers/7/abc.jpg',
        'cover_thumbnail_path' => null,
    ]);

    expect($book->coverThumbnailUrl())->toBe($book->coverUrl());
});

it('sólo se puede reintentar el que no tiene Portada y sí tiene de dónde bajarla', function () {
    expect(Book::factory()->coverPending()->create()->canRetryCover())->toBeTrue()
        ->and(Book::factory()->withoutCover()->create()->canRetryCover())->toBeFalse()
        ->and(Book::factory()->create()->canRetryCover())->toBeFalse();
});

it('lista los Libros a los que les falta la Portada', function () {
    Book::factory()->count(2)->create();
    Book::factory()->coverPending()->create();
    Book::factory()->withoutCover()->create();

    expect(Book::query()->withoutCover()->count())->toBe(2);
});
