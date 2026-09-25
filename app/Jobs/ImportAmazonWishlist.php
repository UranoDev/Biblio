<?php

namespace App\Jobs;

use App\Enums\Status;
use App\Intake\RegisterCandidate;
use App\Models\WishlistSetting;
use App\Sources\AmazonWishlist;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Reads the configured Amazon wishlist and registers every book it finds as
 * Wanted, skipping the ones already on the Shelf (RegisterCandidate handles
 * that check by ISBN).
 *
 * This job is dispatched manually from the settings screen and runs
 * automatically once a week from the scheduler. When Amazon does not answer
 * or the URL is not configured, it stops quietly: no exception propagates, and
 * the last-synced timestamp is not updated, which makes the settings screen
 * show that the sync did not complete.
 */
class ImportAmazonWishlist implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function handle(RegisterCandidate $register): void
    {
        $setting = WishlistSetting::configured();

        if (! $setting instanceof WishlistSetting) {
            return;
        }

        $candidates = (new AmazonWishlist)->candidates($setting->url);

        foreach ($candidates as $candidate) {
            $register->register($candidate, Status::Wanted);
        }

        $setting->markSynced();
    }
}
