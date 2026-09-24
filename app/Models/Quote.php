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

    /**
     * Corrects the Quote: a typo in the transcription, or the page that was
     * missing. The words stay the Book's either way.
     */
    public function rewrite(string $text, ?int $page = null): void
    {
        $this->forceFill([
            'text' => self::transcribe($text),
            'page' => $page,
        ])->save();
    }

    /**
     * The text as it gets stored.
     *
     * The blank space around it goes, because it is typing and not the Book.
     * The line breaks inside stay, because a quote of verse or dialogue is not
     * the same thing once they are gone; the ones the browser sends as CRLF get
     * written like every other, so that two Quotes typed the same are stored
     * the same.
     */
    public static function transcribe(string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }
}
