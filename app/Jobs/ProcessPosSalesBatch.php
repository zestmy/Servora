<?php

namespace App\Jobs;

use App\Models\PosSalesBatch;
use App\Scopes\CompanyScope;
use App\Services\Sales\PosSalesBatchProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Parses and applies one uploaded POS sales batch.
 *
 * Carries the batch ID rather than the model: the job may sit in the queue
 * past interesting state changes, and the processor re-reads fresh state
 * anyway. Queued (not sync) because parsing an Excel file through
 * PhpSpreadsheet is exactly the work the 2 GB box must not do inside a
 * request.
 */
class ProcessPosSalesBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> Seconds between retries. */
    public array $backoff = [60, 300];

    public function __construct(public int $batchId)
    {
    }

    public function handle(PosSalesBatchProcessor $processor): void
    {
        $batch = PosSalesBatch::withoutGlobalScope(CompanyScope::class)->find($this->batchId);

        // Gone (pruned) or already handled by a human via re-apply — either
        // way there is nothing left for the queued copy of this work to do.
        if (! $batch || ! in_array($batch->status, [
            PosSalesBatch::STATUS_RECEIVED,
            PosSalesBatch::STATUS_PROCESSING,
        ], true)) {
            return;
        }

        $processor->process($batch);
    }

    public function failed(Throwable $e): void
    {
        PosSalesBatch::withoutGlobalScope(CompanyScope::class)
            ->whereKey($this->batchId)
            ->whereIn('status', [PosSalesBatch::STATUS_RECEIVED, PosSalesBatch::STATUS_PROCESSING])
            ->update([
                'status'       => PosSalesBatch::STATUS_FAILED,
                'error'        => mb_substr($e->getMessage(), 0, 2000),
                'processed_at' => now(),
            ]);
    }
}
