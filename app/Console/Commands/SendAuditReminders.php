<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Audits\AuditReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Email whoever needs to know that an audit or a corrective action is overdue.
 *
 * Runs HOURLY; the service sends only in the 08:00 hour of each company's own
 * timezone, and the audit_reminders table makes a repeat run in that hour a
 * no-op — so this is safe to run by hand, and `--force` is for testing the
 * content at any time of day.
 */
class SendAuditReminders extends Command
{
    protected $signature = 'audits:send-reminders
                            {--force : Send now regardless of the hour}
                            {--company= : Only this company id}
                            {--dry-run : Count what would be sent, send nothing}';

    protected $description = 'Send daily reminders for overdue scheduled audits and corrective actions';

    public function handle(AuditReminderService $reminders): int
    {
        $companies = Company::withoutGlobalScopes()
            ->where('is_active', true)
            ->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->get();

        $auditors = $owners = 0;

        foreach ($companies as $company) {
            try {
                $result = $reminders->sendForCompany($company, (bool) $this->option('force'), (bool) $this->option('dry-run'));
            } catch (\Throwable $e) {
                // One tenant's bad data must not stop the others' reminders.
                Log::error('Audit reminders failed for a company', ['company_id' => $company->id, 'error' => $e->getMessage()]);
                $this->error("#{$company->id} {$company->name}: {$e->getMessage()}");
                continue;
            }

            if ($result['skipped']) {
                $this->line("#{$company->id} {$company->name}: skipped ({$result['skipped']})", verbosity: 'v');
                continue;
            }

            $auditors += $result['auditors'];
            $owners   += $result['owners'];

            if ($result['auditors'] || $result['owners']) {
                $this->info("#{$company->id} {$company->name}: {$result['auditors']} auditor digest(s), {$result['owners']} owner reminder(s)"
                    . ($this->option('dry-run') ? ' (dry run)' : ''));
            }
        }

        $this->info("Done: {$auditors} auditor digest(s), {$owners} owner reminder(s)" . ($this->option('dry-run') ? ' would be sent.' : ' queued.'));

        return self::SUCCESS;
    }
}
