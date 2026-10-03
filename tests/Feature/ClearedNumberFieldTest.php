<?php

namespace Tests\Feature;

use App\Livewire\Marketing\EaFormGenerator;
use App\Livewire\Marketing\FoodCostCalculator;
use App\Livewire\Marketing\MenuEngineeringMatrix;
use App\Livewire\Marketing\RecipeCostCalculator;
use App\Livewire\Marketing\SalaryCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Clearing a number field is not a crash.
 *
 * REPORTED AS: the free salary calculator returned a 500 "midway filling up
 * the form". The most ordinary edit there is — select the box, delete what is
 * in it, type the real figure — killed the component.
 *
 * A `public float` on a property bound to a number input is a promise the
 * BROWSER cannot keep. An emptied box posts "", Livewire assigns it, PHP
 * refuses the type, and Livewire's recovery is to UNSET the property — so the
 * next read lands on __get and throws PropertyNotFoundException. Ints are no
 * safer: `public int $children` went the same way on 2026-10-02, after this
 * file had already been listing it — the render only reads it once a salary
 * is entered, so clearing it on an empty form proved nothing. Each field is
 * now read back after it is cleared, which is the read that fails.
 *
 * These tests clear every numeric field on every public tool, because the
 * failure was invisible to the type checker and to every existing test — all
 * of which set values rather than removing them.
 */
class ClearedNumberFieldTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: class-string, 1: array<int, string>}> */
    public static function toolProvider(): array
    {
        return [
            'salary calculator' => [SalaryCalculator::class, [
                'basic', 'unpaidLeaveDays', 'normalHoursPerDay', 'overtime', 'serviceCharge', 'children',
            ]],
            'food cost calculator' => [FoodCostCalculator::class, [
                'cost', 'price', 'target', 'soldPerWeek',
            ]],
            'recipe cost calculator' => [RecipeCostCalculator::class, [
                'portions', 'targetFoodCostPct',
            ]],
            'EA form generator' => [EaFormGenerator::class, [
                'year',
            ]],
        ];
    }

    /**
     * @dataProvider toolProvider
     *
     * @param  class-string  $component
     * @param  array<int, string>  $fields
     */
    public function test_every_number_field_survives_being_emptied(string $component, array $fields): void
    {
        foreach ($fields as $field) {
            $test = Livewire::test($component)
                ->set($field, '')
                ->assertOk();

            // Throws PropertyNotFoundException if Livewire unset it.
            $this->assertSame('', $test->instance()->{$field}, $field);
        }
    }

    /**
     * Emptied one after another, which is what retyping a form actually looks
     * like — the earlier failure only needed one, but a form is cleared in
     * sequence and each step re-renders.
     *
     * @dataProvider toolProvider
     *
     * @param  class-string  $component
     * @param  array<int, string>  $fields
     */
    public function test_the_whole_form_survives_being_emptied_in_sequence(string $component, array $fields): void
    {
        $test = Livewire::test($component);

        foreach ($fields as $field) {
            $test->set($field, '');
        }

        $test->assertOk();
    }

    /**
     * An empty salary is the empty state, not a wrong answer.
     *
     * The point of not crashing is that the page says "nothing entered yet"
     * rather than showing RM 0.00 as though it had computed something.
     */
    public function test_an_emptied_salary_returns_to_the_empty_state(): void
    {
        $component = Livewire::test(SalaryCalculator::class)
            ->set('basic', 3000)
            ->set('basic', '');

        $this->assertFalse($component->instance()->figures()['ready']);
    }

    /** Typing again after clearing gets the right answer, not a stale one. */
    public function test_retyping_after_clearing_computes_normally(): void
    {
        $figures = Livewire::test(SalaryCalculator::class)
            ->set('basic', 3000)
            ->set('basic', '')
            ->set('basic', 4500)
            ->instance()->figures();

        $this->assertTrue($figures['ready']);
        $this->assertEqualsWithDelta(4500, $figures['gross'], 0.01);
    }

    /** The matrix takes its numbers in rows; clearing one must be safe too. */
    public function test_clearing_a_matrix_row_is_safe(): void
    {
        Livewire::test(MenuEngineeringMatrix::class)
            ->set('items.0.sold', '')
            ->set('items.0.price', '')
            ->set('items.0.cost', '')
            ->assertOk();
    }

    /** The salary case as reported: a figure entered, then children cleared. */
    public function test_clearing_children_with_a_salary_entered(): void
    {
        $figures = Livewire::test(SalaryCalculator::class)
            ->set('basic', 3000)
            ->set('children', 2)
            ->set('children', '')
            ->assertOk()
            ->instance()->figures();

        $this->assertTrue($figures['ready']);
    }

    /** A cleared year refuses the download instead of printing a blank year. */
    public function test_an_ea_form_with_no_year_is_not_downloaded(): void
    {
        Livewire::test(EaFormGenerator::class)
            ->set('year', '')
            ->call('downloadPdf')
            ->assertSet('downloadError', 'Enter the year of remuneration.');
    }

    /** Retyping a year a digit at a time still moves the default employment period. */
    public function test_retyping_the_year_moves_the_default_period(): void
    {
        $test = Livewire::test(EaFormGenerator::class);
        foreach (['', '2', '20', '202', '2024'] as $typed) {
            $test->set('year', $typed);
        }

        $test->assertSet('employedFrom', '2024-01-01')->assertSet('employedTo', '2024-12-31');
    }
}
