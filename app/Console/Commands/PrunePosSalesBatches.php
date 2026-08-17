<?php

namespace App\Console\Commands;

use App\Models\PosSalesBatch;
use App\Scopes\CompanyScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Retention for POS sales batches.
 *
 * Two clocks: the raw upload files go after KEEP_FILES_DAYS (they exist for
 * re-apply and debugging, and disk on the box is small), and batch ROWS that
 * never applied go after KEEP_UNAPPLIED_DAYS. Applied rows stay forever —
 * they are the audit trail sales_records.pos_sales_batch_id points into.
 */
class PrunePosSalesBatches extends Command
{
    protected $signature = 'pos-agent:prune-batches';

    protected $description = 'Delete old POS batch upload files and long-dead unapplied batch rows';

    public function handle(): int
    {
        $files = 0;

        PosSalesBatch::withoutGlobalScope(CompanyScope::class)
            ->whereNotNull('file_path')
            ->where('created_at', '<', now()->subDays(PosSalesBatch::KEEP_FILES_DAYS))
            ->orderBy('id')
            ->chunkById(100, function ($batches) use (&$files) {
                foreach ($batches as $batch) {
                    Storage::disk('local')->delete($batch->file_path);
                    $batch->forceFill(['file_path' => null])->saveQuietly();
                    $files++;
                }
            });

        $rows = PosSalesBatch::withoutGlobalScope(CompanyScope::class)
            ->whereNotIn('status', [PosSalesBatch::STATUS_APPLIED, PosSalesBatch::STATUS_SUPERSEDED])
            ->where('created_at', '<', now()->subDays(PosSalesBatch::KEEP_UNAPPLIED_DAYS))
            ->delete();

        $this->info("Pruned {$files} stored file(s), {$rows} unapplied batch row(s).");

        return self::SUCCESS;
    }
}
