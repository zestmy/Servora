<?php

namespace App\Livewire\Marketing;

use Livewire\Component;

/**
 * Food cost percentage, both directions.
 *
 * The arithmetic is one division, which is exactly why it is worth a page: the
 * question is never "what is 8.40 over 28.00", it is "am I charging enough",
 * and that needs the answer read back in the operator's own terms — what this
 * dish makes, what it should cost, what it should sell for.
 *
 * No database, no email, no limit. It has nothing to give away, so it asks for
 * nothing: a visitor can be useful to themselves here and meet Servora on the
 * way out rather than on the way in.
 */
class FoodCostCalculator extends Component
{
    public string $dishName = '';

    /** What the ingredients cost to make one portion. */
    public float $cost = 0;

    /** What it sells for, before tax. */
    public float $price = 0;

    /** The share of the price the food is meant to be. */
    public float $target = 30;

    /** Optional: turns per-dish figures into a month. */
    public float $soldPerWeek = 0;

    /** @return array<string, float|null> */
    public function figures(): array
    {
        $cost   = max(0.0, (float) $this->cost);
        $price  = max(0.0, (float) $this->price);
        $target = min(99.0, max(1.0, (float) $this->target));

        // Below a sold price there is no percentage to state — and 0/0 is not
        // "0%", it is "you have not told me yet".
        $actual = $price > 0 ? ($cost / $price) * 100 : null;
        $profit = $price > 0 ? $price - $cost : null;

        return [
            'actual'        => $actual === null ? null : round($actual, 1),
            'profit'        => $profit === null ? null : round($profit, 2),
            'margin'        => $actual === null ? null : round(100 - $actual, 1),
            // The two ways to hit the target: charge this, or spend that.
            'price_at_target' => $cost > 0 ? round($cost / ($target / 100), 2) : null,
            'cost_at_target'  => $price > 0 ? round($price * ($target / 100), 2) : null,
            'monthly_profit'  => ($profit !== null && $this->soldPerWeek > 0)
                // 52/12 rather than 4: four weeks a month loses a month a year,
                // and this number is read as "what the dish is worth to me".
                ? round($profit * (float) $this->soldPerWeek * (52 / 12), 2)
                : null,
        ];
    }

    /** How the number should feel, not just what it is. */
    public function verdict(): ?array
    {
        $figures = $this->figures();

        if ($figures['actual'] === null) {
            return null;
        }

        $actual = $figures['actual'];
        $target = (float) $this->target;

        if ($actual <= $target) {
            return [
                'tone' => 'good',
                'text' => 'At or under your target — this dish is carrying its weight.',
            ];
        }

        if ($actual <= $target + 5) {
            return [
                'tone' => 'watch',
                'text' => 'A little over target. Worth watching, not worth panicking about.',
            ];
        }

        return [
            'tone' => 'bad',
            'text' => 'Well over target. Either the price is too low or the recipe costs more than it should.',
        ];
    }

    public function render()
    {
        return view('livewire.marketing.food-cost-calculator', [
            'figures' => $this->figures(),
            'verdict' => $this->verdict(),
        ])->layout('layouts.marketing', [
            'title' => 'Free Food Cost Percentage Calculator',
        ]);
    }
}
