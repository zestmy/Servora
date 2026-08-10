<?php

namespace Tests\Feature;

use App\Livewire\Marketing\FoodCostCalculator;
use App\Livewire\Marketing\ToolsIndex;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The food cost percentage tool.
 *
 * One division, which is exactly why it needs pinning: the arithmetic is easy
 * to get subtly wrong in the direction nobody checks — a 0/0 reported as "0%"
 * reads as a perfect dish, and "four weeks a month" quietly loses a month a
 * year from the figure an operator is about to make a decision on.
 */
class FoodCostCalculatorTest extends TestCase
{
    public function test_it_reports_the_percentage_profit_and_margin(): void
    {
        Livewire::test(FoodCostCalculator::class)
            ->set('cost', 8.40)
            ->set('price', 28.00)
            ->assertSee('30.0%')      // food cost
            ->assertSee('RM 19.60')   // gross profit
            ->assertSee('70.0%');     // margin
    }

    /**
     * Nothing entered is not a perfect dish. 0/0 must read as "you have not
     * told me yet", not as 0% food cost.
     */
    public function test_an_empty_form_states_nothing_rather_than_zero_percent(): void
    {
        Livewire::test(FoodCostCalculator::class)
            ->assertSee('Enter a cost and a menu price')
            ->assertDontSee('0.0%');
    }

    public function test_it_offers_both_ways_to_hit_the_target(): void
    {
        Livewire::test(FoodCostCalculator::class)
            ->set('cost', 12.00)
            ->set('price', 30.00)
            ->set('target', 30)
            ->assertSee('RM 40.00')   // charge this for what it costs now
            ->assertSee('RM 9.00');   // or get the recipe to this at today's price
    }

    public function test_a_month_is_52_over_12_weeks_not_four(): void
    {
        $figures = Livewire::test(FoodCostCalculator::class)
            ->set('cost', 10.00)
            ->set('price', 20.00)
            ->set('soldPerWeek', 100)
            ->call('figures');

        // 100 dishes a week at RM 10 profit = RM 1,000/week.
        // 52/12 weeks a month = RM 4,333.33, not RM 4,000.
        $this->assertEqualsWithDelta(4333.33, $figures->effects['returns'][0]['monthly_profit'], 0.01);
    }

    public function test_the_verdict_changes_with_the_target(): void
    {
        $under = Livewire::test(FoodCostCalculator::class)->set('cost', 6)->set('price', 30)->set('target', 30);
        $over  = Livewire::test(FoodCostCalculator::class)->set('cost', 18)->set('price', 30)->set('target', 30);

        $under->assertSee('carrying its weight');
        $over->assertSee('Well over target');
    }

    public function test_the_tools_index_lists_both_tools(): void
    {
        Livewire::test(ToolsIndex::class)
            ->assertSee('Recipe Cost Calculator')
            ->assertSee('Food Cost Percentage Calculator');
    }
}
