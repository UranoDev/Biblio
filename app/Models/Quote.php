<?php

namespace App\Models;

use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A verbatim fragment of the Book, with its page if it was written down. These
 * are the words of the Book, not mine — that is the Note, which lives on the
 * Book.
 *
 * @property int $id
 * @property int $book_id
 * @property string $text
 * @property int|null $page
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Book $book
 */
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    protected $table = 'quotes';

    protected $guarded = ['id'];

    /** @return BelongsTo<Book, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
