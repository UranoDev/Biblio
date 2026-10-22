<?php

namespace App\Jobs;

use App\Covers\Image;
use App\Covers\InvalidImage;
use App\Drive\DriveFile;
use App\Drive\DriveUnavailable;
use App\Drive\GoogleDrive;
use App\Models\CoverDraft;
use App\Models\DriveImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Turns every photograph in the Drive folder into a CoverDraft and moves it to
 * the processed folder (BIB-74).
 *
 * The draft is created before the photograph is moved, and the Drive id is
 * unique: if the move fails, the next pass finds the draft already there and
 * only tries the move again. A photograph that could not be fetched leaves a
 * draft without a photo, which the next pass fetches again.
 *
 * It runs every few minutes from the scheduler. A pass that could not even list
 * the folder —the key, the sharing— is written down on the DriveImport instead
 * of failing the job: failing it every five minutes would bury the list of
 * failed jobs.
 */
class ImportDrivePhotos implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** The image types that can be used as they were uploaded. */
    private const USABLE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public int $tries = 1;

    /** How long a pass holds the lock, so two passes never meet. */
    public int $uniqueFor = 900;

    public function handle(GoogleDrive $drive): void
    {
        if (! GoogleDrive::isConfigured()) {
            return;
        }

        $inbox = (string) config('drive.folder');
        $processed = (string) config('drive.processed_folder');

        try {
            $files = $drive->images($inbox);
        } catch (DriveUnavailable $failure) {
            DriveImport::recordFailure($failure->getMessage());

            return;
        }

        foreach ($files as $file) {
            $draft = CoverDraft::query()->firstOrNew(
                ['drive_file_id' => $file->id],
                ['drive_file_name' => $file->name],
            );

            if (! $draft->hasPhoto() && ! $this->fetch($drive, $file, $draft)) {
                continue;
            }

            try {
                $drive->move($file->id, $inbox, $processed);
            } catch (DriveUnavailable $failure) {
                $draft->markFailed('Moving it to the processed folder failed: '.$failure->getMessage());

                continue;
            }

            if ($draft->hasFailed()) {
                $draft->clearFailure();
            }
        }

        DriveImport::recordPass();
    }

    /** Stores the photograph on the draft and queues its reading. */
    private function fetch(GoogleDrive $drive, DriveFile $file, CoverDraft $draft): bool
    {
        try {
            $image = Image::from($this->binaryOf($drive, $file));
        } catch (DriveUnavailable|InvalidImage $failure) {
            $draft->markFailed('Fetching it from Drive failed: '.$failure->getMessage());

            return false;
        }

        $draft->keepPhoto($image);
        $draft->failed_at = null;
        $draft->failure = null;
        $draft->save();

        ReadCoverDraft::dispatch($draft);

        return true;
    }

    /**
     * The original when it can be a Cover as it is; otherwise the JPEG Drive
     * renders of it. That is what turns a HEIC, which GD does not read, and a
     * photograph heavier than a Cover may weigh into one that fits.
     */
    private function binaryOf(GoogleDrive $drive, DriveFile $file): string
    {
        $usable = in_array($file->mimeType, self::USABLE_TYPES, true)
            && $file->size !== null
            && $file->size <= (int) config('covers.max_weight', 5 * 1024 * 1024);

        return $usable
            ? $drive->content($file)
            : $drive->thumbnail($file, (int) config('drive.thumbnail_size', 1600));
    }
}
