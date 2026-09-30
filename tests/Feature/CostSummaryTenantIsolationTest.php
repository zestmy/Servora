<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\StockTake;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WastageRecord;
use App\Models\WastageRecordLine;
use App\Services\CostSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The cost summary's item detail stays inside the company.
 *
 * getItemDetail() joined wastage lines to their records with no company
 * filter — line models carry no company scope and a join does not inherit
 * the parent's — so "All outlets" listed every tenant's wastage by item name,
 * quantity and cost.
 *
 * Also covers the download controllers that checked the company but not the
 * outlet: a stock take's result and count sheet are one outlet's figures.
 */
class CostSummaryTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $name): array
    {
        $company = Company::create([
            'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true,
        ]);
        $outlet = Outlet::create(['company_id' => $company->id, 'name' => "{$name} Main", 'code' => 'M' . $company->id, 'is_active' => true]);

        return [$company, $outlet];
    }

    private function wastage(Company $company, Outlet $outlet, string $item): void
    {
        $uom = UnitOfMeasure::first() ?? UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight', 'base_factor' => 1]);
        $category = IngredientCategory::create(['company_id' => $company->id, 'name' => 'Dry']);
        $ingredient = Ingredient::create([
            'company_id' => $company->id, 'name' => $item, 'base_uom_id' => $uom->id, 'recipe_uom_id' => $uom->id,
            'ingredient_category_id' => $category->id, 'current_cost' => 5, 'is_active' => true,
        ]);
        $record = WastageRecord::create([
            'company_id' => $company->id, 'outlet_id' => $outlet->id, 'reference_number' => 'W-' . $company->id,
            'wastage_date' => now()->startOfMonth()->addDays(2)->toDateString(), 'total_cost' => 50, 'method' => 'detailed',
        ]);
        WastageRecordLine::create([
            'wastage_record_id' => $record->id, 'ingredient_id' => $ingredient->id, 'quantity' => 10,
            'uom_id' => $uom->id, 'unit_cost' => 5, 'total_cost' => 50,
        ]);
    }

    private function user(Company $company, Outlet $outlet, array $permissions, bool $allOutlets = true): User
    {
        $user = User::factory()->create(['company_id' => $company->id, 'outlet_id' => $outlet->id, 'can_view_all_outlets' => $allOutlets]);
        $user->companies()->syncWithoutDetaching([$company->id]);
        $user->outlets()->syncWithoutDetaching([$outlet->id]);
        setPermissionsTeamId($company->id);
        foreach ($permissions as $p) {
            $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_all_outlets_wastage_detail_lists_only_this_companys_items(): void
    {
        [$a, $outletA] = $this->company('Alpha');
        [$b, $outletB] = $this->company('Bravo');
        $this->wastage($a, $outletA, 'ALPHA FLOUR');
        $this->wastage($b, $outletB, 'BRAVO SECRET SAUCE');

        $this->actingAs($this->user($a, $outletA, ['reports.view']));

        $summary = app(CostSummaryService::class)->generate(now()->format('Y-m'), null);
        $names = collect($summary['wastage_detail'] ?? [])->flatten()->filter(fn ($v) => is_string($v))->implode('|');

        $this->assertStringContainsString('ALPHA FLOUR', $names);
        $this->assertStringNotContainsString('BRAVO SECRET SAUCE', $names, "Another company's wastage leaked into the cost summary.");
    }

    public function test_a_stock_take_from_an_outlet_the_user_cannot_access_is_not_served(): void
    {
        [$a, $outletA] = $this->company('Alpha');
        $other = Outlet::create(['company_id' => $a->id, 'name' => 'Alpha Other', 'code' => 'AO', 'is_active' => true]);

        $take = StockTake::create([
            'company_id' => $a->id, 'outlet_id' => $other->id, 'status' => 'completed', 'method' => 'detailed',
            'stock_take_date' => today()->toDateString(), 'reference_number' => 'ST-X',
            'total_stock_cost' => 0, 'total_variance_cost' => 0,
        ]);

        // Restricted to the main outlet only.
        $user = $this->user($a, $outletA, ['inventory.view'], allOutlets: false);

        $this->actingAs($user)->get(route('inventory.stock-takes.result', $take->id))->assertNotFound();
        $this->actingAs($user)->get(route('inventory.stock-takes.count-sheet', $take->id))->assertNotFound();
    }
}
