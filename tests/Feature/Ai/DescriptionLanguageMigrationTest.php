<?php

use App\Enums\DescriptionLanguage;
use App\Enums\Origin;
use App\Models\Book;
use Illuminate\Support\Facades\DB;

/*
 * The Description started saying which language it is in (BIB-45). The Books
 * that were already registered have to get that answer too, or the three that
 * are in English today would never enter a regeneration: each test here puts
 * the old schema back, writes a Book the way it used to be written, and
 * migrates forward again.
 */
const DESCRIPTION_LANGUAGE_MIGRATION = 'database/migrations/2026_09_24_110000_add_description_language_to_books_table.php';

/** A Book written straight into the table, before the column existed. */
function bookWrittenBefore(string $title, ?string $description): int
{
    return DB::table('books')->insertGetId([
        'title' => $title,
        'authors' => json_encode(['Quien sea']),
        'description' => $description,
        'description_origin' => $description === null ? null : Origin::Source->value,
        'source' => 'open_library',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('le pone su idioma a las Descriptions ya capturadas', function () {
    $this->artisan('migrate:rollback', ['--path' => DESCRIPTION_LANGUAGE_MIGRATION, '--realpath' => false])
        ->assertSuccessful();

    $english = bookWrittenBefore(
        'Code complete',
        'A practical handbook of software construction, with hundreds of techniques that the people who write programs have found to work.'
    );

    $spanish = bookWrittenBefore(
        'Cómo ganarse a la gente',
        'Un libro sobre las relaciones entre las personas, y sobre lo que hace que una conversación llegue a alguna parte.'
    );

    $none = bookWrittenBefore('Plan B', null);

    $this->artisan('migrate', ['--path' => DESCRIPTION_LANGUAGE_MIGRATION, '--realpath' => false])
        ->assertSuccessful();

    expect(Book::query()->findOrFail($english)->description_language)->toBe(DescriptionLanguage::Other)
        ->and(Book::query()->findOrFail($spanish)->description_language)->toBe(DescriptionLanguage::Spanish)
        ->and(Book::query()->findOrFail($none)->description_language)->toBeNull();
});

/**
 * Sin este relleno los Books en inglés no entran en ninguna corrida: la
 * consulta que los elige lee la columna, no el texto.
 */
it('deja a los Books en otro idioma esperando su traducción', function () {
    $this->artisan('migrate:rollback', ['--path' => DESCRIPTION_LANGUAGE_MIGRATION, '--realpath' => false])
        ->assertSuccessful();

    $english = bookWrittenBefore(
        'Who moved my cheese?',
        'A short story about two mice and two little people who live in a maze, and about what each of them does when the cheese is not there any more.'
    );

    $this->artisan('migrate', ['--path' => DESCRIPTION_LANGUAGE_MIGRATION, '--realpath' => false])
        ->assertSuccessful();

    expect(Book::query()->findOrFail($english)->descriptionAwaitsTranslation())->toBeTrue()
        ->and(Book::query()->descriptionAwaitingTranslation()->count())->toBe(1);
});
