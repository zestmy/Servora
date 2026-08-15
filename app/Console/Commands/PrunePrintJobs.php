<?php

namespace App\Console\Commands;

use App\Models\PrintJob;
use App\Services\Labels\AgentJobs;
use Illuminate\Console\Command;

/**
 * Deletes settled print-agent job rows past their retention window.
 *
 * Payloads (the rendered PDFs — the actual growth risk) are already nulled
 * the moment a job reaches a terminal status; this removes the skeleton
 * rows once nobody is debugging them. Same shape as sop:prune-exports:
 * lifecycle handles growth, not storage choice.
 */
class PrunePrintJobs extends Command
{
    protected $signature = 'labels:prune-print-jobs
        {--days= : Delete terminal jobs older than this many days (default: PrintJob::KEEP_DAYS)}';

    protected $description = 'Delete settled print-agent jobs past their retention window.';

    public function handle(AgentJobs $jobs): int
    {
        $days = (int) ($this->option('days') ?: PrintJob::KEEP_DAYS);

        $deleted = $jobs->prune($days);

        $this->info("Pruned {$deleted} settled print job(s).");

        return self::SUCCESS;
    }
}
