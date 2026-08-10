<?php

namespace App\Services\Marketing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The price ticker on the marketing home page.
 *
 * WHAT THIS PUBLISHES, STATED PLAINLY: prices derived from the purchase records
 * of the companies using Servora. That was a deliberate decision (2026-08-11)
 * taken with the numbers in front of us — at the time there were two companies
 * with price history, so an item's published price is close to what one
 * identifiable merchant pays. Two things are done to publish no more than the
 * ticker needs:
 *
 *   - the MEDIAN across companies is published, never a raw invoice line;
 *   - no supplier is ever named, so what a merchant pays and who they buy it
 *     from stay separate.
 *
 * If that trade stops being acceptable, MIN_COMPANIES below is the dial: raise
 * it to 5 and the ticker publishes only items that several merchants buy, which
 * is the ordinary floor for this kind of aggregate. It is set to 1 because the
 * alternative today is an empty ticker.
 */
class MarketPriceTicker
{
    /** Distinct companies an item needs before its price is published. */
    private const MIN_COMPANIES = 1;

    /** Items shown, most-traded first — a ticker is scanned, not read. */
    private const LIMIT = 24;

    private const TTL_MINUTES = 60;

    /**
     * Beyond this, a "price move" is a unit change, not a market.
     *
     * The first run against real data published blueberries up 912% and rosemary
     * up 152% — both were the same item re-costed per punnet instead of per
     * kilo. A ticker is a credibility device; one absurd number spends the
     * credibility of every honest one beside it.
     */
    private const IMPLAUSIBLE_MOVE_PCT = 60;

    /**
     * Category names as merchants actually type them, mapped to the handful of
     * groups a visitor recognises.
     *
     * Matched on substrings because these are free-text categories: one company
     * writes "DRY GOODS", another "Groceries", and neither is wrong.
     */
    private const GROUPS = [
        'Vegetables' => ['vegetable', 'produce', 'potato', 'onion', 'salad', 'herb'],
        'Fruits'     => ['fruit'],
        'Poultry'    => ['poultry', 'chicken', 'duck', 'egg'],
        'Meat'       => ['meat', 'beef', 'lamb', 'pork', 'mutton'],
        'Seafood'    => ['seafood', 'fish', 'prawn', 'squid'],
        'Dry goods'  => ['dry good', 'grocer', 'flour', 'rice', 'sugar', 'oil', 'spice', 'condiment'],
    ];

    /** @return Collection<int, array<string, mixed>> */
    public function items(): Collection
    {
        return Cache::remember('marketing.price-ticker', now()->addMinutes(self::TTL_MINUTES), function () {
            return $this->build();
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function build(): Collection
    {
        $rows = DB::table('ingredient_price_history as h')
            ->join('ingredients as i', 'i.id', '=', 'h.ingredient_id')
            ->leftJoin('ingredient_categories as c', 'c.id', '=', 'i.ingredient_category_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'h.uom_id')
            ->whereNotNull('h.cost')
            ->where('h.cost', '>', 0)
            ->orderByDesc('h.effective_date')
            ->get([
                'i.name as item',
                'i.company_id',
                'c.name as category',
                'u.abbreviation as unit',
                'h.cost',
                'h.effective_date',
            ]);

        return $rows
            // Grouped by NAME, not by ingredient id: the same tomato is a
            // different row in every company, and one price per company is the
            // thing this is trying not to publish.
            ->groupBy(fn ($r) => mb_strtoupper(trim((string) $r->item)) . '|' . ($r->unit ?: '?'))
            ->map(fn (Collection $prices, string $key) => $this->summarise(explode('|', $key)[0], $prices))
            ->filter()
            ->sortByDesc('samples')
            ->take(self::LIMIT)
            ->values();
    }

    /** @return array<string, mixed>|null */
    private function summarise(string $name, Collection $prices): ?array
    {
        $group = $this->groupFor($prices->first()->category ?? '');

        if (! $group) {
            return null;
        }

        if ($prices->pluck('company_id')->unique()->count() < self::MIN_COMPANIES) {
            return null;
        }

        $byDate = $prices->groupBy(fn ($r) => (string) $r->effective_date)->sortKeysDesc();

        if ($byDate->count() < 2) {
            return null;
        }

        $latest   = $this->median($byDate->first()->pluck('cost'));
        $previous = $this->median($byDate->skip(1)->first()->pluck('cost'));

        if ($latest === null || $previous === null || $previous <= 0) {
            return null;
        }

        $change = (($latest - $previous) / $previous) * 100;

        if (abs($change) > self::IMPLAUSIBLE_MOVE_PCT) {
            return null;
        }

        return [
            'item'      => $this->presentable($name),
            'group'     => $group,
            'unit'      => $prices->first()->unit ?: null,
            'price'     => round($latest, 2),
            'previous'  => round($previous, 2),
            'change'    => round($change, 1),
            // Under a tenth of a percent is noise, and an arrow that twitches
            // on rounding makes the whole strip look made up.
            'direction' => abs($change) < 0.1 ? 'flat' : ($change > 0 ? 'up' : 'down'),
            'samples'   => $prices->count(),
        ];
    }

    private function groupFor(?string $category): ?string
    {
        $category = mb_strtolower(trim((string) $category));

        if ($category === '') {
            return null;
        }

        foreach (self::GROUPS as $group => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($category, $needle)) {
                    return $group;
                }
            }
        }

        return null;
    }

    /** @param Collection<int, mixed> $values */
    private function median(Collection $values): ?float
    {
        $sorted = $values->map(fn ($v) => (float) $v)->sort()->values();

        if ($sorted->isEmpty()) {
            return null;
        }

        $middle = intdiv($sorted->count(), 2);

        return $sorted->count() % 2 === 1
            ? $sorted[$middle]
            : ($sorted[$middle - 1] + $sorted[$middle]) / 2;
    }

    /**
     * Merchants type in shouty inventory shorthand — "ONION, RED, PEELED".
     * A marketing page is read by people who do not work there.
     */
    private function presentable(string $name): string
    {
        $name = preg_replace('/\s+/', ' ', trim($name));

        return \Illuminate\Support\Str::title(mb_strtolower($name));
    }
}
