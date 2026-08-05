<?php

namespace App\Console\Commands;

use App\Jobs\ResolveClockEventAddress;
use App\Models\ClockEvent;
use App\Models\ClockSetting;
use Illuminate\Console\Command;

/**
 * Queue address lookups for punches recorded before reverse geocoding was
 * switched on, or missed while a provider was down.
 *
 * Manual, not scheduled. Backfilling a year of punches is thousands of
 * requests at a third party, which is a decision to take deliberately with a
 * `--limit` in hand, not something that should quietly start one night.
 */
class BackfillClockAddresses extends Command
{
    protected $signature = 'clock:backfill-addresses
                            {--company= : Only this company}
                            {--limit=200 : How many punches to queue}
                            {--dry-run}';

    protected $description = 'Queue reverse-geocoding for clock-in punches that have no address yet';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        // Only companies that have opted in — the setting is the consent, and
        // a backfill must not be a way around it.
        $companyIds = ClockSetting::withoutGlobalScopes()
            ->where('resolve_addresses', true)
            ->when($this->option('company'), fn ($q) => $q->where('company_id', (int) $this->option('company')))
            ->pluck('company_id');

        if ($companyIds->isEmpty()) {
            $this->warn('No company has address resolution switched on.');
            return self::SUCCESS;
        }

        $events = ClockEvent::withoutGlobalScopes()
            ->whereIn('company_id', $companyIds)
            ->whereNotNull('latitude')
            ->whereNull('address_resolved_at')
            ->orderByDesc('happened_at')   // newest first: those get looked at
            ->limit($limit)
            ->pluck('id');

        if ($events->isEmpty()) {
            $this->info('Nothing to backfill.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Would queue {$events->count()} punch(es).");
            return self::SUCCESS;
        }

        foreach ($events as $id) {
            ResolveClockEventAddress::dispatch($id);
        }

        $remaining = ClockEvent::withoutGlobalScopes()
            ->whereIn('company_id', $companyIds)
            ->whereNotNull('latitude')
            ->whereNull('address_resolved_at')
            ->count() - $events->count();

        $this->info("Queued {$events->count()} punch(es)."
            . ($remaining > 0 ? " {$remaining} still without an address — run again to continue." : ''));

        return self::SUCCESS;
    }
}
