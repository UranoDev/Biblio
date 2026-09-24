<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A slug a Category used to answer to, and the Category that answers for it
 * now.
 *
 * It exists so that renaming and merging stay free of consequences: the address
 * that was shared before the change keeps working, and lands on the Category
 * the Books ended up in (BIB-17).
 *
 * @property int $id
 * @property int $category_id
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category $category
 */
class CategorySlug extends Model
{
    protected $table = 'category_slugs';

    protected $guarded = ['id'];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
