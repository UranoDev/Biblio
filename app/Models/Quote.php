<?php

namespace App\Models;

use App\Search\FoldedText;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string $text_folded
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

    /**
     * The folded text travels with the Quote, always, and never gets written
     * by hand (BIB-16).
     *
     * It lives on the save and not on the two methods that write a Quote
     * because the two are not the only ones: a Quote corrected from anywhere
     * whose folded text stayed behind is a Quote the Search stops finding, and
     * nothing on the screen would say so.
     */
    protected static function booted(): void
    {
        static::saving(function (Quote $quote): void {
            // A Quote read back without its text -- a `select` that left the
            // column out -- has nothing to fold, and folding the absence would
            // erase what is stored.
            if (! array_key_exists('text', $quote->getAttributes())) {
                return;
            }

            $quote->text_folded = FoldedText::of($quote->text);
        });
    }

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
     * The Search of Quotes: the ones whose words hold everything that was
     * typed. It is the other half of the Search of the Shelf, and like that one
     * it never goes out to a Source -- that is the TitleLookup.
     *
     * Every word has to be in the Quote, and not just one of them: whoever
     * remembers two words of a phrase remembers them from the same phrase, and
     * answering with everything that holds either one answers with the whole
     * library.
     *
     * The comparison runs over the folded column, so the accents of what was
     * typed and of what was stored stop mattering on both sides.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        foreach (FoldedText::words($term) as $word) {
            $query->whereRaw("quotes.text_folded like ? escape '='", [FoldedText::pattern($word)]);
        }
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
