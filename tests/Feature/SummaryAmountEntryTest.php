<?php

namespace Tests\Feature;

use App\Livewire\Inventory\StaffMealForm;
use App\Livewire\Inventory\WastageForm;
use App\Models\Company;
use App\Models\Department;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\StaffMealRecord;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WastageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A quick-entry alternative to itemising a Wastage note or Staff Meal entry
 * line by line — the same Summary Amount method Stock Take already offers
 * (StockTakeForm::$method), applied here so a kitchen that doesn't want to
 * itemise a small loss or a one-off meal can just key in the total.
 */
class SummaryAmountEntryTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Summary Amount Co', 'slug' => Str::slug('Summary Amount Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo([
            Permission::findOrCreate('inventory.view', 'web'),
            Permission::findOrCreate('inventory.wastage.record', 'web'),
            Permission::findOrCreate('inventory.staff_meals.record', 'web'),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->department = Department::create(['company_id' => $this->company->id, 'name' => 'Hot Kitchen', 'is_active' => true]);

        $this->actingAs($this->user);
    }

    // ── Wastage ──────────────────────────────────────────────────────────

    public function test_a_wastage_summary_amount_saves_with_no_lines(): void
    {
        Livewire::test(WastageForm::class)
            ->set('outlet_id', (string) $this->outlet->id)
            ->set('department_id', $this->department->id)
            ->set('wastage_date', '2026-08-05')
            ->set('method', 'summary')
            ->set('summary_amount', '123.45')
            ->call('save');

        $record = WastageRecord::latest('id')->first();

        $this->assertNotNull($record);
        $this->assertSame('summary', $record->method);
        $this->assertEqualsWithDelta(123.45, (float) $record->total_cost, 0.001);
        $this->assertSame(0, $record->lines()->count());
    }

    public function test_a_wastage_summary_amount_is_required(): void
    {
        Livewire::test(WastageForm::class)
            ->set('outlet_id', (string) $this->outlet->id)
            ->set('department_id', $this->department->id)
            ->set('wastage_date', '2026-08-05')
            ->set('method', 'summary')
            ->set('summary_amount', '')
            ->call('save')
            ->assertHasErrors(['summary_amount']);
    }

    public function test_a_detailed_wastage_record_still_requires_lines(): void
    {
        Livewire::test(WastageForm::class)
            ->set('outlet_id', (string) $this->outlet->id)
            ->set('department_id', $this->department->id)
            ->set('wastage_date', '2026-08-05')
            ->set('method', 'detailed')
            ->call('save')
            ->assertHasErrors(['lines']);
    }

    public function test_reopening_a_summary_wastage_record_loads_its_method_and_amount(): void
    {
        $record = WastageRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'department_id' => $this->department->id,
            'wastage_date' => '2026-08-05', 'method' => 'summary', 'total_cost' => 88.20,
        ]);

        $component = Livewire::test(WastageForm::class, ['id' => $record->id]);

        $component->assertSet('method', 'summary');
        $this->assertSame('88.2', $component->get('summary_amount'));
    }

    public function test_switching_from_detailed_to_summary_drops_any_typed_lines(): void
    {
        $kg = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight']);
        $flour = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Flour',
            'base_uom_id' => $kg->id, 'recipe_uom_id' => $kg->id, 'current_cost' => 5, 'is_active' => true,
        ]);

        // Lines are added while in detailed mode, then the record is switched
        // to summary before saving — the leftover client-side lines must not
        // sneak into a "summary" record.
        Livewire::test(WastageForm::class)
            ->set('outlet_id', (string) $this->outlet->id)
            ->set('department_id', $this->department->id)
            ->set('wastage_date', '2026-08-05')
            ->call('addIngredient', $flour->id)
            ->set('lines.0.quantity', '4')
            ->set('method', 'summary')
            ->set('summary_amount', '50')
            ->call('save');

        $record = WastageRecord::latest('id')->first();

        $this->assertSame(0, $record->lines()->count(), 'A summary record should carry no line items, even if some were added before the toggle.');
        $this->assertEqualsWithDelta(50.0, (float) $record->total_cost, 0.001);
    }

    // ── Staff Meal ───────────────────────────────────────────────────────

    public function test_a_staff_meal_summary_amount_saves_with_no_lines(): void
    {
        Livewire::test(StaffMealForm::class)
            ->set('outlet_id', (string) $this->outlet->id)
            ->set('meal_date', '2026-08-05')
            ->set('method', 'summary')
            ->set('summary_amount', '77.50')
            ->call('save');

        $record = StaffMealRecord::latest('id')->first();

        $this->assertNotNull($record);
        $this->assertSame('summary', $record->method);
        $this->assertEqualsWithDelta(77.50, (float) $record->total_cost, 0.001);
        $this->assertSame(0, $record->lines()->count());
    }

    public function test_a_staff_meal_summary_amount_is_required(): void
    {
        Livewire::test(StaffMealForm::class)
            ->set('outlet_id', (string) $this->outlet->id)
            ->set('meal_date', '2026-08-05')
            ->set('method', 'summary')
            ->set('summary_amount', '')
            ->call('save')
            ->assertHasErrors(['summary_amount']);
    }

    public function test_reopening_a_summary_staff_meal_record_loads_its_method_and_amount(): void
    {
        $record = StaffMealRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'meal_date' => '2026-08-05', 'method' => 'summary', 'total_cost' => 42.00,
        ]);

        $component = Livewire::test(StaffMealForm::class, ['id' => $record->id]);

        $component->assertSet('method', 'summary');
        $this->assertSame('42', $component->get('summary_amount'));
    }

    public function test_the_forms_offer_the_method_toggle(): void
    {
        $wastageHtml = Livewire::test(WastageForm::class)->html();
        $this->assertStringContainsString('Summary Amount', $wastageHtml);
        $this->assertStringContainsString('Detailed Items', $wastageHtml);

        $staffMealHtml = Livewire::test(StaffMealForm::class)->html();
        $this->assertStringContainsString('Summary Amount', $staffMealHtml);
        $this->assertStringContainsString('Detailed Items', $staffMealHtml);
    }
}
