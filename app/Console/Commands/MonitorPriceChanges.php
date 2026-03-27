<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\PriceMonitoringService;
use Illuminate\Console\Command;

class MonitorPriceChanges extends Command
{
    protected $signature = 'price:monitor';
    protected $description = 'Auto-detect ingredient price changes across all companies';

    public function handle(): int
    {
        $companies = Company::where('is_active', true)->get();
        $totalAlerts = 0;

        foreach ($companies as $company) {
            $count = PriceMonitoringService::autoDetectChanges($company);
            if ($count > 0) {
                $this->info("{$company->name}: {$count} price change(s) detected.");
                $totalAlerts += $count;
            }
        }

        $this->info("Done. {$totalAlerts} total notification(s) created.");

        return self::SUCCESS;
    }
}
