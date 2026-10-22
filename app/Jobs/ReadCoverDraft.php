<?php

namespace App\Jobs;

use App\Intake\CoverUnreadable;
use App\Intake\ReadCover;
use App\Models\CoverDraft;
use finfo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Reads the photograph of a CoverDraft the way ManualEntry reads an upload
 * (BIB-74): what the AI read, or the Record of a Source when the photograph
 * carried an ISBN some catalog knows.
 *
 * A photograph that could not be read is not a failed job: the draft keeps it
 * and says so, and the form is typed by hand, the same as on ManualEntry.
 */
class ReadCoverDraft implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** A draft discarded before its turn came has nothing left to read. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public CoverDraft $draft) {}

    public function handle(ReadCover $read): void
    {
        $photo = $this->draft->photo();

        if ($photo === null) {
            return;
        }

        try {
            $reading = $read->readImage($photo, (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($photo));
        } catch (CoverUnreadable $failure) {
            $this->draft->recordUnreadable($failure->getMessage());

            return;
        }

        $this->draft->recordReading($reading);
    }
}
