<?php

namespace Tests\Feature;

use App\Livewire\Marketing\MenuEngineeringMatrix;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The menu engineering matrix.
 *
 * The two thresholds are where this goes wrong, and both go wrong in the
 * direction that flatters the menu — which is the direction nobody checks.
 * These pin them with worked examples rather than with the formula, so a
 * "simplification" that changes the answer fails here rather than in a meeting
 * where somebody is deciding what to take off the menu.
 */
class MenuEngineeringMatrixTest extends TestCase
{
    /** @param array<int, array{0: string, 1: float, 2: float, 3: float}> $rows */
    private function withMenu(array $rows)
    {
        $items = array_map(fn ($r) => [
            'name' => $r[0], 'cost' => $r[1], 'price' => $r[2], 'sold' => $r[3],
        ], $rows);

        return Livewire::test(MenuEngineeringMatrix::class)->set('items', $items);
    }

    private function analysisOf($screen): array
    {
        return $screen->call('analysis')->effects['returns'][0];
    }

    /**
     * Popularity is 70% of an equal share, not the share itself.
     *
     * On a four-item menu that is 17.5%, not 25% — with an equal-share line
     * roughly half a menu is "unpopular" by construction however well it sells.
     */
    public function test_the_popularity_line_is_seventy_percent_of_an_equal_share(): void
    {
        $analysis = $this->analysisOf($this->withMenu([
            ['A', 5, 20, 25], ['B', 5, 20, 25], ['C', 5, 20, 25], ['D', 5, 20, 25],
        ]));

        $this->assertEqualsWithDelta(17.5, $analysis['popularity_line'], 0.01);
    }

    /**
     * Profitability is judged against the WEIGHTED average — total cash over
     * total covers. One rare high-margin dish must not drag the line up and
     * reclassify the rest of the menu as underperforming.
     */
    public function test_profitability_uses_the_weighted_average_not_the_average_margin(): void
    {
        $analysis = $this->analysisOf($this->withMenu([
            // 100 sold at RM 5 profit, 100 at RM 5, and 1 lobster at RM 100.
            ['Everyday A', 5, 10, 100],
            ['Everyday B', 5, 10, 100],
            ['Lobster', 50, 150, 1],
        ]));

        // Weighted: (500 + 500 + 100) / 201 = RM 5.47.
        // A plain average of the margins would be (5 + 5 + 100) / 3 = RM 36.67,
        // which would call both everyday dishes unprofitable.
        $this->assertEqualsWithDelta(5.47, $analysis['average'], 0.01);

        $everyday = collect($analysis['items'])->firstWhere('name', 'Everyday A');
        $this->assertSame('plowhorse', $everyday['quadrant'], 'Popular, just under the line.');
    }

    public function test_it_places_each_dish_in_the_right_quadrant(): void
    {
        $analysis = $this->analysisOf($this->withMenu([
            ['Star',      6,  30, 100],   // sells a lot, RM 24 each
            ['Plowhorse', 12, 15, 120],   // sells most, only RM 3 each
            ['Puzzle',    8,  40, 5],     // RM 32 each, barely sells
            ['Dog',       9,  12, 4],     // RM 3 each, barely sells
        ]));

        $byName = collect($analysis['items'])->keyBy('name');

        $this->assertSame('star', $byName['Star']['quadrant']);
        $this->assertSame('plowhorse', $byName['Plowhorse']['quadrant']);
        $this->assertSame('puzzle', $byName['Puzzle']['quadrant']);
        $this->assertSame('dog', $byName['Dog']['quadrant']);
    }

    /** One dish has nothing to be compared against; two is a coin toss. */
    public function test_it_refuses_to_judge_fewer_than_three_dishes(): void
    {
        $screen = $this->withMenu([['Only one', 5, 20, 10], ['And another', 6, 22, 12]]);

        $this->assertNull($screen->call('analysis')->effects['returns'][0]);
        $screen->assertSee('Fill in at least three dishes');
    }

    /** A half-filled row is somebody still typing, not a dish worth judging. */
    public function test_incomplete_rows_are_ignored_rather_than_counted_as_zero(): void
    {
        $analysis = $this->analysisOf($this->withMenu([
            ['A', 5, 20, 10], ['B', 5, 20, 10], ['C', 5, 20, 10],
            ['', 0, 0, 0],
            ['No price yet', 5, 0, 10],
        ]));

        $this->assertCount(3, $analysis['items']);
    }

    public function test_the_thresholds_are_shown_not_just_applied(): void
    {
        $this->withMenu([['A', 5, 20, 10], ['B', 5, 25, 10], ['C', 5, 30, 10]])
            ->assertSee('Popularity line')
            ->assertSee('Average profit per dish sold')
            ->assertSee('70% of an equal share');
    }
}
