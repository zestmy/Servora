<?php

namespace App\Services\Billing;

use App\Models\Plan;

/**
 * What a configuration costs (docs/pricing-model.md). Pure arithmetic: no
 * company, no subscription, no credit — CheckoutService adds those.
 *
 *   suite price × outlets
 *   − volume: outlets 6–10 at 10% off, 11–19 at 15% off (marginal: the first
 *     five are always full price); 20+ is quoted by hand
 *   + flat add-ons (none on Full, which includes them; at most two on Basic)
 *   + metered add-ons × max(quantity, minimum)
 *   yearly = 10 × monthly (two months free)
 */
class PriceCalculator
{
    public const ENTERPRISE_FROM = 20;

    private const VOLUME_BANDS = [
        // [first outlet, last outlet, discount]
        [6, 10, 0.10],
        [11, self::ENTERPRISE_FROM - 1, 0.15],
    ];

    /**
     * @param  array<string, int>  $addons  module => quantity (1 for a flat add-on)
     */
    public function quote(Plan $plan, int $outlets, array $addons, string $cycle): Quote
    {
        $fail = fn (string $error) => new Quote([], 0, $cycle, 0, error: $error);

        if (! in_array('basic', (array) $plan->modules, true)) {
            return $fail('Choose the Basic or Full suite.');
        }
        if ($outlets < 1) {
            return $fail('At least one outlet.');
        }
        if ($outlets >= self::ENTERPRISE_FROM) {
            return $fail(self::ENTERPRISE_FROM.' outlets or more is Enterprise pricing — contact us and we will quote it.');
        }

        $catalogue = (array) config('modules.catalogue');
        $unit = (float) $plan->price_monthly;
        $lines = [[
            'label'  => "{$plan->name} suite",
            'detail' => "{$outlets} outlet".($outlets === 1 ? '' : 's').' × RM'.number_format($unit, 0),
            'amount' => $unit * $outlets,
        ]];

        foreach (self::VOLUME_BANDS as [$from, $to, $off]) {
            $count = max(0, min($outlets, $to) - $from + 1);
            if ($count > 0) {
                $lines[] = [
                    'label'  => 'Volume discount',
                    'detail' => "Outlets {$from}–".min($outlets, $to).' at '.($off * 100).'% off',
                    'amount' => -round($unit * $count * $off, 2),
                ];
            }
        }

        $flat = 0;
        foreach ($addons as $module => $quantity) {
            $m = $catalogue[$module] ?? null;
            if (! $m || ! in_array($m['kind'], ['addon', 'metered'], true)) {
                continue;
            }
            // Full already includes the flat add-ons; never charge for them twice.
            if (in_array($module, (array) $plan->modules, true)) {
                continue;
            }

            if ($m['kind'] === 'addon') {
                $flat++;
                $lines[] = ['label' => $m['name'], 'detail' => 'Add-on', 'amount' => (float) $m['price']];
                continue;
            }

            $billed = max((int) $quantity, (int) ($m['min_quantity'] ?? 1));
            $lines[] = [
                'label'  => $m['name'],
                'detail' => "{$billed} {$m['unit']}".($billed === 1 ? '' : 's').' × RM'.$m['price']
                    .((int) $quantity < $billed ? " (minimum {$billed})" : ''),
                'amount' => (float) $m['price'] * $billed,
            ];
        }

        $cap = (int) config('modules.addon_cap_on_basic');
        if ($flat > $cap) {
            return $fail("Basic takes up to {$cap} add-ons. For more, the Full suite includes all six.");
        }

        $monthly = round(array_sum(array_column($lines, 'amount')), 2);

        return new Quote($lines, $monthly, $cycle, $cycle === 'yearly' ? $monthly * 10 : $monthly);
    }
}
