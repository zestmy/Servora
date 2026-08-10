<?php

namespace App\Services\Marketing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The ingredient prices the public tools are allowed to see.
 *
 * Same bargain as the ticker, and for the same reason: these are prices paid by
 * companies using Servora, published deliberately (2026-08-11). Everything a
 * visitor can reach goes through here so there is ONE place that decides what
 * leaves the tenant boundary — a public search box wired straight to the
 * ingredients table would let anyone type a competitor's speciality item and
 * read back what they pay, per company, with the supplier attached.
 *
 * What leaves: an item name, a unit, and the median price across every company
 * that buys it. What never leaves: which company, which supplier, and any
 * single invoice line.
 */
class PublicIngredientPrices
{
    private const TTL_MINUTES = 60;

    /** Nothing useful comes back from one or two letters. */
    private const MIN_SEARCH = 2;

    private const RESULTS = 20;

    /**
     * Every publishable item, keyed by a stable slug.
     *
     * Built once and cached: the whole catalogue is small, and the alternative
     * is a LIKE query across every company's ingredients on each keystroke of a
     * public, uncached page.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function catalogue(): Collection
    {
        return Cache::remember('marketing.public-prices', now()->addMinutes(self::TTL_MINUTES), function () {
            return DB::table('ingredient_price_history as h')
                ->join('ingredients as i', 'i.id', '=', 'h.ingredient_id')
                ->leftJoin('units_of_measure as u', 'u.id', '=', 'h.uom_id')
                ->whereNotNull('h.cost')
                ->where('h.cost', '>', 0)
                ->orderByDesc('h.effective_date')
                ->get(['i.name as item', 'u.abbreviation as unit', 'h.cost'])
                ->groupBy(fn ($r) => mb_strtoupper(trim((string) $r->item)) . '|' . ($r->unit ?: '?'))
                ->map(function (Collection $rows, string $key) {
                    [$name, $unit] = explode('|', $key);

                    return [
                        'slug'  => \Illuminate\Support\Str::slug($key),
                        'name'  => \Illuminate\Support\Str::title(mb_strtolower($name)),
                        'unit'  => $unit === '?' ? null : $unit,
                        'price' => round($this->median($rows->pluck('cost')), 2),
                    ];
                })
                ->filter(fn (array $item) => $item['price'] > 0)
                ->keyBy('slug');
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    public function search(string $term): Collection
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_SEARCH) {
            return collect();
        }

        $needle = mb_strtolower($term);

        return $this->catalogue()
            ->filter(fn (array $i) => str_contains(mb_strtolower($i['name']), $needle))
            // A name that STARTS with what was typed is what the person meant.
            ->sortBy(fn (array $i) => str_starts_with(mb_strtolower($i['name']), $needle) ? 0 : 1)
            ->take(self::RESULTS)
            ->values();
    }

    /** @return array<string, mixed>|null */
    public function find(string $slug): ?array
    {
        return $this->catalogue()->get($slug);
    }

    /** @param Collection<int, mixed> $values */
    private function median(Collection $values): float
    {
        $sorted = $values->map(fn ($v) => (float) $v)->sort()->values();

        if ($sorted->isEmpty()) {
            return 0.0;
        }

        $middle = intdiv($sorted->count(), 2);

        return $sorted->count() % 2 === 1
            ? $sorted[$middle]
            : ($sorted[$middle - 1] + $sorted[$middle]) / 2;
    }
}
