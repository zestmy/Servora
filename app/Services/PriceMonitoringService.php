<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientPriceHistory;
use App\Models\PriceChangeNotification;
use App\Models\SupplierIngredient;
use App\Models\SupplierPriceAlert;
use App\Models\SupplierProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PriceMonitoringService
{
    /**
     * Auto-detect price changes for all supplier-ingredient relationships within a company.
     * Creates PriceChangeNotification records for changes exceeding the company's threshold.
     */
    public static function autoDetectChanges(Company $company): int
    {
        $threshold = floatval($company->price_alert_threshold ?? 5.0);
        if ($threshold <= 0) return 0;

        // Get all supplier_ingredients for this company's suppliers
        $supplierIds = $company->suppliers()->pluck('id');
        if ($supplierIds->isEmpty()) return 0;

        $records = SupplierIngredient::whereIn('supplier_id', $supplierIds)
            ->where('last_cost', '>', 0)
            ->get();

        $created = 0;

        foreach ($records as $si) {
            // Get the previous price from history (second-most-recent entry)
            $history = IngredientPriceHistory::where('ingredient_id', $si->ingredient_id)
                ->where('supplier_id', $si->supplier_id)
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->limit(2)
                ->pluck('cost');

            if ($history->count() < 2) continue;

            $currentPrice = floatval($history->first());
            $previousPrice = floatval($history->last());

            if ($previousPrice <= 0) continue;

            $changePct = round((($currentPrice - $previousPrice) / $previousPrice) * 100, 2);

            if (abs($changePct) < $threshold) continue;

            // Check if we already notified about this exact change today
            $exists = PriceChangeNotification::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('ingredient_id', $si->ingredient_id)
                ->where('supplier_id', $si->supplier_id)
                ->where('new_price', $currentPrice)
                ->where('old_price', $previousPrice)
                ->exists();

            if ($exists) continue;

            PriceChangeNotification::create([
                'company_id'     => $company->id,
                'ingredient_id'  => $si->ingredient_id,
                'supplier_id'    => $si->supplier_id,
                'old_price'      => $previousPrice,
                'new_price'      => $currentPrice,
                'change_percent' => $changePct,
                'change_amount'  => round($currentPrice - $previousPrice, 4),
                'direction'      => $changePct > 0 ? 'increase' : 'decrease',
                'detected_at'    => now(),
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Get price comparison data for an ingredient across all suppliers.
     */
    public static function compareSupplierPrices(int $ingredientId): Collection
    {
        $suppliers = SupplierIngredient::where('ingredient_id', $ingredientId)
            ->with('supplier')
            ->orderBy('last_cost')
            ->get()
            ->map(function ($si) use ($ingredientId) {
                $priceHistory = IngredientPriceHistory::where('ingredient_id', $ingredientId)
                    ->where('supplier_id', $si->supplier_id)
                    ->orderByDesc('effective_date')
                    ->limit(10)
                    ->get();

                $previousCost = $priceHistory->skip(1)->first()?->cost;
                $changePct = ($previousCost && $previousCost > 0)
                    ? round((($si->last_cost - $previousCost) / $previousCost) * 100, 2)
                    : null;

                return [
                    'supplier_id'   => $si->supplier_id,
                    'supplier_name' => $si->supplier?->name ?? '—',
                    'last_cost'     => floatval($si->last_cost),
                    'uom'           => $si->uom?->abbreviation ?? '',
                    'is_preferred'  => $si->is_preferred,
                    'pack_size'     => floatval($si->pack_size ?? 1),
                    'change_pct'    => $changePct,
                    'history'       => $priceHistory->map(fn ($h) => [
                        'date' => $h->effective_date->format('d M Y'),
                        'cost' => floatval($h->cost),
                    ])->toArray(),
                ];
            });

        return $suppliers;
    }

    /**
     * Check all active price alerts and trigger notifications.
     * Intended to run as a scheduled command.
     */
    public static function checkAlerts(): array
    {
        $triggered = [];

        $alerts = SupplierPriceAlert::with(['ingredient', 'supplier'])
            ->where('is_active', true)
            ->get();

        foreach ($alerts as $alert) {
            $result = self::evaluateAlert($alert);
            if ($result['triggered']) {
                $triggered[] = $result;
                $alert->update(['last_triggered_at' => now()]);

                Log::info('Price alert triggered', [
                    'alert_id'    => $alert->id,
                    'ingredient'  => $alert->ingredient?->name,
                    'supplier'    => $alert->supplier?->name,
                    'type'        => $alert->alert_type,
                    'message'     => $result['message'],
                ]);
            }
        }

        return $triggered;
    }

    private static function evaluateAlert(SupplierPriceAlert $alert): array
    {
        $ingredientId = $alert->ingredient_id;
        $supplierId = $alert->supplier_id;

        // Get current and previous price
        $history = IngredientPriceHistory::where('ingredient_id', $ingredientId)
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->orderByDesc('effective_date')
            ->limit(2)
            ->get();

        if ($history->count() < 2) {
            return ['triggered' => false, 'message' => 'Not enough price history.'];
        }

        $currentPrice = floatval($history->first()->cost);
        $previousPrice = floatval($history->last()->cost);

        if ($previousPrice <= 0) {
            return ['triggered' => false, 'message' => 'Previous price is zero.'];
        }

        $changePct = (($currentPrice - $previousPrice) / $previousPrice) * 100;
        $changeAmt = $currentPrice - $previousPrice;

        $triggered = false;
        $message = '';

        switch ($alert->alert_type) {
            case 'increase':
                if ($changePct > 0) {
                    if ($alert->threshold_percent && $changePct >= $alert->threshold_percent) {
                        $triggered = true;
                        $message = sprintf(
                            '%s price increased by %.1f%% (%.2f → %.2f)',
                            $alert->ingredient?->name, $changePct, $previousPrice, $currentPrice
                        );
                    } elseif ($alert->threshold_amount && $changeAmt >= $alert->threshold_amount) {
                        $triggered = true;
                        $message = sprintf(
                            '%s price increased by %.2f (%.2f → %.2f)',
                            $alert->ingredient?->name, $changeAmt, $previousPrice, $currentPrice
                        );
                    }
                }
                break;

            case 'decrease':
                if ($changePct < 0) {
                    $absPct = abs($changePct);
                    if ($alert->threshold_percent && $absPct >= $alert->threshold_percent) {
                        $triggered = true;
                        $message = sprintf(
                            '%s price decreased by %.1f%% (%.2f → %.2f)',
                            $alert->ingredient?->name, $absPct, $previousPrice, $currentPrice
                        );
                    }
                }
                break;

            case 'threshold':
                if ($alert->threshold_amount && $currentPrice > $alert->threshold_amount) {
                    $triggered = true;
                    $message = sprintf(
                        '%s price (%.2f) exceeds threshold (%.2f)',
                        $alert->ingredient?->name, $currentPrice, $alert->threshold_amount
                    );
                }
                break;
        }

        return ['triggered' => $triggered, 'message' => $message, 'change_pct' => $changePct];
    }

    /**
     * Get ingredients with significant recent price changes.
     */
    public static function getRecentPriceChanges(int $days = 30, float $minChangePct = 5.0): Collection
    {
        $since = now()->subDays($days);

        return IngredientPriceHistory::with(['ingredient', 'supplier'])
            ->where('effective_date', '>=', $since)
            ->orderByDesc('effective_date')
            ->get()
            ->groupBy('ingredient_id')
            ->filter(function ($history) use ($minChangePct) {
                if ($history->count() < 2) return false;
                $latest = floatval($history->first()->cost);
                $earliest = floatval($history->last()->cost);
                if ($earliest <= 0) return false;
                $pct = abs(($latest - $earliest) / $earliest * 100);
                return $pct >= $minChangePct;
            })
            ->map(function ($history) {
                $latest = $history->first();
                $earliest = $history->last();
                $pct = round(($latest->cost - $earliest->cost) / $earliest->cost * 100, 2);
                return [
                    'ingredient_id'   => $latest->ingredient_id,
                    'ingredient_name' => $latest->ingredient?->name,
                    'supplier_name'   => $latest->supplier?->name,
                    'old_price'       => floatval($earliest->cost),
                    'new_price'       => floatval($latest->cost),
                    'change_pct'      => $pct,
                    'date'            => $latest->effective_date->format('d M Y'),
                ];
            })
            ->values();
    }
}
