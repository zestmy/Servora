<?php

namespace Tests\Feature;

use App\Livewire\Labels\SetPrint;
use App\Livewire\Labels\Sets;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\LabelPrint;
use App\Models\LabelPrinter;
use App\Models\LabelSet;
use App\Models\LabelSetLine;
use App\Models\LabelTemplate;
use App\Models\Outlet;
use App\Models\ShelfLifeRule;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Labels\LabelSetImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A shelf life set on a print set LINE computes the use-by at print time.
 *
 * The gap this closes: a freeform line has no linked item, so no rule can
 * ever resolve for it — every print of "Lemon Wedges" demanded a hand-typed
 * date. The line's own shelf life is also the most specific setting there
 * is, so on a linked item it must beat a category rule, not lose to it.
 */
class LabelSetLineShelfLifeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private Employee $employee;
    private LabelPrinter $printer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name'      => 'Line Life Co',
            'slug'      => Str::slug('Line Life Co') . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Bangsar', 'code' => 'BSR', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        $this->employee = Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'AMIRUL PREPS',
            'is_active'  => true,
        ]);

        $this->printer = LabelPrinter::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Pass printer',
            'driver'     => 'browser',
            'width_mm'   => 70,
            'height_mm'  => 40,
            'is_active'  => true,
        ]);

        LabelTemplate::create([
            'company_id' => $this->company->id,
            'name'       => 'Prep 70x40',
            'label_type' => 'prep',
            'width_mm'   => 70,
            'height_mm'  => 40,
            'layout'     => ['fields' => [
                ['token' => 'item.name', 'x' => 2, 'y' => 2, 'w' => 46, 'h' => 6, 'font_size' => 10],
            ]],
        ]);
    }

    private function setWithLine(array $line): LabelSet
    {
        $set = LabelSet::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Chiller 1',
        ]);

        LabelSetLine::create(array_merge([
            'label_set_id'  => $set->id,
            'sort_order'    => 0,
            'label_type'    => 'prep',
            'storage_state' => 'chill',
            'copies'        => 1,
        ], $line));

        return $set;
    }

    private function printScreen(LabelSet $set)
    {
        return Livewire::actingAs($this->user)
            ->test(SetPrint::class, ['set' => $set])
            ->set('printerId', $this->printer->id)
            ->set('employeeId', $this->employee->id);
    }

    public function test_freeform_line_with_own_shelf_life_prints_a_computed_use_by(): void
    {
        Carbon::setTestNow('2026-08-19 09:00:00');

        $set = $this->setWithLine([
            'custom_name'      => 'Lemon Wedges',
            'shelf_life_value' => 3,
            'shelf_life_unit'  => 'days',
        ]);

        $this->printScreen($set)->call('print')->assertHasNoErrors();

        $print = LabelPrint::firstOrFail();

        $this->assertFalse((bool) $print->manual_expiry, 'The date came from the line, nobody typed it.');
        $this->assertSame('2026-08-22 23:59:59', $print->end_at->format('Y-m-d H:i:s'));
        $this->assertSame('set_line', $print->payload['shelf_life']['source']);

        Carbon::setTestNow();
    }

    public function test_line_shelf_life_beats_a_category_rule(): void
    {
        Carbon::setTestNow('2026-08-19 09:00:00');

        $uom = UnitOfMeasure::firstOrCreate(
            ['abbreviation' => 'kg'],
            ['name' => 'Kilogram', 'type' => 'weight']
        );

        $category = IngredientCategory::create([
            'company_id' => $this->company->id, 'name' => 'DAIRY',
        ]);

        $ingredient = Ingredient::create([
            'company_id'             => $this->company->id,
            'name'                   => 'BUTTER',
            'base_uom_id'            => $uom->id,
            'recipe_uom_id'          => $uom->id,
            'ingredient_category_id' => $category->id,
        ]);

        ShelfLifeRule::create([
            'company_id'    => $this->company->id,
            'ruleable_type' => IngredientCategory::class,
            'ruleable_id'   => $category->id,
            'storage_state' => 'chill',
            'value'         => 5,
            'unit'          => 'days',
        ]);

        $set = $this->setWithLine([
            'labelable_type'   => Ingredient::class,
            'labelable_id'     => $ingredient->id,
            'shelf_life_value' => 2,
            'shelf_life_unit'  => 'days',
        ]);

        $this->printScreen($set)->call('print')->assertHasNoErrors();

        $this->assertSame(
            '2026-08-21',
            LabelPrint::firstOrFail()->end_at->format('Y-m-d'),
            'The line says 2 days; the category rule says 5. The line is more specific and must win.'
        );

        Carbon::setTestNow();
    }

    public function test_preview_is_automatic_so_no_date_is_asked_for(): void
    {
        $set  = $this->setWithLine([
            'custom_name'      => 'Lemon Wedges',
            'shelf_life_value' => 12,
            'shelf_life_unit'  => 'hours',
        ]);
        $line = $set->lines()->firstOrFail();

        $previews = $this->printScreen($set)->viewData('previews');

        $this->assertFalse($previews[$line->id]['manual'], 'Manual = the screen shows a date picker. It must not.');
        $this->assertSame('set_line', $previews[$line->id]['source']);
    }

    public function test_sub_day_line_life_is_not_rounded_to_end_of_day(): void
    {
        Carbon::setTestNow('2026-08-19 09:00:00');

        $set = $this->setWithLine([
            'custom_name'      => 'Cut Fruit',
            'shelf_life_value' => 4,
            'shelf_life_unit'  => 'hours',
        ]);

        $this->printScreen($set)->call('print')->assertHasNoErrors();

        $this->assertSame(
            '2026-08-19 13:00',
            LabelPrint::firstOrFail()->end_at->format('Y-m-d H:i'),
            'Rounding a 4-hour life to 23:59 would extend it — a hazard, not a nicety.'
        );

        Carbon::setTestNow();
    }

    public function test_manager_screen_sets_the_life_and_defaults_the_unit_to_days(): void
    {
        $set  = $this->setWithLine(['custom_name' => 'Lemon Wedges']);
        $line = $set->lines()->firstOrFail();

        $screen = Livewire::actingAs($this->user)->test(Sets::class)
            ->set('outletId', $this->outlet->id)
            ->call('editLines', $set->id);

        $screen->call('updateLine', $line->id, 'shelf_life_value', '3');

        $line->refresh();
        $this->assertSame(3.0, (float) $line->shelf_life_value);
        $this->assertSame('days', $line->shelf_life_unit, 'A value with no unit yet defaults to days.');

        $screen->call('updateLine', $line->id, 'shelf_life_unit', 'hours');
        $this->assertSame('hours', $line->refresh()->shelf_life_unit);

        // Clearing the value clears the unit too — "follow the rules again"
        // is one action, not a value-less unit left behind.
        $screen->call('updateLine', $line->id, 'shelf_life_value', '');

        $line->refresh();
        $this->assertNull($line->shelf_life_value);
        $this->assertNull($line->shelf_life_unit);
    }

    public function test_copying_a_set_carries_the_line_shelf_life(): void
    {
        $other = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Damansara', 'code' => 'DMS', 'is_active' => true,
        ]);

        $set = $this->setWithLine([
            'custom_name'      => 'Lemon Wedges',
            'shelf_life_value' => 3,
            'shelf_life_unit'  => 'days',
        ]);

        [$copy] = app(LabelSetImport::class)->copyToOutlet($set, $other->id);

        $line = $copy->lines()->firstOrFail();

        $this->assertSame(3.0, (float) $line->shelf_life_value);
        $this->assertSame('days', $line->shelf_life_unit);
    }
}
