<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientPriceHistory;
use App\Models\SupplierIngredient;
use App\Models\SupplierPriceAlert;
use App\Models\SupplierProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PriceMonitoringService
{
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
