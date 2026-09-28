<?php

namespace App\Services\Billing;

use Carbon\CarbonInterface;

/**
 * One currency's value in ringgit: how many MYR one unit of it buys, on the
 * date Bank Negara published it. MYR itself is 1.
 */
final class Fx
{
    public function __construct(
        public readonly string $currency,
        public readonly float $myrPerUnit,
        public readonly CarbonInterface $date,
        public readonly string $type = 'middle_rate',
    ) {}

    public static function myr(): self
    {
        return new self('MYR', 1.0, now());
    }

    public function isMyr(): bool
    {
        return $this->currency === 'MYR';
    }

    /** Rounded to the sen, because that is what CHIP-IN charges in. */
    public function toMyr(float $amount): float
    {
        return round($amount * $this->myrPerUnit, 2);
    }

    public function fromMyr(float $myr): float
    {
        return $this->myrPerUnit > 0 ? round($myr / $this->myrPerUnit, 2) : 0.0;
    }

    /** What a payment records about the conversion (payments.fx). */
    public function toArray(float $amount): array
    {
        return [
            'currency'     => $this->currency,
            'amount'       => round($amount, 2),
            'myr_per_unit' => round($this->myrPerUnit, 8),
            'myr'          => $this->toMyr($amount),
            'rate_type'    => $this->type,
            'rate_date'    => $this->date->toDateString(),
            'source'       => 'Bank Negara Malaysia',
        ];
    }
}
