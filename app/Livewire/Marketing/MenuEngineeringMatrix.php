<?php

namespace App\Livewire\Marketing;

use Livewire\Component;

/**
 * The menu engineering matrix — Kasavana & Smith, 1982.
 *
 * Every dish is placed against two lines: how often it sells compared with the
 * rest of the menu, and how much cash it leaves behind compared with the rest
 * of the menu. That gives four groups and four different instructions, which is
 * the whole point — "sales are down" is not an instruction, "this one sells and
 * makes nothing, put the price up" is.
 *
 * TWO PIECES OF ARITHMETIC ARE EASY TO GET WRONG, and both are wrong in the
 * direction that flatters the menu:
 *
 *   - Profitability is judged against the WEIGHTED average contribution — total
 *     cash over total covers — not the average of each dish's margin. A rare
 *     lobster with a huge margin drags a plain average upward and quietly
 *     reclassifies half the menu as underperforming.
 *
 *   - Popularity is judged against 70% of an equal share, not against the
 *     average. On a 10-item menu the line is 7%, not 10%: with an equal-share
 *     line, half the menu is "unpopular" by construction no matter how well it
 *     sells.
 */
class MenuEngineeringMatrix extends Component
{
    /** @var array<int, array{name: string, cost: float, price: float, sold: float}> */
    public array $items = [
        ['name' => '', 'cost' => 0, 'price' => 0, 'sold' => 0],
        ['name' => '', 'cost' => 0, 'price' => 0, 'sold' => 0],
        ['name' => '', 'cost' => 0, 'price' => 0, 'sold' => 0],
    ];

    /**
     * The share of an equal slice a dish must reach to count as popular.
     *
     * 70% is the figure from the original method and the one every hospitality
     * course teaches; exposing it as a field would invite tuning until the menu
     * looks good.
     */
    private const POPULARITY_RULE = 0.70;

    private const QUADRANTS = [
        'star' => [
            'name'   => 'Stars',
            'advice' => 'Sells well and makes money. Protect it: keep the recipe honest, keep it visible, do not discount it.',
        ],
        'plowhorse' => [
            'name'   => 'Plowhorses',
            'advice' => 'Popular but thin. Raise the price a little, or take cost out of the plate — people are already ordering it.',
        ],
        'puzzle' => [
            'name'   => 'Puzzles',
            'advice' => 'Good money, few takers. Move it up the menu, rename it, or let staff recommend it before you cut it.',
        ],
        'dog' => [
            'name'   => 'Dogs',
            'advice' => 'Neither popular nor profitable. Reprice it once, then take it off and free up the prep.',
        ],
    ];

    public function addRow(): void
    {
        $this->items[] = ['name' => '', 'cost' => 0, 'price' => 0, 'sold' => 0];
    }

    public function removeRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);

        if (! $this->items) {
            $this->addRow();
        }
    }

    /** Rows with enough on them to place. */
    private function usable(): array
    {
        return array_values(array_filter(
            $this->items,
            fn (array $i) => trim((string) $i['name']) !== ''
                && (float) $i['price'] > 0
                && (float) $i['sold'] > 0
        ));
    }

    /** @return array<string, mixed>|null */
    public function analysis(): ?array
    {
        $items = $this->usable();

        // One dish has nothing to be compared against; two is a coin toss. The
        // method needs a menu.
        if (count($items) < 3) {
            return null;
        }

        $totalSold = array_sum(array_map(fn ($i) => (float) $i['sold'], $items));

        if ($totalSold <= 0) {
            return null;
        }

        $totalContribution = array_sum(array_map(
            fn ($i) => ((float) $i['price'] - (float) $i['cost']) * (float) $i['sold'],
            $items
        ));

        // Weighted, not the average of the margins — see the class note.
        $averageContribution = $totalContribution / $totalSold;
        $popularityLine      = (1 / count($items)) * self::POPULARITY_RULE * 100;

        $placed = [];

        foreach ($items as $item) {
            $contribution = (float) $item['price'] - (float) $item['cost'];
            $share        = ((float) $item['sold'] / $totalSold) * 100;

            $popular     = $share >= $popularityLine;
            $profitable  = $contribution >= $averageContribution;

            $quadrant = match (true) {
                $popular && $profitable   => 'star',
                $popular && ! $profitable => 'plowhorse',
                ! $popular && $profitable => 'puzzle',
                default                   => 'dog',
            };

            $placed[] = [
                'name'         => $item['name'],
                'sold'         => (float) $item['sold'],
                'share'        => round($share, 1),
                'contribution' => round($contribution, 2),
                'total'        => round($contribution * (float) $item['sold'], 2),
                'quadrant'     => $quadrant,
                'label'        => self::QUADRANTS[$quadrant]['name'],
            ];
        }

        usort($placed, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'items'            => $placed,
            'total_sold'       => $totalSold,
            'total_profit'     => round($totalContribution, 2),
            'average'          => round($averageContribution, 2),
            'popularity_line'  => round($popularityLine, 1),
            'quadrants'        => self::QUADRANTS,
            'grouped'          => collect($placed)->groupBy('quadrant'),
        ];
    }

    public function render()
    {
        return view('livewire.marketing.menu-engineering-matrix', [
            'analysis' => $this->analysis(),
        ])->layout('layouts.marketing', [
            'title' => 'Free Menu Engineering Matrix',
        ]);
    }
}
