<?php

namespace Tests\Feature\Domain;

use App\Enums\Origin;
use App\Enums\ReadingStatus;
use App\Enums\Status;
use App\Models\Book;
use App\Models\Category;
use App\Models\Quote;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_isbn_is_not_registered_twice(): void
    {
        Book::factory()->create(['isbn' => '9786073100000']);

        $this->expectException(QueryException::class);

        Book::factory()->create(['isbn' => '9786073100000']);
    }

    public function test_a_book_without_an_isbn_can_be_registered_more_than_once(): void
    {
        Book::factory()->withoutIsbn()->create();
        Book::factory()->withoutIsbn()->create();

        $this->assertSame(2, Book::whereNull('isbn')->count());
    }

    public function test_a_book_is_born_visible(): void
    {
        $book = Book::create([
            'title' => 'Pedro Páramo',
            'authors' => ['Juan Rulfo'],
        ]);

        $this->assertFalse($book->fresh()->is_hidden);
    }

    public function test_a_book_is_born_on_the_shelf(): void
    {
        $book = Book::create([
            'title' => 'Pedro Páramo',
            'authors' => ['Juan Rulfo'],
        ]);

        $this->assertSame(Status::Owned, $book->fresh()->status);
    }

    public function test_reading_tells_three_states_apart(): void
    {
        $unread = Book::factory()->create();
        $undated = Book::factory()->readUndated()->create();
        $dated = Book::factory()->read()->create(['read_on' => '2024-03-01']);

        $this->assertSame(ReadingStatus::Unread, $unread->fresh()->readingStatus());
        $this->assertSame(ReadingStatus::ReadUndated, $undated->fresh()->readingStatus());
        $this->assertSame(ReadingStatus::ReadDated, $dated->fresh()->readingStatus());

        $this->assertFalse($unread->readingStatus()->isRead());
        $this->assertTrue($undated->readingStatus()->isRead());
        $this->assertTrue($dated->readingStatus()->isRead());
    }

    public function test_read_without_a_date_is_not_confused_with_unread_in_queries(): void
    {
        Book::factory()->create();
        Book::factory()->readUndated()->create();
        Book::factory()->read()->create();

        $this->assertSame(2, Book::query()->read()->count());
        $this->assertSame(1, Book::query()->read()->whereNull('read_on')->count());
    }

    public function test_the_description_stores_which_of_the_three_origins_it_comes_from(): void
    {
        $fromSource = Book::factory()->descriptionFromSource()->create();
        $fromAi = Book::factory()->descriptionFromAi()->create();
        $fromOwner = Book::factory()->descriptionFromOwner()->create();
        $withoutDescription = Book::factory()->withoutDescription()->create();

        $this->assertSame(Origin::Source, $fromSource->fresh()->description_origin);
        $this->assertSame(Origin::Ai, $fromAi->fresh()->description_origin);
        $this->assertSame(Origin::Owner, $fromOwner->fresh()->description_origin);
        $this->assertNull($withoutDescription->fresh()->description_origin);
    }

    public function test_only_the_ai_description_is_regenerated(): void
    {
        $this->assertTrue(Origin::Ai->isRegenerated());
        $this->assertFalse(Origin::Source->isRegenerated());
        $this->assertFalse(Origin::Owner->isRegenerated());
    }

    public function test_it_stores_which_ai_model_generated_its_own(): void
    {
        $book = Book::factory()
            ->descriptionFromAi('gemini', 'gemini-2.5-flash')
            ->categoriesGenerated('gemini', 'gemini-2.5-flash')
            ->create()
            ->fresh();

        $this->assertSame('gemini', $book->description_provider);
        $this->assertSame('gemini-2.5-flash', $book->description_ai_model);
        $this->assertSame('gemini', $book->categories_provider);
        $this->assertSame('gemini-2.5-flash', $book->categories_ai_model);
    }

    public function test_a_book_nobody_generated_categories_for_has_no_ai_model(): void
    {
        $book = Book::factory()->create()->fresh();

        $this->assertNull($book->categories_ai_model);
        $this->assertCount(0, $book->categories);
    }

    public function test_the_authors_keep_their_order(): void
    {
        $book = Book::factory()->create([
            'authors' => ['Jorge Luis Borges', 'Adolfo Bioy Casares'],
        ]);

        $this->assertSame(['Jorge Luis Borges', 'Adolfo Bioy Casares'], $book->fresh()->authors);
    }

    public function test_a_book_has_several_quotes(): void
    {
        $book = Book::factory()->create();

        Quote::factory()->count(3)->create(['book_id' => $book->id]);

        $this->assertCount(3, $book->fresh()->quotes);
        $this->assertTrue($book->quotes->first()->book->is($book));
    }

    public function test_the_quotes_go_with_the_book(): void
    {
        $book = Book::factory()->has(Quote::factory()->count(2))->create();

        $book->delete();

        $this->assertSame(0, Quote::count());
    }

    public function test_a_book_has_several_categories(): void
    {
        $book = Book::factory()->create();
        $categories = Category::factory()->count(2)->create();

        $book->categories()->attach($categories);

        $this->assertCount(2, $book->fresh()->categories);
    }

    public function test_the_same_category_is_not_repeated_on_a_book(): void
    {
        $book = Book::factory()->create();
        $category = Category::factory()->create();

        $book->categories()->syncWithoutDetaching($category);
        $book->categories()->syncWithoutDetaching($category);

        $this->assertCount(1, $book->fresh()->categories);
    }

    public function test_hidden_books_stay_out_of_the_showcase_queries(): void
    {
        $visible = Book::factory()->create();
        Book::factory()->hidden()->create();

        $visibles = Book::query()->visible()->get();

        $this->assertCount(1, $visibles);
        $this->assertTrue($visibles->first()->is($visible));
    }

    public function test_shelf_and_wanted_are_the_same_entity_with_a_different_status(): void
    {
        $wanted = Book::factory()->wanted()->create();
        Book::factory()->create();

        $this->assertSame(1, Book::query()->wanted()->count());
        $this->assertSame(1, Book::query()->owned()->count());

        $wanted->update(['status' => Status::Owned]);

        $this->assertSame(0, Book::query()->wanted()->count());
        $this->assertSame(2, Book::query()->owned()->count());
    }

    public function test_the_loan_is_a_pair_of_fields_and_is_cleared_when_it_comes_back(): void
    {
        $book = Book::factory()->lent('Ana')->create();

        $this->assertSame('Ana', $book->fresh()->lent_to);
        $this->assertNotNull($book->fresh()->lent_on);

        $book->update(['lent_to' => null, 'lent_on' => null]);

        $this->assertNull($book->fresh()->lent_to);
        $this->assertNull($book->fresh()->lent_on);
    }
}
