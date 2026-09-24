<?php

namespace App\Models;

use App\Ai\AiModel;
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
use Illuminate\Support\Facades\Storage;

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
 * @property Status $status
 * @property bool $is_read
 * @property Carbon|null $read_on
 * @property string|null $note
 * @property string|null $lent_to
 * @property Carbon|null $lent_on
 * @property bool $is_hidden
 * @property string|null $categories_provider
 * @property string|null $categories_ai_model
 * @property string|null $description_provider
 * @property string|null $description_ai_model
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'description_origin' => Origin::class,
            'source' => Source::class,
            'status' => Status::class,
            'is_read' => 'boolean',
            'read_on' => 'date',
            'lent_on' => 'date',
            'is_hidden' => 'boolean',
        ];
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
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
     * Which of the three situations the Categories of the Book are in
     * (ADR-006).
     *
     * A Book without Categories is not one thing but two: one that is waiting
     * in the queue, and one that is not going to get any until somebody chooses
     * an AiModel. The screen that shows them says which.
     */
    public function categoriesStatus(): GenerationStatus
    {
        if ($this->categories_ai_model !== null) {
            return GenerationStatus::Generated;
        }

        return AiModel::chosen() instanceof AiModel
            ? GenerationStatus::Pending
            : GenerationStatus::Off;
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
     * The Cover is stored on the configured disk, it is not linked to the Source
     * (ADR-004). A Book without a Cover is a normal state: the download may have
     * failed, or there may never have been an image to download.
     */
    public function hasCover(): bool
    {
        return $this->cover_path !== null;
    }

    /** The Cover at original size, for the Book sheet. */
    public function coverUrl(): ?string
    {
        return $this->cover_path === null
            ? null
            : Storage::disk()->url($this->cover_path);
    }

    /**
     * The scaled-down version, for the Shelf and Showcase grids. If for some
     * reason it was not stored, it falls back to the original rather than to
     * nothing.
     */
    public function coverThumbnailUrl(): ?string
    {
        return $this->cover_thumbnail_path === null
            ? $this->coverUrl()
            : Storage::disk()->url($this->cover_thumbnail_path);
    }

    /**
     * Whether there is still somewhere to download the Cover from again. Without
     * a source URL —ManualEntry, or a Source that brought no image— the only way
     * is to upload it.
     */
    public function canRetryCover(): bool
    {
        return ! $this->hasCover() && $this->cover_source_url !== null;
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
     * The Books that are missing their Cover.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withoutCover(Builder $query): void
    {
        $query->whereNull('cover_path');
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
}
