<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The URL of the public Amazon wishlist and when it was last synced.
 *
 * It is a single row: configuring a new URL replaces the one there is. The
 * sync timestamp lets the settings screen say when the last run happened and
 * confirm that the scheduler is working.
 *
 * @property int $id
 * @property string $url
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WishlistSetting extends Model
{
    protected $table = 'wishlist_settings';

    protected $guarded = ['id'];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    /**
     * The configured wishlist, or null while none has been set.
     */
    public static function configured(): ?self
    {
        return static::query()->latest('id')->first();
    }

    /**
     * Store the URL, replacing the previous one.
     */
    public static function configure(string $url): self
    {
        $setting = static::configured() ?? new self;

        $setting->url = $url;
        $setting->save();

        return $setting;
    }

    /**
     * Record that a sync just completed successfully.
     */
    public function markSynced(): void
    {
        $this->last_synced_at = now();
        $this->save();
    }
}
