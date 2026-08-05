<?php

namespace App\Console\Commands;

use App\Models\SalaryRevision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Write approved salary revisions onto their employee once the effective date
 * arrives.
 *
 * A raise agreed in March to start in April must not take effect in March, so
 * approval records intent and this lands it. Without this command a
 * future-dated revision would sit approved forever and the employee would
 * quietly stay on the old figure.
 */
class ApplyDueSalaryRevisions extends Command
{
    protected $signature = 'salary:apply-revisions {--dry-run}';

    protected $description = 'Apply approved salary revisions that have reached their effective date';

    public function handle(): int
    {
        // Runs unattended with no authenticated user, so the company scope is
        // dropped by hand — every row carries its own company_id anyway.
        $due = SalaryRevision::withoutGlobalScopes()
            ->where('status', SalaryRevision::APPROVED)
            ->whereNull('applied_at')
            ->whereDate('effective_on', '<=', now()->toDateString())
            ->orderBy('effective_on')
            ->get();

        if ($due->isEmpty()) {
            $this->info('No salary revisions due.');
            return self::SUCCESS;
        }

        $applied = 0;
        $failed  = 0;

        foreach ($due as $revision) {
            if ($this->option('dry-run')) {
                $this->line("would apply #{$revision->id}: employee {$revision->employee_id} → {$revision->to_amount}");
                continue;
            }

            try {
                // One tenant's deleted employee must not stop everyone else's
                // raises from landing, so each row is handled on its own.
                $revision->applyIfDue() ? $applied++ : $failed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Salary revision failed to apply', [
                    'revision_id' => $revision->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $this->info("Applied {$applied} salary revision(s)." . ($failed ? " {$failed} could not be applied." : ''));

        return self::SUCCESS;
    }
}
