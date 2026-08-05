<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Deactivate staff whose resignation date has passed.
 *
 * A resignation is usually recorded in advance — someone gives notice on the
 * 5th to leave on the 31st — so the row cannot be switched off at the moment
 * it is saved without removing them from the very month their final pay
 * depends on. This lands it the morning after the leaving date, which is what
 * keeps them off the next period's attendance grid, duty roster and service
 * charge pool.
 *
 * Idempotent: the query only ever sees rows still flagged active, so a missed
 * night is caught by the next run.
 */
class ApplyDueResignations extends Command
{
    protected $signature = 'hr:apply-resignations {--dry-run}';

    protected $description = 'Deactivate employees whose resignation date has passed';

    public function handle(): int
    {
        // Runs unattended with no authenticated user, so the company scope is
        // dropped by hand — every row carries its own company_id anyway.
        $due = Employee::withoutGlobalScopes()
            ->resignationDue()
            ->orderBy('employment_status_date')
            ->get();

        if ($due->isEmpty()) {
            $this->info('No resignations due.');
            return self::SUCCESS;
        }

        $done = 0;

        foreach ($due as $employee) {
            $left = $employee->employment_status_date->format('Y-m-d');

            if ($this->option('dry-run')) {
                $this->line("would deactivate #{$employee->id} {$employee->name} (left {$left})");
                continue;
            }

            try {
                // One tenant's bad row must not stop everyone else's
                // resignations from landing, so each is handled on its own.
                $employee->update(['is_active' => false]);
                $done++;
            } catch (\Throwable $e) {
                Log::error('Resignation failed to apply', [
                    'employee_id' => $employee->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $this->info($this->option('dry-run')
            ? "{$due->count()} resignation(s) due."
            : "Deactivated {$done} resigned employee(s).");

        return self::SUCCESS;
    }
}
