<?php

namespace App\Console\Commands;

use App\Services\Billing\ExchangeRates;
use Illuminate\Console\Command;

/**
 * Pull Bank Negara's ringgit rates, so marketing pages price visitors off a
 * recent rate without ever waiting on BNM themselves. Checkout fetches its
 * own fresh rate regardless.
 */
class RefreshExchangeRates extends Command
{
    protected $signature = 'billing:refresh-rates';
    protected $description = "Fetch Bank Negara Malaysia's exchange rates for subscription pricing";

    public function handle(ExchangeRates $rates): int
    {
        $count = $rates->refresh();
        $count > 0 ? $this->info("Stored {$count} rates.") : $this->warn('No rates stored; BNM could not be reached.');

        return self::SUCCESS;
    }
}
