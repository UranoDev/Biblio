<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * The subject a Book belongs to. In the Showcase it is the way to navigate,
 * hence the slug.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Book> $books
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $table = 'categories';

    protected $guarded = ['id'];

    /** @return BelongsToMany<Book, $this> */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_category');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
