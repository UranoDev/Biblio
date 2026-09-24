<?php

use App\Models\Book;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

/*
 * The page of a Quote stopped being an integer and became the label the Book
 * prints (BIB-42). The Quotes written before that cannot lose their page, so
 * each test here puts the old schema back, writes a Quote the way it used to
 * be written, and migrates forward again.
 */
const QUOTE_PAGE_MIGRATION = 'database/migrations/2026_09_24_100000_make_quote_page_a_label.php';

it('conserva la página de las Quotes ya capturadas', function () {
    $book = Book::factory()->create();

    $this->artisan('migrate:rollback', ['--path' => QUOTE_PAGE_MIGRATION, '--realpath' => false])
        ->assertSuccessful();

    $id = DB::table('quotes')->insertGetId([
        'book_id' => $book->getKey(),
        'text' => 'Vine a Comala porque me dijeron que acá vivía mi padre.',
        'text_folded' => 'vine a comala porque me dijeron que aca vivia mi padre.',
        'page' => 7,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate', ['--path' => QUOTE_PAGE_MIGRATION, '--realpath' => false])
        ->assertSuccessful();

    $quote = Quote::query()->findOrFail($id);

    expect($quote->page)->toBe('7')
        ->and($quote->page_is_front_matter)->toBeFalse()
        ->and($quote->page_number)->toBe(7);
});

it('deja sin página la Quote que nunca la trajo', function () {
    $book = Book::factory()->create();

    $this->artisan('migrate:rollback', ['--path' => QUOTE_PAGE_MIGRATION, '--realpath' => false])
        ->assertSuccessful();

    $id = DB::table('quotes')->insertGetId([
        'book_id' => $book->getKey(),
        'text' => 'Nada puede durar tanto tiempo.',
        'text_folded' => 'nada puede durar tanto tiempo.',
        'page' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate', ['--path' => QUOTE_PAGE_MIGRATION, '--realpath' => false])
        ->assertSuccessful();

    $quote = Quote::query()->findOrFail($id);

    expect($quote->page)->toBeNull()
        ->and($quote->page_is_front_matter)->toBeFalse()
        ->and($quote->page_number)->toBeNull();
});
