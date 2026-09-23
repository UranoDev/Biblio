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
