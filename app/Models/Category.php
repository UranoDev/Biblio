<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The subject a Book belongs to. In the Showcase it is the way to navigate,
 * hence the slug.
 *
 * The vocabulary fragments on its own: the AI is handed the existing list, but
 * over months «Ciencia ficción» and «Ciencia-ficción» still turn up, and so do
 * Categories with a single Book that nobody navigates by (ADR-006). Renaming
 * and merging are what keeps it healthy, and both happen here rather than on
 * the screen, because both touch more than one row and have to land whole.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_canonical
 * @property int|null $suggested_by_book_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Book> $books
 * @property-read Collection<int, CategorySlug> $formerSlugs
 * @property-read Book|null $suggestedBy
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $table = 'categories';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_canonical' => 'boolean'];
    }

    /** @return BelongsToMany<Book, $this> */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_category');
    }

    /** The Book whose AI run first proposed this non-canonical Category. */
    public function suggestedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Book::class, 'suggested_by_book_id');
    }

    /**
     * The addresses this Category used to answer to, from its own renames and
     * from whatever it absorbed.
     *
     * @return HasMany<CategorySlug, $this>
     */
    public function formerSlugs(): HasMany
    {
        return $this->hasMany(CategorySlug::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The Category an address names, whether it is the current slug or one it
     * had before.
     *
     * The current one is looked up first: a slug that a live Category holds is
     * never also history, but asking in this order says out loud which of the
     * two answers a redirect and which does not.
     */
    public static function bySlug(string $slug): ?self
    {
        $current = self::query()->where('slug', $slug)->first();

        if ($current instanceof self) {
            return $current;
        }

        return CategorySlug::query()->where('slug', $slug)->first()?->category;
    }

    /**
     * Gives the Category another name, and the slug that comes out of it.
     *
     * The slug it had is kept —unless the name was only respelled and the slug
     * did not move— so that the addresses already shared land here instead of
     * on a 404. And if the new slug was itself history, that row goes: an
     * address belongs to the Category that holds it now, not to a redirect.
     */
    public function rename(string $name): void
    {
        $written = trim($name);
        $slug = Str::slug($written);

        if ($written === '' || $slug === '') {
            return;
        }

        $previous = $this->slug;

        DB::transaction(function () use ($written, $slug, $previous): void {
            $this->forceFill(['name' => $written, 'slug' => $slug])->save();

            CategorySlug::query()->where('slug', $slug)->delete();

            if ($previous !== $slug) {
                $this->formerSlugs()->create(['slug' => $previous]);
            }
        });
    }

    /**
     * Takes in every Book of another Category and leaves it gone.
     *
     * The Books of both end up here without repeating —a Book in both was one
     * Book all along— and the address of what was absorbed keeps working, now
     * pointing here. Its own history comes along: a Category that was renamed
     * before being merged carries several addresses, and all of them have to
     * survive the merge.
     */
    public function absorb(self $other): void
    {
        if ($other->getKey() === $this->getKey()) {
            return;
        }

        DB::transaction(function () use ($other): void {
            /** @var list<int> $books */
            $books = $other->books()->pluck('books.id')->all();

            $this->books()->syncWithoutDetaching($books);

            // The rows of the absorbed Category point here before it is
            // deleted, which is what keeps the cascade from taking them.
            $other->formerSlugs()->update(['category_id' => $this->getKey()]);

            $this->formerSlugs()->create(['slug' => $other->slug]);

            $other->books()->detach();
            $other->delete();
        });

        $this->unsetRelation('books');
        $this->unsetRelation('formerSlugs');
    }

    /** Categories in the canonical list. */
    #[Scope]
    protected function canonical(Builder $query): void
    {
        $query->where('is_canonical', true);
    }

    /** Non-canonical Categories that have at least one Book — these need admin review. */
    #[Scope]
    protected function pendingReview(Builder $query): void
    {
        $query->where('is_canonical', false)->has('books');
    }

    /**
     * Every Category by name, each one carrying how many Books it has.
     *
     * This is the list the screen works on, and the count is the whole point of
     * it: the ones with a single Book are the first symptom of a fragmented
     * vocabulary, and the ones with none are what a merge leaves behind.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withBookCount(Builder $query): void
    {
        $query->withCount('books')->orderBy('name');
    }
}
