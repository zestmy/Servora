<?php

namespace App\Console\Commands;

use App\Models\SopExport;
use Illuminate\Console\Command;

/**
 * Clears out rendered SOP export PDFs once they are no longer worth keeping.
 *
 * These are big — the full catalogue is ~12 MB — and every rebuild writes
 * another one, so on a box with a modest disk they would accumulate faster
 * than anything else the app writes.
 *
 * Only the FILE goes. The row stays as history, and the panel already
 * distinguishes "completed but the file has been cleared" from "never
 * finished" via SopExport::isDownloadable(), so a pruned export reads as an
 * offer to rebuild rather than an error.
 *
 * Also fails off any run that died without reporting — a worker killed by a
 * deploy restart or the OOM killer never gets to write its own status, and
 * without this the row would sit "processing" forever and keep the panel's
 * button disabled.
 */
class PruneSopExports extends Command
{
    protected $signature = 'sop:prune-exports
        {--hours= : Delete files older than this many hours (default: SopExport::KEEP_FILE_HOURS)}';

    protected $description = 'Delete stored SOP export PDFs past their retention window and fail off abandoned runs.';

    public function handle(): int
    {
        $hours  = (int) ($this->option('hours') ?: SopExport::KEEP_FILE_HOURS);
        $cutoff = now()->subHours($hours);

        $files = 0;
        SopExport::whereNotNull('file_path')
            ->where('created_at', '<', $cutoff)
            ->each(function (SopExport $export) use (&$files) {
                $export->forgetFile();
                $files++;
            });

        // Anything still unfinished well past the point a live worker would
        // have reported. Uses the model's own staleness window so the panel
        // and the pruner cannot disagree about what counts as dead.
        $abandoned = SopExport::whereIn('status', [SopExport::STATUS_QUEUED, SopExport::STATUS_PROCESSING])
            ->where('created_at', '<', now()->subMinutes(SopExport::STALE_AFTER_MINUTES))
            ->update([
                'status'      => SopExport::STATUS_FAILED,
                'error'       => 'The export stopped before it finished — the worker may have been restarted.',
                'phase'       => null,
                'finished_at' => now(),
            ]);

        $this->info("Pruned {$files} export file(s); failed off {$abandoned} abandoned run(s).");

        return self::SUCCESS;
    }
}
