<?php

namespace App\Services\Billing;

/**
 * A priced configuration. `monthly` and `cycleTotal` are the recurring price;
 * `credit` and `dueNow` are what CheckoutService adds for a change mid-period.
 */
final class Quote
{
    /**
     * @param  array<int, array{label: string, detail: string, amount: float}>  $lines  monthly
     */
    public function __construct(
        public readonly array $lines,
        public readonly float $monthly,
        public readonly string $cycle,
        public readonly float $cycleTotal,
        public readonly float $credit = 0,
        public readonly ?float $dueNow = null,
        public readonly ?string $error = null,
    ) {}

    public function ok(): bool
    {
        return $this->error === null;
    }

    public function withCredit(float $credit): self
    {
        return new self($this->lines, $this->monthly, $this->cycle, $this->cycleTotal,
            $credit, max(0, round($this->cycleTotal - $credit, 2)), $this->error);
    }

    public function due(): float
    {
        return $this->dueNow ?? $this->cycleTotal;
    }
}
