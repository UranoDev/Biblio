<?php

namespace App\Models;

use App\Ai\AiModel;
use App\Covers\GeneratedCover;
use App\Enums\CoverOrigin;
use App\Enums\CoverSize;
use App\Enums\GenerationStatus;
use App\Enums\Origin;
use App\Enums\ReadingStatus;
use App\Enums\Source;
use App\Enums\Status;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A concrete edition, identified by its ISBN: the unit that gets registered,
 * that is owned or that is wanted.
 *
 * @property int $id
 * @property string|null $isbn
 * @property string $title
 * @property list<string> $authors
 * @property string|null $publisher
 * @property int|null $year
 * @property string|null $edition
 * @property string|null $description
 * @property Origin|null $description_origin
 * @property Source|null $source
 * @property string|null $cover_path
 * @property string|null $cover_source_url
 * @property string|null $cover_thumbnail_path
 * @property CoverOrigin $cover_origin
 * @property Status $status
 * @property bool $is_read
 * @property Carbon|null $read_on
 * @property string|null $note
 * @property string|null $lent_to
 * @property Carbon|null $lent_on
 * @property bool $is_hidden
 * @property string|null $categories_provider
 * @property string|null $categories_ai_model
 * @property Carbon|null $categories_edited_at
 * @property string|null $description_provider
 * @property string|null $description_ai_model
 * @property Carbon|null $generation_queued_at
 * @property Carbon|null $generation_failed_at
 * @property string|null $generation_failure
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Quote> $quotes
 * @property-read Collection<int, Category> $categories
 */
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    protected $table = 'books';

    protected $guarded = ['id'];

    /**
     * A Book is born with the generated Cover, whichever path it came in
     * through (ADR-008): there is no moment in its life when it has none. The
     * column carries the same default, so a row written outside Eloquent lands
     * the same way.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'cover_origin' => CoverOrigin::Generated->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'description_origin' => Origin::class,
            'cover_origin' => CoverOrigin::class,
            'source' => Source::class,
            'status' => Status::class,
            'is_read' => 'boolean',
            'read_on' => 'date',
            'lent_on' => 'date',
            'is_hidden' => 'boolean',
            'categories_edited_at' => 'datetime',
            'generation_queued_at' => 'datetime',
            'generation_failed_at' => 'datetime',
        ];
    }

    /**
     * Anything that shows in the image of a Quote and lives on the Book sends
     * its Quotes back to the queue (ADR-009).
     *
     * The Cover is the one that costs: replacing a generated piece with a
     * photograph of my own (ADR-008) invalidates the shareable image of every
     * Quote of that Book, and a day of photographing twenty books can be a
     * hundred Quotes — two hundred renders. Without this rule the library fills
     * up in silence with images of the Cover it used to have, and it is only
     * found out when somebody shares one.
     */
    protected static function booted(): void
    {
        static::updated(function (Book $book): void {
            if (! $book->wasChanged(['title', 'authors', 'cover_path'])) {
                return;
            }

            $book->quotes()->each(fn (Quote $quote) => $quote->queueImages());
        });
    }

    /**
     * The Quotes of the Book, in the order they go in the Book: the front
     * matter first, then the rest by page, and the ones whose page was not
     * written down at the end.
     *
     * The order lives on the relation and not on each screen, because the Shelf
     * and the Showcase have to show the same Quotes in the same sequence, and a
     * Quote typed later belongs where its page is, not at the bottom.
     *
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->inBookOrder();
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'book_category');
    }

    /**
     * Which of the three Reading states the Book is in.
     *
     * Read without a date is not the same as unread, so the state cannot be
     * deduced from the date alone.
     */
    public function readingStatus(): ReadingStatus
    {
        if (! $this->is_read) {
            return ReadingStatus::Unread;
        }

        return $this->read_on === null
            ? ReadingStatus::ReadUndated
            : ReadingStatus::ReadDated;
    }

    /**
     * Whether the Book already has a Description, whoever wrote it.
     */
    public function hasDescription(): bool
    {
        return $this->description !== null && trim($this->description) !== '';
    }

    /**
     * Whether the AI may write the Description of this Book (ADR-007).
     *
     * It may when there is none —no Source brought it— and when the one there
     * is was written by the AI itself. The one from the Source and mine are
     * never overwritten, not even by a regeneration.
     */
    public function acceptsAiDescription(): bool
    {
        if (! $this->hasDescription()) {
            return true;
        }

        return $this->description_origin?->isRegenerated() === true;
    }

    /**
     * Which of the four situations the Categories of the Book are in (ADR-006).
     *
     * A Book without Categories is not one thing but three: one waiting its
     * turn in the queue, one the queue gave up on after hours of trying, and
     * one that is not going to get any until somebody chooses an AiModel. The
     * screen that shows them says which.
     *
     * The failure comes first, even over Categories written earlier: what it
     * describes is the last thing that happened with the AI for this Book, and
     * that is a regeneration that did not get through.
     */
    public function categoriesStatus(): GenerationStatus
    {
        if ($this->generation_failed_at !== null) {
            return GenerationStatus::Failed;
        }

        // Waiting its turn comes before what it already has: a Book whose
        // Categories were written months ago and that was queued again is in
        // the queue, and the sheet has to say so from the moment the button was
        // pressed and not once the answer arrives (BIB-22).
        if ($this->generation_queued_at !== null) {
            return GenerationStatus::Pending;
        }

        if ($this->categories_ai_model !== null) {
            return GenerationStatus::Generated;
        }

        return AiModel::chosen() instanceof AiModel
            ? GenerationStatus::Pending
            : GenerationStatus::Off;
    }

    /** Whether the Book is in the queue waiting for what the AI writes for it. */
    public function isGenerationQueued(): bool
    {
        return $this->generation_queued_at !== null;
    }

    /**
     * Whether the Categories of the Book were corrected by hand.
     *
     * It is what the warning before a regeneration is built on: the AI
     * overwrites its own work without asking, and mine only after a yes
     * (BIB-22).
     */
    public function categoriesEditedByHand(): bool
    {
        return $this->categories_edited_at !== null;
    }

    /**
     * The Categories of the Book were just corrected by hand: one was added or
     * one was taken out.
     */
    public function categoriesCorrected(): void
    {
        $this->forceFill(['categories_edited_at' => now()])->save();
    }

    /**
     * The Book is waiting its turn: the request is on its way and whatever the
     * queue gave up on before is off the record (BIB-33).
     *
     * The mark is written when the button is pressed and not when the answer
     * arrives, because between the two there are two hundred Books ahead of it
     * and the screen cannot stay silent for that long (BIB-22).
     */
    public function generationQueued(): void
    {
        $this->forceFill([
            'generation_queued_at' => now(),
            'generation_failed_at' => null,
            'generation_failure' => null,
        ])->save();
    }

    /**
     * The queue gave up on what the AI writes for this Book, and this is what
     * the provider said last. It is written down so that the sheet can tell it
     * apart from a Book that is still in line (BIB-33).
     */
    public function generationFailed(string $reason): void
    {
        $this->forceFill([
            'generation_queued_at' => null,
            'generation_failed_at' => now(),
            'generation_failure' => Str::limit(Str::squish($reason), 250),
        ])->save();
    }

    /**
     * The Book that was Wanted is mine now. It is a change of Status, not a new
     * record: Shelf and Wanted are the same entity (ADR-005).
     */
    public function moveToShelf(): void
    {
        $this->forceFill(['status' => Status::Owned])->save();
    }

    /** Undoes the previous step: the Book is Wanted again. */
    public function moveBackToWanted(): void
    {
        $this->forceFill(['status' => Status::Wanted])->save();
    }

    /**
     * The Book is read, with the date when it is known.
     *
     * Without a date it stays read: a Book read years ago, without knowing
     * when, is not the same thing as an unread one, and losing that difference
     * is what a plain checkbox does.
     *
     * @param  Carbon|string|null  $on  the read date, or null when it is not known
     */
    public function markAsRead(Carbon|string|null $on = null): void
    {
        $this->forceFill(['is_read' => true, 'read_on' => $on])->save();
    }

    /** The Book goes back to unread, and the date it had goes with it. */
    public function markAsUnread(): void
    {
        $this->forceFill(['is_read' => false, 'read_on' => null])->save();
    }

    /**
     * The Book was lent out, in free text and with the date it left.
     *
     * @param  Carbon|string|null  $on  the date it left, or null when it was not written down
     */
    public function lendTo(string $who, Carbon|string|null $on = null): void
    {
        $this->forceFill(['lent_to' => $who, 'lent_on' => $on])->save();
    }

    /**
     * The Book came back. Nothing is kept: there is no history of who had it,
     * because the lending is a field and not an entity.
     */
    public function returned(): void
    {
        $this->forceFill(['lent_to' => null, 'lent_on' => null])->save();
    }

    /**
     * The Description I wrote myself, which from here on no regeneration
     * touches (ADR-007).
     *
     * The AiModel that wrote the previous one stops being recorded: there is no
     * longer anything of the AI's in this Description, and leaving the stamp
     * would put the Book in the queue of what is left on the old AiModel.
     */
    public function writeDescription(?string $text): void
    {
        $written = $text === null || trim($text) === '' ? null : trim($text);

        $this->forceFill([
            'description' => $written,
            'description_origin' => $written === null ? null : Origin::Owner,
            'description_provider' => null,
            'description_ai_model' => null,
        ])->save();
    }

    /**
     * Adds a Quote to the Book: the words of the Book, with its page when it
     * was written down.
     *
     * @param  string|null  $page  the page as the Book prints it — `29` or `xiv` —,
     *                             or null when it was not written down
     */
    public function addQuote(string $text, ?string $page = null): Quote
    {
        return $this->quotes()->create([
            'text' => Quote::transcribe($text),
            'page' => $page,
        ]);
    }

    /** The Note: mine, one per Book, and out of the Showcase. */
    public function writeNote(?string $text): void
    {
        $written = $text === null || trim($text) === '' ? null : trim($text);

        $this->forceFill(['note' => $written])->save();
    }

    /** Takes the Book out of the Showcase, and its Quotes with it (ADR-003). */
    public function hide(): void
    {
        $this->forceFill(['is_hidden' => true])->save();
    }

    /** Puts the Book back in the Showcase. */
    public function show(): void
    {
        $this->forceFill(['is_hidden' => false])->save();
    }

    /**
     * Whether the Cover of the Book is a stored image.
     *
     * It is not the same question as whether it has a Cover: every Book has one
     * (ADR-008), and the ones with no file show the generated piece. This is
     * what the screens ask before building a URL.
     */
    public function hasCover(): bool
    {
        return $this->cover_path !== null;
    }

    /**
     * The typographic piece that stands in for the image, built on the fly and
     * never written to disk (ADR-008).
     */
    public function generatedCover(): GeneratedCover
    {
        return GeneratedCover::for($this);
    }

    /**
     * Where the Cover the Book shows came from.
     *
     * A Book read back with only some of its columns —a `select` that left this
     * one out— answers the generated piece, which is the state a Book with no
     * stored image is in.
     */
    public function coverOrigin(): CoverOrigin
    {
        return $this->cover_origin ?? CoverOrigin::Generated;
    }

    /** Whether what the Book shows is the generated piece and not a photograph. */
    public function hasGeneratedCover(): bool
    {
        return $this->coverOrigin() === CoverOrigin::Generated;
    }

    /**
     * Whether a Cover arriving from a Source may take the place of the one the
     * Book has now (ADR-008).
     *
     * It may when the one there was generated, and when it came from a Source
     * itself. It never may over the photo I took: that one is replaced by
     * uploading another one, by hand and on purpose.
     */
    public function acceptsCoverFromSource(): bool
    {
        return CoverOrigin::Source->mayReplace($this->coverOrigin());
    }

    /** The Cover at original size, for the Book sheet. */
    public function coverUrl(): ?string
    {
        return $this->coverUrlFor(CoverSize::Full);
    }

    /**
     * The scaled-down version, for the Shelf and Showcase grids. It has an
     * address of its own even when it was not stored: what gets served then is
     * the original, which is better than a hole in the grid.
     */
    public function coverThumbnailUrl(): ?string
    {
        return $this->coverUrlFor(CoverSize::Thumbnail);
    }

    /**
     * Where the Cover is served from: a route of the application, never the URL
     * of the disk.
     *
     * The disk is private and answers nothing to a request without a signature,
     * so `Storage::url()` builds an address that has never worked (BIB-32). And
     * the application is the only place that knows whether the Book is Hidden,
     * which its Cover has to obey too (ADR-003).
     */
    public function coverUrlFor(CoverSize $size): ?string
    {
        return $this->coverPathFor($size) === null
            ? null
            : route('cover', ['book' => $this, 'size' => $size]);
    }

    /** The file on the disk each version of the Cover is stored at. */
    public function coverPathFor(CoverSize $size): ?string
    {
        return match ($size) {
            CoverSize::Full => $this->cover_path,
            CoverSize::Thumbnail => $this->cover_thumbnail_path ?? $this->cover_path,
        };
    }

    /**
     * Whether there is still somewhere to download the Cover from again. Without
     * a source URL —ManualEntry, or a Source that brought no image— the only way
     * is to upload it.
     */
    public function canRetryCover(): bool
    {
        return ! $this->hasCover()
            && $this->cover_source_url !== null
            && $this->acceptsCoverFromSource();
    }

    /**
     * The Books whose Categories were written by another AiModel.
     *
     * Knowing which ones they are is what makes the regeneration precise:
     * without it the only way to bring them up to date is to regenerate all of
     * them and pay for them again (ADR-006).
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function categoriesFromAnotherAiModel(Builder $query, AiModel $chosen): void
    {
        $query->whereNotNull('categories_ai_model')
            ->where(function (Builder $query) use ($chosen): void {
                $query->where('categories_ai_model', '!=', $chosen->name)
                    ->orWhere('categories_provider', '!=', $chosen->provider);
            });
    }

    /**
     * The Books whose Description was written by another AiModel. The ones the
     * Source wrote and mine are not here: their Origin is not the AI and
     * regenerating never touches them (ADR-007).
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function descriptionFromAnotherAiModel(Builder $query, AiModel $chosen): void
    {
        $query->whereNotNull('description_ai_model')
            ->where(function (Builder $query) use ($chosen): void {
                $query->where('description_ai_model', '!=', $chosen->name)
                    ->orWhere('description_provider', '!=', $chosen->provider);
            });
    }

    /**
     * The Books left on another AiModel, on either of the two halves: the
     * candidates to regenerate after changing the model (ADR-006).
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function fromAnotherAiModel(Builder $query, AiModel $chosen): void
    {
        $query->where(function (Builder $query) use ($chosen): void {
            $query->categoriesFromAnotherAiModel($chosen)
                ->orWhere(fn (Builder $query) => $query->descriptionFromAnotherAiModel($chosen));
        });
    }

    /**
     * The Books waiting their turn in the queue (BIB-22). The grid asks so that
     * it only keeps looking while there is something on its way.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function awaitingGeneration(Builder $query): void
    {
        $query->whereNotNull('generation_queued_at');
    }

    /**
     * The Books the AI may write a Description for (ADR-007): the ones with
     * none, and the ones whose Description the AI wrote itself.
     *
     * It is the query form of acceptsAiDescription(), and it is what keeps a
     * mass regeneration from ever reaching the Description of the Source or
     * mine: those never enter the run in the first place.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function descriptionWritable(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('description')
                ->orWhere('description', '')
                ->orWhere('description_origin', Origin::Ai);
        });
    }

    /**
     * The other side: the Books whose Description no regeneration touches,
     * because the Source brought it or I wrote it (ADR-007).
     *
     * They get counted before launching, so that the run says out loud how many
     * it is leaving out and why, instead of quietly skipping them.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function descriptionKept(Builder $query): void
    {
        $query->whereNotNull('description')
            ->where('description', '!=', '')
            ->whereIn('description_origin', [Origin::Source, Origin::Owner]);
    }

    /**
     * The Books the queue gave up on. They are the ones a regeneration can be
     * thrown at again without touching the rest (BIB-33).
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withFailedGeneration(Builder $query): void
    {
        $query->whereNotNull('generation_failed_at');
    }

    /**
     * The Books whose Cover is the generated piece: the queue of what is left
     * to photograph (ADR-008).
     *
     * That queue is the other half of what the Origin is for. Replacing the
     * generated Covers with my own photographs is going to take months, book by
     * book, and without this there would be no way to say how many are left.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function coverGenerated(Builder $query): void
    {
        $query->where('cover_origin', CoverOrigin::Generated);
    }

    /**
     * The Books whose Cover came from one particular place.
     *
     * It is what the filter of the Shelf asks, so that the number the Dashboard
     * prints opens the list it counted (BIB-40).
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withCoverOrigin(Builder $query, CoverOrigin $origin): void
    {
        $query->where('cover_origin', $origin);
    }

    /**
     * The Books nobody has written a single Quote from yet.
     *
     * A Book gets read and its Quotes get typed in afterwards, days later and
     * from the paper: this is the list of the ones where that is still owed.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withoutQuotes(Builder $query): void
    {
        $query->whereDoesntHave('quotes');
    }

    /**
     * The Books that show up in the Showcase. Every query feeding it goes
     * through here (ADR-003).
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('is_hidden', false);
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function owned(Builder $query): void
    {
        $query->where('status', Status::Owned);
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function wanted(Builder $query): void
    {
        $query->where('status', Status::Wanted);
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function read(Builder $query): void
    {
        $query->where('is_read', true);
    }

    /**
     * The Search: the Books whose title or whose Authors contain what was
     * typed. It never goes out to a Source — that is the TitleLookup.
     *
     * The escape character is `=` and not the usual backslash: a backslash
     * inside a bound pattern does not mean the same thing to MySQL, where the
     * Shelf runs, as to SQLite, where the tests do. With `=` both engines read
     * the pattern the same way, and a `%` typed into the box is a percent sign
     * and not «everything».
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $typed = Str::lower(trim($term));

        if ($typed === '') {
            return;
        }

        $like = static fn (string $text): string => '%'.str_replace(
            ['=', '%', '_'],
            ['==', '=%', '=_'],
            $text,
        ).'%';

        // The Authors are a JSON column and the two engines keep it written
        // differently: MySQL stores the accented character and SQLite stores
        // the `á` that json_encode produced. Both forms are looked for, so
        // that «Julián» finds the same Book wherever it is stored.
        $asStored = trim((string) json_encode($typed), '"');

        $query->where(function (Builder $query) use ($like, $typed, $asStored): void {
            $query->whereRaw("lower(title) like ? escape '='", [$like($typed)])
                ->orWhereRaw("lower(cast(authors as char)) like ? escape '='", [$like($typed)])
                ->orWhereRaw("lower(cast(authors as char)) like ? escape '='", [$like($asStored)]);
        });
    }

    /**
     * The Books in one of the three Reading states.
     *
     * It is not a column: read without a date and unread are two states that a
     * single flag cannot tell apart, so each one is its own pair of conditions.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function inReadingStatus(Builder $query, ReadingStatus $status): void
    {
        match ($status) {
            ReadingStatus::Unread => $query->where('is_read', false),
            ReadingStatus::ReadUndated => $query->where('is_read', true)->whereNull('read_on'),
            ReadingStatus::ReadDated => $query->where('is_read', true)->whereNotNull('read_on'),
        };
    }

    /**
     * The Books that belong to one Category, by its slug.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function inCategory(Builder $query, string $slug): void
    {
        $query->whereHas('categories', function (Builder $categories) use ($slug): void {
            $categories->where('categories.slug', $slug);
        });
    }
}
