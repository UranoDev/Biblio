<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * When the Drive folder was last looked at, and why the last look failed
 * (BIB-74).
 *
 * A single row. It is what lets the screen of CoverDrafts say whether the
 * scheduler is reaching the folder at all, which nothing else would show: a
 * folder nobody can read and a folder with nothing new in it look the same.
 *
 * @property int $id
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $last_failed_at
 * @property string|null $last_failure
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DriveImport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
            'last_failed_at' => 'datetime',
        ];
    }

    /** The row, or null while the folder has never been looked at. */
    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }

    public static function recordPass(): void
    {
        self::row()->forceFill([
            'last_checked_at' => now(),
            'last_failed_at' => null,
            'last_failure' => null,
        ])->save();
    }

    public static function recordFailure(string $reason): void
    {
        self::row()->forceFill([
            'last_failed_at' => now(),
            'last_failure' => Str::limit(Str::squish($reason), 250),
        ])->save();
    }

    public function hasFailed(): bool
    {
        return $this->last_failed_at !== null;
    }

    private static function row(): self
    {
        return self::current() ?? new self;
    }
}
