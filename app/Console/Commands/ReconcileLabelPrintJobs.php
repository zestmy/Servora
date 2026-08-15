<?php

namespace App\Console\Commands;

use App\Services\Labels\AgentJobs;
use App\Services\Labels\LabelJobReconciler;
use Illuminate\Console\Command;

/**
 * Asks PrintNode what happened to jobs we queued, and expires agent jobs
 * nobody collected.
 *
 * The asymmetry is the point: PrintNode has to be POLLED for outcomes,
 * whereas a print agent pushes done/error itself — the only sweep its jobs
 * need is the one for a PC that never came to collect at all.
 *
 * Browser-printed labels are untouched: nothing reports back from a browser,
 * so 'sent' is as much as can ever be known about them.
 */
class ReconcileLabelPrintJobs extends Command
{
    protected $signature = 'labels:reconcile-jobs {--days=7 : How far back to look}';

    protected $description = 'Update queued PrintNode label jobs with their actual outcome and expire uncollected agent jobs';

    public function handle(LabelJobReconciler $reconciler, AgentJobs $agentJobs): int
    {
        $expired = $agentJobs->expireOverdue();

        if ($expired > 0) {
            $this->info("Expired {$expired} uncollected agent job(s).");
        }

        $stats = $reconciler->reconcile((int) $this->option('days'));

        $this->info(sprintf(
            'Checked %d batch(es) across %d company(ies); updated %d label row(s).',
            $stats['checked'],
            $stats['companies'],
            $stats['updated'],
        ));

        if ($stats['failures'] > 0) {
            // Not a failure exit: the other companies were reconciled fine,
            // and a non-zero code would make the scheduler shout every run
            // for one tenant's bad key.
            $this->warn($stats['failures'] . ' company(ies) could not be reached — see the log.');
        }

        return self::SUCCESS;
    }
}
