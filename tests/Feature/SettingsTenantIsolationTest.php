<?php

namespace Tests\Feature;

use App\Livewire\Settings\FormTemplateEdit;
use App\Livewire\Settings\LabourCosts;
use App\Livewire\Settings\ParLevels;
use App\Livewire\Settings\TaxRates;
use App\Models\Company;
use App\Models\FormTemplate;
use App\Models\FormTemplateLine;
use App\Models\Ingredient;
use App\Models\LabourCost;
use App\Models\LabourCostAllowance;
use App\Models\Outlet;
use App\Models\TaxRate;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Settings screens that took ids from the browser and wrote through them
 * without asking whose row it was: a tax rate shared by every tenant, another
 * company's template line, another record's allowance.
 */
class SettingsTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Company $other;
    private Outlet $otherOutlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany('Home Co');
        $this->outlet  = Outlet::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);

        $this->other       = $this->makeCompany('Other Co');
        $this->otherOutlet = Outlet::create(['company_id' => $this->other->id, 'name' => 'Theirs', 'code' => 'THR', 'is_active' => true]);
    }

    private function makeCompany(string $name): Company
    {
        return Company::create([
            'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
    }

    /** @param array<int, string> $abilities */
    private function user(array $abilities): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(array_map(fn ($a) => Permission::findOrCreate($a, 'web'), $abilities));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    // ── Tax rates ─────────────────────────────────────────────────────────

    private function systemRate(): TaxRate
    {
        return TaxRate::withoutGlobalScopes()->create([
            'company_id' => null, 'country_code' => 'MY', 'name' => 'SST (system)',
            'rate' => 6, 'is_inclusive' => false, 'is_default' => true, 'is_active' => true,
        ]);
    }

    public function test_a_tenant_cannot_delete_a_system_tax_rate(): void
    {
        $system = $this->systemRate();
        $user   = $this->user(['settings.tax_rates']);
        $this->actingAs($user);

        Livewire::test(TaxRates::class)
            ->call('delete', $system->id)
            ->assertNotFound();

        $this->assertNotNull(TaxRate::withoutGlobalScopes()->find($system->id));
    }

    public function test_a_tenant_cannot_edit_a_system_tax_rate(): void
    {
        $system = $this->systemRate();
        $user   = $this->user(['settings.tax_rates']);
        $this->actingAs($user);

        Livewire::test(TaxRates::class)
            ->call('openEdit', $system->id)
            ->assertNotFound();

        // Forging the edit id straight into save() does not reach it either.
        Livewire::test(TaxRates::class)
            ->set('editId', $system->id)
            ->set('country_code', 'MY')
            ->set('name', 'Hijacked')
            ->set('rate', '99')
            ->call('save')
            ->assertNotFound();

        $this->assertSame('SST (system)', TaxRate::withoutGlobalScopes()->find($system->id)->name);
    }

    public function test_a_tenant_can_still_edit_its_own_tax_rate(): void
    {
        $user = $this->user(['settings.tax_rates']);
        $this->actingAs($user);
        $own = TaxRate::create([
            'company_id' => $this->company->id, 'country_code' => 'MY', 'name' => 'Service',
            'rate' => 10, 'is_inclusive' => false, 'is_default' => false, 'is_active' => true,
        ]);

        Livewire::test(TaxRates::class)
            ->call('openEdit', $own->id)
            ->set('name', 'Service charge')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Service charge', $own->fresh()->name);
    }

    // ── Form template lines ───────────────────────────────────────────────

    public function test_remove_line_cannot_delete_another_companys_template_line(): void
    {
        $theirTemplate = FormTemplate::withoutGlobalScopes()->create([
            'company_id' => $this->other->id, 'name' => 'Their sheet',
            'form_type' => 'stock_take', 'is_active' => true, 'sort_order' => 0,
        ]);
        $theirLine = FormTemplateLine::create([
            'form_template_id' => $theirTemplate->id, 'item_type' => 'ingredient',
            'default_quantity' => 3, 'sort_order' => 0,
        ]);

        $user = $this->user(['purchasing.suppliers.manage']);
        $this->actingAs($user);
        $mine = FormTemplate::create([
            'company_id' => $this->company->id, 'name' => 'My sheet',
            'form_type' => 'stock_take', 'is_active' => true, 'sort_order' => 0,
        ]);

        Livewire::test(FormTemplateEdit::class, ['id' => $mine->id])
            ->call('removeLine', $theirLine->id)
            ->call('updateQty', $theirLine->id, '999')
            ->call('reorderLines', [$theirLine->id]);

        $fresh = FormTemplateLine::find($theirLine->id);
        $this->assertNotNull($fresh, 'Another company\'s template line was deleted.');
        $this->assertSame(3, (int) $fresh->default_quantity);
    }

    public function test_the_template_id_cannot_be_swapped_from_the_browser(): void
    {
        $theirTemplate = FormTemplate::withoutGlobalScopes()->create([
            'company_id' => $this->other->id, 'name' => 'Their sheet',
            'form_type' => 'stock_take', 'is_active' => true, 'sort_order' => 0,
        ]);

        $user = $this->user(['purchasing.suppliers.manage']);
        $this->actingAs($user);
        $mine = FormTemplate::create([
            'company_id' => $this->company->id, 'name' => 'My sheet',
            'form_type' => 'stock_take', 'is_active' => true, 'sort_order' => 0,
        ]);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::test(FormTemplateEdit::class, ['id' => $mine->id])
            ->set('templateId', $theirTemplate->id);
    }

    // ── Labour cost allowances ────────────────────────────────────────────

    public function test_labour_costs_cannot_update_another_companys_allowance(): void
    {
        $theirs = LabourCost::withoutGlobalScopes()->create([
            'company_id' => $this->other->id, 'outlet_id' => $this->otherOutlet->id,
            'month' => now()->startOfMonth()->toDateString(), 'department_type' => 'foh', 'basic_salary' => 1000,
        ]);
        $theirAllowance = LabourCostAllowance::create([
            'labour_cost_id' => $theirs->id, 'label' => 'Housing', 'amount' => 300,
        ]);

        $user = $this->user(['hr.view', 'hr.compensation']);
        $this->actingAs($user);

        Livewire::test(LabourCosts::class)
            ->set('outletId', $this->outlet->id)
            ->call('openEdit', 'foh')
            ->set('allowances', [['id' => $theirAllowance->id, 'label' => 'Pwned', 'amount' => '1']])
            ->call('save')
            ->assertHasNoErrors();

        $fresh = LabourCostAllowance::find($theirAllowance->id);
        $this->assertSame('Housing', $fresh->label);
        $this->assertSame('300.00', (string) $fresh->amount);
    }

    public function test_a_hr_viewer_cannot_save_labour_costs(): void
    {
        $user = $this->user(['hr.view']);
        $this->actingAs($user);

        Livewire::test(LabourCosts::class)
            ->set('outletId', $this->outlet->id)
            ->call('save')
            ->assertForbidden();

        $this->assertSame(0, LabourCost::count());
    }

    public function test_labour_costs_refuse_another_companys_outlet(): void
    {
        $user = $this->user(['hr.view', 'hr.compensation']);
        $this->actingAs($user);

        Livewire::test(LabourCosts::class)
            ->set('outletId', $this->otherOutlet->id)
            ->call('save')
            ->assertForbidden();

        $this->assertSame(0, DB::table('labour_costs')->count());
    }

    // ── Par levels ────────────────────────────────────────────────────────

    public function test_an_inventory_viewer_cannot_change_par_levels(): void
    {
        $user = $this->user(['inventory.view']);
        $this->actingAs($user);
        $kg = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight']);
        $ingredient = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Flour', 'is_active' => true,
            'base_uom_id' => $kg->id, 'recipe_uom_id' => $kg->id,
            'purchase_price' => 10, 'pack_size' => 1, 'yield_percent' => 100,
        ]);

        Livewire::test(ParLevels::class)
            ->set('outletId', $this->outlet->id)
            ->set("parLevels.{$ingredient->id}", '10')
            ->assertForbidden();

        $this->assertSame(0, DB::table('ingredient_par_levels')->count());
    }
}
