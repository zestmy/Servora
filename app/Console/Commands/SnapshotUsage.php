<?php

namespace App\Console\Commands;

use App\Services\UsageTrackingService;
use Illuminate\Console\Command;

class SnapshotUsage extends Command
{
    protected $signature = 'usage:snapshot';
    protected $description = 'Snapshot current usage counts for all active companies';

    public function handle(): int
    {
        $count = app(UsageTrackingService::class)->snapshotAll();
        $this->info("Snapshotted usage for {$count} companies.");

        return self::SUCCESS;
    }
}
