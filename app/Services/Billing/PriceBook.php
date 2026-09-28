<?php

namespace App\Services\Billing;

use App\Models\BillingCurrency;

/**
 * Subscription prices in one currency.
 *
 * MYR is the list: plans.price_monthly for the suites, config/modules.php for
 * the add-ons. Any other currency either has a fixed price set by the admin
 * (Admin › Currencies) or is the MYR list converted at Bank Negara's rate and
 * rounded UP to the currency's rounding step, so a converted price never
 * lands below the ringgit it stands for. A fixed currency with an item left
 * blank converts that item.
 *
 * Keys: 'suite' => basic|full, 'module' => any catalogue key.
 */
final class PriceBook
{
    public function __construct(
        public readonly string $currency,
        private readonly ?BillingCurrency $row = null,
        public readonly ?Fx $fx = null,
    ) {}

    public static function myr(): self
    {
        return new self('MYR', null, Fx::myr());
    }

    public function isMyr(): bool
    {
        return $this->currency === 'MYR';
    }

    /** Every price in this book can be worked out right now. */
    public function available(): bool
    {
        if ($this->isMyr() || $this->fx) {
            return true;
        }

        // Without a rate, only a fully fixed book can quote.
        if ($this->row?->pricing_mode !== BillingCurrency::MODE_FIXED) {
            return false;
        }

        foreach (['basic', 'full'] as $slug) {
            if (! is_numeric($this->row->prices['suite'][$slug] ?? null)) {
                return false;
            }
        }
        foreach (array_keys((array) config('modules.catalogue')) as $key) {
            if ($key !== 'basic' && ! is_numeric($this->row->prices['module'][$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    public function suite(string $slug, float $myr): ?float
    {
        return $this->price('suite', $slug, $myr);
    }

    public function module(string $key, float $myr): ?float
    {
        return $this->price('module', $key, $myr);
    }

    private function price(string $group, string $key, float $myr): ?float
    {
        if ($this->isMyr() || $myr == 0.0) {
            return $myr;
        }

        $fixed = $this->row?->pricing_mode === BillingCurrency::MODE_FIXED
            ? ($this->row->prices[$group][$key] ?? null) : null;
        if (is_numeric($fixed)) {
            return (float) $fixed;
        }

        if (! $this->fx) {
            return null;
        }

        // Per-employee prices are small: rounding RM3 up to a whole dollar
        // is a third more. Below ten steps, round to a tenth of one.
        $amount = $this->fx->fromMyr($myr);
        $step = $this->step();

        return self::roundUp($amount, $amount < $step * 10 ? $step / 10 : $step);
    }

    public function step(): float
    {
        return $this->row && $this->row->rounding > 0 ? (float) $this->row->rounding : 1.0;
    }

    public static function roundUp(float $amount, float $step): float
    {
        $step = $step > 0 ? $step : 1;

        return round(ceil(round($amount / $step, 6)) * $step, 2);
    }

    public function symbol(): string
    {
        return $this->isMyr() ? 'RM' : ($this->row?->symbol ?: $this->currency.' ');
    }

    /** Whole units when the currency rounds to whole units (and always for MYR list prices). */
    public function decimals(float $amount = 0): int
    {
        if ($this->isMyr()) {
            return floor($amount) == $amount ? 0 : 2;
        }

        return $this->step() >= 1 && floor($amount) == $amount ? 0 : 2;
    }

    public function format(float $amount, ?int $decimals = null): string
    {
        $sign = $amount < 0 ? '−' : '';

        return $sign.$this->symbol().number_format(abs($amount), $decimals ?? $this->decimals($amount));
    }

    public function name(): string
    {
        return $this->isMyr() ? 'Malaysian ringgit' : ($this->row?->name ?: $this->currency);
    }
}
