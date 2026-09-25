<?php

namespace Tests\Feature\Domain;

use App\Models\Book;
use App\Models\Category;
use App\Models\CategorySlug;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_has_several_books(): void
    {
        $category = Category::factory()->named('Ciencia ficción')->create();
        $books = Book::factory()->count(3)->create();

        $category->books()->attach($books);

        $this->assertCount(3, $category->fresh()->books);
    }

    public function test_the_name_is_not_repeated(): void
    {
        Category::factory()->named('Ensayo')->create();

        $this->expectException(QueryException::class);

        Category::factory()->named('Ensayo')->create();
    }

    public function test_the_slug_comes_from_the_name_and_serves_the_url(): void
    {
        $category = Category::factory()->named('Ciencia ficción')->create();

        $this->assertSame('ciencia-ficcion', $category->slug);
        $this->assertSame('slug', $category->getRouteKeyName());
    }

    public function test_deleting_a_category_leaves_the_books(): void
    {
        $category = Category::factory()->create();
        $book = Book::factory()->create();
        $category->books()->attach($book);

        $category->delete();

        $this->assertSame(1, Book::count());
        $this->assertCount(0, $book->fresh()->categories);
    }

    /*
    |--------------------------------------------------------------------------
    | Renaming
    |--------------------------------------------------------------------------
    */

    public function test_renaming_changes_the_name_and_the_slug(): void
    {
        $category = Category::factory()->named('Ciencia ficcion')->create();

        $category->rename('Ciencia ficción');

        $this->assertSame('Ciencia ficción', $category->fresh()->name);
        $this->assertSame('ciencia-ficcion', $category->fresh()->slug);
    }

    public function test_renaming_keeps_the_books(): void
    {
        $category = Category::factory()->named('Novela gráfica')->create();
        $books = Book::factory()->count(2)->create();
        $category->books()->attach($books);

        $category->rename('Narrativa');

        $this->assertCount(2, $category->fresh()->books);
    }

    public function test_the_old_address_still_reaches_the_renamed_category(): void
    {
        $category = Category::factory()->named('Novela gráfica')->create();

        $category->rename('Narrativa');

        $this->assertTrue(Category::bySlug('novela-grafica')?->is($category));
        $this->assertTrue(Category::bySlug('narrativa')?->is($category));
    }

    public function test_an_address_nobody_ever_had_reaches_nothing(): void
    {
        Category::factory()->named('Novela gráfica')->create();

        $this->assertNull(Category::bySlug('astronomia'));
    }

    public function test_a_respelling_that_leaves_the_slug_where_it_was_keeps_no_history(): void
    {
        $category = Category::factory()->named('ciencia ficcion')->create();

        $category->rename('Ciencia Ficcion');

        $this->assertSame('ciencia-ficcion', $category->fresh()->slug);
        $this->assertCount(0, $category->fresh()->formerSlugs);
    }

    public function test_renaming_back_takes_the_address_away_from_the_history(): void
    {
        $category = Category::factory()->named('Novela gráfica')->create();

        $category->rename('Narrativa');
        $category->rename('Novela gráfica');

        $this->assertSame('novela-grafica', $category->fresh()->slug);
        $this->assertSame(['narrativa'], $category->fresh()->formerSlugs->pluck('slug')->all());
        $this->assertTrue(Category::bySlug('novela-grafica')?->is($category));
    }

    public function test_a_blank_name_renames_nothing(): void
    {
        $category = Category::factory()->named('Novela gráfica')->create();

        $category->rename('   ');

        $this->assertSame('Novela gráfica', $category->fresh()->name);
    }

    /*
    |--------------------------------------------------------------------------
    | Merging
    |--------------------------------------------------------------------------
    */

    public function test_merging_leaves_the_books_of_both_in_the_one_that_stays(): void
    {
        $stays = Category::factory()->named('Ciencia ficción')->create();
        $absorbed = Category::factory()->named('Sci-fi')->create();

        $stays->books()->attach(Book::factory()->create());
        $absorbed->books()->attach(Book::factory()->count(2)->create());

        $stays->absorb($absorbed);

        $this->assertCount(3, $stays->fresh()->books);
    }

    public function test_a_book_in_both_is_not_counted_twice(): void
    {
        $stays = Category::factory()->named('Ciencia ficción')->create();
        $absorbed = Category::factory()->named('Sci-fi')->create();
        $shared = Book::factory()->create();

        $stays->books()->attach($shared);
        $absorbed->books()->attach($shared);

        $stays->absorb($absorbed);

        $this->assertCount(1, $stays->fresh()->books);
    }

    public function test_the_absorbed_category_is_gone(): void
    {
        $stays = Category::factory()->named('Ciencia ficción')->create();
        $absorbed = Category::factory()->named('Sci-fi')->create();

        $stays->absorb($absorbed);

        $this->assertNull(Category::find($absorbed->getKey()));
    }

    public function test_the_address_of_the_absorbed_category_reaches_the_one_that_stays(): void
    {
        $stays = Category::factory()->named('Ciencia ficción')->create();
        $absorbed = Category::factory()->named('Sci-fi')->create();

        $stays->absorb($absorbed);

        $this->assertTrue(Category::bySlug('sci-fi')?->is($stays));
    }

    public function test_the_addresses_the_absorbed_category_had_before_survive_the_merge(): void
    {
        $stays = Category::factory()->named('Narrativa')->create();
        $absorbed = Category::factory()->named('Novela gráfica')->create();
        $absorbed->rename('Novelas');

        $stays->absorb($absorbed);

        $this->assertTrue(Category::bySlug('novela-grafica')?->is($stays));
        $this->assertTrue(Category::bySlug('novelas')?->is($stays));
        $this->assertSame(2, CategorySlug::query()->count());
    }

    public function test_merging_a_category_into_itself_does_nothing(): void
    {
        $category = Category::factory()->named('Novela gráfica')->create();
        $category->books()->attach(Book::factory()->create());

        $category->absorb($category);

        $this->assertNotNull(Category::find($category->getKey()));
        $this->assertCount(1, $category->fresh()->books);
    }

    public function test_the_books_of_the_absorbed_category_are_not_deleted(): void
    {
        $stays = Category::factory()->named('Narrativa')->create();
        $absorbed = Category::factory()->named('Novela gráfica')->create();
        $book = Book::factory()->create();
        $absorbed->books()->attach($book);

        $stays->absorb($absorbed);

        $this->assertNotNull(Book::find($book->getKey()));
    }

    /*
    |--------------------------------------------------------------------------
    | The count
    |--------------------------------------------------------------------------
    */

    public function test_the_list_says_how_many_books_each_category_has(): void
    {
        $several = Category::factory()->named('Narrativa')->create();
        $several->books()->attach(Book::factory()->count(3)->create());

        Category::factory()->named('Lírica')->create()->books()->attach(Book::factory()->create());
        Category::factory()->named('Ensayo')->create();

        $counts = Category::query()->withBookCount()->get()
            ->mapWithKeys(fn (Category $each) => [$each->name => (int) $each->books_count])
            ->all();

        $this->assertSame(3, $counts['Narrativa']);
        $this->assertSame(1, $counts['Lírica']);
        $this->assertSame(0, $counts['Ensayo']);
    }
}
