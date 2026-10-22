<?php

namespace App\Models;

use App\Covers\Image;
use App\Domain\Isbn;
use App\Intake\CoverReading;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A photograph of a Cover that arrived from the Drive folder and waits for the
 * owner to review it (BIB-74).
 *
 * It is not a Book: a Book is born only when somebody registers it, from
 * ManualEntry, the same as when the photograph is uploaded there. Until then
 * the draft keeps the photograph and what was read off it, and stays out of the
 * Shelf and the Showcase.
 *
 * @property int $id
 * @property string $drive_file_id
 * @property string $drive_file_name
 * @property string|null $photo_path
 * @property string|null $title
 * @property list<string>|null $authors
 * @property string|null $publisher
 * @property int|null $year
 * @property string|null $isbn
 * @property bool $from_source
 * @property Carbon|null $read_at
 * @property string|null $read_failure
 * @property Carbon|null $failed_at
 * @property string|null $failure
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CoverDraft extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'from_source' => 'boolean',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The photograph goes with its draft: once the Book is registered it
        // lives on as the Cover, and a discarded one is not wanted.
        static::deleted(function (CoverDraft $draft): void {
            if ($draft->photo_path !== null) {
                Storage::disk()->delete($draft->photo_path);
            }
        });
    }

    public function hasPhoto(): bool
    {
        return $this->photo_path !== null;
    }

    /** The photograph as it was stored, or null when it is not on the disk. */
    public function photo(): ?string
    {
        if ($this->photo_path === null) {
            return null;
        }

        return Storage::disk()->get($this->photo_path);
    }

    /** Stores the photograph on the default disk, replacing any earlier one. */
    public function keepPhoto(Image $image): void
    {
        $previous = $this->photo_path;

        $path = 'cover-drafts/'.Str::lower((string) Str::ulid()).'.'.$image->extension();

        Storage::disk()->put($path, $image->binary);

        $this->photo_path = $path;

        if ($previous !== null) {
            Storage::disk()->delete($previous);
        }
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function wasUnreadable(): bool
    {
        return $this->read_failure !== null;
    }

    public function hasFailed(): bool
    {
        return $this->failed_at !== null;
    }

    public function recordReading(CoverReading $reading): void
    {
        $this->forceFill([
            'title' => $reading->title,
            'authors' => $reading->authors,
            'publisher' => $reading->publisher,
            'year' => $reading->year,
            'isbn' => $reading->isbn?->value(),
            'from_source' => $reading->fromSource,
            'read_at' => now(),
            'read_failure' => null,
        ])->save();
    }

    public function recordUnreadable(string $reason): void
    {
        $this->forceFill([
            'read_at' => now(),
            'read_failure' => Str::limit(Str::squish($reason), 250),
        ])->save();
    }

    public function markFailed(string $reason): void
    {
        $this->forceFill([
            'failed_at' => now(),
            'failure' => Str::limit(Str::squish($reason), 250),
        ])->save();
    }

    public function clearFailure(): void
    {
        $this->forceFill(['failed_at' => null, 'failure' => null])->save();
    }

    public function isbn(): ?Isbn
    {
        return $this->isbn === null ? null : Isbn::from($this->isbn);
    }
}
