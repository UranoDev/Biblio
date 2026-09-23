<?php

namespace Tests\Feature\Domain;

use App\Models\Book;
use App\Models\Category;
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
}
