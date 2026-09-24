<?php

namespace App\Models;

use App\Enums\ShareFormat;
use App\Jobs\RenderQuoteImages;
use App\Quotes\InvalidPageLabel;
use App\Quotes\PageLabel;
use App\Quotes\QuoteCard;
use App\Search\FoldedText;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A verbatim fragment of the Book, with its page if it was written down. These
 * are the words of the Book, not mine — that is the Note, which lives on the
 * Book.
 *
 * @property int $id
 * @property int $book_id
 * @property string $text
 * @property string $text_folded
 * @property string|null $page
 * @property bool $page_is_front_matter
 * @property int|null $page_number
 * @property string|null $card_image_path
 * @property string|null $story_image_path
 * @property string|null $images_fingerprint
 * @property Carbon|null $images_queued_at
 * @property Carbon|null $images_failed_at
 * @property string|null $images_failure
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
     * Whether this write owes two images, carried from the `saving` hook to the
     * `saved` one. It is not a column: the row already says the images are
     * owed, and what this adds is that **this write** is the one that has to
     * put the job in the queue.
     */
    public bool $imagesOwed = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_is_front_matter' => 'boolean',
            'images_queued_at' => 'datetime',
            'images_failed_at' => 'datetime',
        ];
    }

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

        /*
         * The two columns the order is made of come out of the label, always
         * (BIB-42). Like the folded text, they live on the save and not on the
         * two methods that write a Quote: a page corrected from anywhere whose
         * order stayed behind puts the Quote in the wrong place on the sheet
         * and in the Showcase, and nothing on the screen would say so.
         */
        static::saving(function (Quote $quote): void {
            // A Quote read back without its page -- a `select` that left the
            // column out -- has no label to read, and reading the absence would
            // erase the order that is stored.
            if (! array_key_exists('page', $quote->getAttributes())) {
                return;
            }

            $written = trim((string) $quote->page);
            $page = null;

            // A page left blank is a page that was not written down, which the
            // Quote is allowed to be.
            if ($written !== '') {
                $page = PageLabel::parse($written) ?? throw InvalidPageLabel::for($written);
            }

            $quote->page = $page?->label();
            $quote->page_is_front_matter = $page?->isFrontMatter() ?? false;
            $quote->page_number = $page?->number();
        });

        /*
         * The images are queued when the Quote is stored, and storing it never
         * waits for them (ADR-009).
         *
         * The mark goes in on the same write and not on a second one: a hook
         * that saved the Quote again to write it would be saving from inside a
         * save, where nothing has been synced yet and the Quote still looks
         * dirty in the text — so it would queue, and save, and queue again,
         * forever. Here the mark is an attribute like any other and the job is
         * dispatched once the row is down.
         */
        static::saving(function (Quote $quote): void {
            // A new Quote has no images; a corrected one has the wrong ones.
            // Writing down where the files ended up is neither, and it must not
            // queue another render of what was just rendered.
            if (! $quote->exists || $quote->isDirty(['text', 'page'])) {
                $quote->markImagesOwed();
            }
        });

        static::saved(function (Quote $quote): void {
            if ($quote->imagesOwed) {
                $quote->imagesOwed = false;

                RenderQuoteImages::dispatch($quote);
            }
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
    public function rewrite(string $text, ?string $page = null): void
    {
        $this->forceFill([
            'text' => self::transcribe($text),
            'page' => $page,
        ])->save();
    }

    /**
     * The public address of the Quote: short, stable, and the tool the whole
     * page is for (ADR-009).
     */
    public function shareUrl(): string
    {
        return route('showcase.quote', $this);
    }

    /**
     * Puts the two images in the queue. Storing a Quote never waits for them
     * (ADR-009), so this writes down that they are owed and leaves.
     *
     * It is called from outside when something the image shows changed
     * somewhere else — the title of the Book, its Cover — and from the screen
     * that picks the queue up again once there are credits.
     */
    public function queueImages(): void
    {
        $this->markImagesOwed();

        $this->save();
    }

    /**
     * Writes down that the two images are owed, on the write that is already
     * happening. Whatever the queue gave up on before is off the record: this
     * is a fresh request.
     */
    public function markImagesOwed(): void
    {
        // A Quote longer than the canvas holds has no image to wait for
        // (ADR-009), so it is not queued and it is not counted with what is
        // pending. Whatever files it had stay on the disk and stop being
        // served; if it ever gets trimmed back to a length that fits, the
        // render that follows is what sweeps them.
        if (! $this->imagesFit()) {
            $this->forceFill([
                'images_queued_at' => null,
                'images_failed_at' => null,
                'images_failure' => null,
            ]);

            $this->imagesOwed = false;

            return;
        }

        $this->forceFill([
            'images_queued_at' => now(),
            'images_failed_at' => null,
            'images_failure' => null,
        ]);

        $this->imagesOwed = true;
    }

    /** The two images are drawn and stored; nothing is owed any more. */
    public function imagesStored(string $card, string $story, string $fingerprint): void
    {
        $this->forceFill([
            'card_image_path' => $card,
            'story_image_path' => $story,
            'images_fingerprint' => $fingerprint,
            'images_queued_at' => null,
            'images_failed_at' => null,
            'images_failure' => null,
        ])->save();
    }

    /**
     * The renderer will not draw this one, and waiting is not going to change
     * that. The mark stays **next to** the one that says the images are owed,
     * not instead of it: the Quote still has none, and the count of what is
     * pending is what says how much a mass regeneration would cost.
     */
    public function imagesFailed(string $reason): void
    {
        $this->forceFill([
            'images_failed_at' => now(),
            'images_failure' => Str::limit(Str::squish($reason), 250),
        ])->save();
    }

    /**
     * The signature of everything that shows in the image: the words, the page,
     * and the Book as the card draws it — its title, its Authors and its Cover.
     *
     * It is what the address of the image carries, because the networks cache
     * `og:image` by URL and with a will: an address that stays the same after
     * the image changed keeps showing the one from a month ago. And it is what
     * makes photographing a Book invalidate the images of its Quotes (ADR-008),
     * which is the expensive consequence the ADR warns about.
     */
    public function imagesFingerprint(): string
    {
        $book = $this->book;

        return md5(implode('|', [
            QuoteCard::for($this)->text(),
            (string) $this->page,
            $book->title,
            implode(',', $book->authors),
            (string) $book->cover_path,
            $book->coverOrigin()->value,
        ]));
    }

    /**
     * Whether the card of this Quote fits the canvas of an image at all.
     *
     * The two formats are fixed and the smallest legible size is fixed, so a
     * Quote past a certain length has no picture — not a smaller one (ADR-009).
     */
    public function imagesFit(): bool
    {
        return QuoteCard::for($this)->fitsInImage();
    }

    /** Whether the image of that format is drawn and stored. */
    public function hasImageFor(ShareFormat $format): bool
    {
        return $this->imagesFit() && $this->imagePathFor($format) !== null;
    }

    /** The file on the disk, or null while the image is still owed. */
    public function imagePathFor(ShareFormat $format): ?string
    {
        $path = $this->getAttribute($format->column());

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * Where the image is served from: a route of the application, never the URL
     * of the disk. The disk is private, and the application is the only place
     * that knows whether the Book is Hidden — which its images obey too
     * (ADR-003).
     */
    public function imageUrlFor(ShareFormat $format): ?string
    {
        if (! $this->hasImageFor($format) || $this->images_fingerprint === null) {
            return null;
        }

        return route('quote.image', [
            'quote' => $this,
            'format' => $format,
            'fingerprint' => $this->images_fingerprint,
        ]);
    }

    /**
     * The files this Quote has on the disk right now.
     *
     * @return list<string>
     */
    public function imageFiles(): array
    {
        return array_values(array_filter([$this->card_image_path, $this->story_image_path]));
    }

    /**
     * The name the downloaded file arrives with. It says the Book and the page,
     * because the file lands in a folder next to twenty others and an
     * identifier says nothing there.
     */
    public function downloadName(): string
    {
        $name = $this->book->title.($this->page === null ? '' : ' - pagina '.$this->page);

        return Str::limit(Str::squish($name), 80, '').'.png';
    }

    /**
     * The Quotes still owed an image, whatever the reason: never drawn, drawn
     * before something they show changed, or waiting for the credits of next
     * month (ADR-009).
     *
     * Before launching something that turns into two hundred renders, this is
     * the number to look at.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function awaitingImages(Builder $query): void
    {
        $query->whereNotNull('images_queued_at');
    }

    /**
     * The ones the renderer refused for a reason waiting does not fix.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withFailedImages(Builder $query): void
    {
        $query->whereNotNull('images_failed_at');
    }

    /**
     * The order the Quotes go in inside a Book: the front matter first, then
     * the rest by page, and the ones whose page was not written down at the
     * end. The id breaks the ties, so two Quotes of the same page keep the
     * order they were written in.
     *
     * It reads the two columns derived from the label and never the label
     * itself (BIB-42): sorting `xiv` as text would file it among the letters,
     * and page 9 after page 10.
     *
     * It lives here and not on each query because the sheet of the Book, the
     * Showcase and the Search of Quotes all show the same sequence, and a
     * fourth screen that ordered it its own way would show another.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function inBookOrder(Builder $query): void
    {
        $query->orderByRaw('quotes.page is null, quotes.page_is_front_matter desc, quotes.page_number, quotes.id');
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
