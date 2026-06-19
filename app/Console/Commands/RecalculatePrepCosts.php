<?php

namespace App\Console\Commands;

use App\Services\PrepCostService;
use Illuminate\Console\Command;

class RecalculatePrepCosts extends Command
{
    protected $signature = 'preps:recalculate-costs';

    protected $description = 'Recalculate stored prep-item costs from current ingredient prices (all companies)';

    public function handle(PrepCostService $service): int
    {
        $this->info('Recalculating prep-item costs from current ingredient prices...');

        $updated = $service->recalculateAll();

        $this->info("Done. {$updated} prep-item cost(s) updated.");

        return self::SUCCESS;
    }
}
