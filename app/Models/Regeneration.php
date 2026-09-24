<?php

namespace App\Models;

use App\Enums\RegenerationTarget;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

/**
 * A mass regeneration: the run that asks the AI again for the Categories and
 * the Descriptions of the Books already registered (BIB-21).
 *
 * The row does not carry the progress. The queue already keeps it in
 * `job_batches`, and copying it here would mean two numbers that can disagree;
 * what the row carries is which batch it was, so that the screen can find the
 * progress again after a reload.
 *
 * @property int $id
 * @property string|null $batch_id
 * @property RegenerationTarget $target
 * @property bool $only_previous
 * @property int $total
 * @property int $kept
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Regeneration extends Model
{
    protected $table = 'regenerations';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target' => RegenerationTarget::class,
            'only_previous' => 'boolean',
        ];
    }

    /** The last regeneration launched, or null while none has been. */
    public static function last(): ?self
    {
        return static::query()->latest('id')->first();
    }

    /**
     * The batch of the queue this regeneration is, or null when the queue no
     * longer knows about it — it was pruned, or nothing was ever queued because
     * no Book matched.
     */
    public function batch(): ?Batch
    {
        return $this->batch_id === null ? null : Bus::findBatch($this->batch_id);
    }

    /** Whether there are still Books of this run waiting their turn. */
    public function isRunning(): bool
    {
        $batch = $this->batch();

        return $batch instanceof Batch && ! $batch->finished();
    }

    /** How many Books of the run are through, counting the ones that failed. */
    public function done(): int
    {
        $batch = $this->batch();

        return $batch instanceof Batch
            ? $batch->totalJobs - $batch->pendingJobs
            : $this->total;
    }

    /** How many are still in line. */
    public function remaining(): int
    {
        $batch = $this->batch();

        return $batch instanceof Batch ? $batch->pendingJobs : 0;
    }

    /** How many the queue gave up on. Each one says so on its own sheet. */
    public function failed(): int
    {
        $batch = $this->batch();

        return $batch instanceof Batch ? $batch->failedJobs : 0;
    }
}
