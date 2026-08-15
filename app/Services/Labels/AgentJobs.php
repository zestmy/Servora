<?php

namespace App\Services\Labels;

use App\Models\LabelPrint;
use App\Models\LabelPrintBatch;
use App\Models\PrintJob;
use App\Scopes\CompanyScope;

/**
 * The two writes that settle an agent job's fate, shared between the ack
 * endpoint and the expiry sweep so they cannot disagree about what settling
 * means.
 *
 * Both propagate to label_prints with the reconciler's exact state-write:
 * drop CompanyScope (the sweep runs with no authenticated user), filter to
 * PENDING_STATUSES so a settled row is never overwritten. The batch is
 * found through driver_job_id — the same per-batch job id PrintNode uses,
 * which is why nothing above the driver had to change.
 */
class AgentJobs
{
    /**
     * An agent reported what happened. 'done' means the document was handed
     * to the OS spooler and the print command exited cleanly — the same
     * epistemics as PrintNode's 'done', no stronger.
     */
    public function finish(PrintJob $job, string $status, ?string $error = null): void
    {
        if ($job->isTerminal()) {
            // A crashed agent can re-ack a job it printed before dying.
            // First answer wins; the record must not flap.
            return;
        }

        $job->finish($status, $error);

        $this->propagate($job, $status);
    }

    /**
     * Expire every job past its TTL that never reached a terminal state.
     *
     * Runs unattended from the scheduler across all companies, so no scope
     * and no throwing — the same two rules LabelJobReconciler lives by.
     *
     * @return int  jobs expired
     */
    public function expireOverdue(): int
    {
        $overdue = PrintJob::withoutGlobalScope(CompanyScope::class)
            ->whereNotIn('status', PrintJob::TERMINAL_STATUSES)
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($overdue as $job) {
            $job->finish('expired');
            $this->propagate($job, 'expired');
        }

        return $overdue->count();
    }

    /**
     * Delete settled job rows old enough that nobody is debugging them.
     * Payloads are already null (finish() drops them); this removes the
     * skeletons.
     *
     * @return int  rows deleted
     */
    public function prune(int $keepDays = PrintJob::KEEP_DAYS): int
    {
        return PrintJob::withoutGlobalScope(CompanyScope::class)
            ->whereIn('status', PrintJob::TERMINAL_STATUSES)
            ->where('created_at', '<', now()->subDays($keepDays))
            ->delete();
    }

    private function propagate(PrintJob $job, string $status): void
    {
        $batch = LabelPrintBatch::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $job->company_id)
            ->where('driver', 'agent')
            ->where('driver_job_id', (string) $job->id)
            ->first();

        if (! $batch) {
            return;
        }

        LabelPrint::withoutGlobalScope(CompanyScope::class)
            ->where('batch_id', $batch->id)
            ->whereIn('status', LabelPrint::PENDING_STATUSES)
            ->update(['status' => $status]);
    }
}
