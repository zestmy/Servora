<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Retention pruning for the audit trail. Deliberately manual and explicit:
 * nothing is deleted unless an operator passes --days or --before, so the
 * default posture is "keep everything" (compliance-friendly). Runs against the
 * raw query builder because the AuditLog model is intentionally immutable.
 */
class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune
        {--days= : Delete entries older than this many days}
        {--before= : Delete entries created before this date (Y-m-d)}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Prune old audit_logs entries (retention). Keeps everything unless a cutoff is given.';

    public function handle(): int
    {
        $cutoff = null;

        if ($this->option('days') !== null) {
            $cutoff = Carbon::today()->subDays((int) $this->option('days'));
        } elseif ($this->option('before')) {
            $cutoff = Carbon::parse($this->option('before'))->startOfDay();
        }

        if (! $cutoff) {
            $this->error('Refusing to prune: pass --days=<n> or --before=<Y-m-d> to set a cutoff.');
            return self::FAILURE;
        }

        $count = DB::table('audit_logs')->where('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info("No audit entries older than {$cutoff->toDateString()}.");
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Permanently delete {$count} audit entries created before {$cutoff->toDateString()}?")) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $deleted = DB::table('audit_logs')->where('created_at', '<', $cutoff)->delete();
        $this->info("Pruned {$deleted} audit entries older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
