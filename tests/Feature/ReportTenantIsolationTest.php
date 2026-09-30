<?php

namespace Tests\Feature;

use App\Livewire\Reports\Hub;
use App\Livewire\Reports\Inventory\StockCard;
use App\Livewire\Reports\InventoryAction\StockAdjustment;
use App\Livewire\Reports\InventoryAction\StockCountAnalysis;
use App\Livewire\Reports\Menu\SalesMenuIngredients;
use App\Livewire\Reports\Order\OrderItemsByBranch;
use App\Livewire\Reports\PriceHistory;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientPriceHistory;
use App\Models\OrderAdjustmentLog;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRecord;
use App\Models\PurchaseRecordLine;
use App\Models\SalesRecord;
use App\Models\SalesRecordLine;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Reports read line tables (stock_take_lines, sales_record_lines,
 * purchase_order_lines, ingredient_price_history, order_adjustment_logs) that
 * carry no company of their own and no global scope, through raw joins that
 * never apply the parent model's scope. With no outlet filter, several
 * reports returned every company's rows. Each report must bound the
 * company-owned parent table itself.
 */
class ReportTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private UnitOfMeasure $kg;

    /** @var array{company: Company, outlet: Outlet, user: User, ingredient: Ingredient} */
    private array $a;
    private array $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kg = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight', 'base_unit_factor' => 1000]);

        $this->a = $this->tenant('Alpha', 'ALPHA SAFFRON');
        $this->b = $this->tenant('Bravo', 'BRAVO TRUFFLE');
    }

    private function tenant(string $name, string $ingredientName): array
    {
        $company = Company::create([
            'name' => $name . ' Co', 'slug' => Str::slug($name) . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true, 'registered_via' => 'seeder',
        ]);

        $outlet = Outlet::create([
            'company_id' => $company->id, 'name' => $name . ' Main', 'code' => strtoupper(substr($name, 0, 3)), 'is_active' => true,
        ]);

        $user = $this->reportUser($company, [$outlet], true);

        $ingredient = Ingredient::create([
            'company_id' => $company->id, 'name' => $ingredientName,
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $this->kg->id,
            'current_cost' => 3.00, 'is_active' => true,
        ]);

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => $name . ' Supplies', 'is_active' => true]);

        return compact('company', 'outlet', 'user', 'ingredient', 'supplier', 'name');
    }

    private function reportUser(Company $company, array $outlets, bool $viewAll): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id, 'outlet_id' => $outlets[0]->id,
            'can_view_all_outlets' => $viewAll,
        ]);
        $user->companies()->syncWithoutDetaching([$company->id]);
        $user->outlets()->syncWithoutDetaching(collect($outlets)->pluck('id')->all());

        setPermissionsTeamId($company->id);
        $user->givePermissionTo(Permission::findOrCreate('reports.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function stockCount(array $t, ?Outlet $outlet = null): void
    {
        $take = StockTake::create([
            'company_id' => $t['company']->id, 'outlet_id' => ($outlet ?? $t['outlet'])->id,
            'status' => 'completed', 'method' => 'detailed',
            'stock_take_date' => '2026-08-10', 'reference_number' => 'ST-' . $t['name'],
            'total_stock_cost' => 0, 'total_variance_cost' => 0,
        ]);

        StockTakeLine::create([
            'stock_take_id' => $take->id, 'ingredient_id' => $t['ingredient']->id,
            'uom_id' => $this->kg->id, 'system_quantity' => 5,
            'actual_quantity' => 4, 'variance_quantity' => -1, 'unit_cost' => 3, 'variance_cost' => -3,
        ]);
    }

    private function sale(array $t): void
    {
        $record = SalesRecord::create([
            'company_id' => $t['company']->id, 'outlet_id' => $t['outlet']->id,
            'sale_date' => '2026-08-10', 'total_revenue' => 100, 'total_cost' => 30,
        ]);

        SalesRecordLine::create([
            'sales_record_id' => $record->id, 'item_name' => $t['ingredient']->name . ' PLATE',
            'quantity' => 2, 'unit_price' => 50, 'unit_cost' => 15, 'total_revenue' => 100, 'total_cost' => 30,
        ]);
    }

    private function purchaseOrderLine(array $t): PurchaseOrderLine
    {
        $po = PurchaseOrder::create([
            'company_id' => $t['company']->id, 'outlet_id' => $t['outlet']->id,
            'supplier_id' => $t['supplier']->id, 'po_number' => 'PO-' . Str::random(6),
            'status' => 'sent', 'order_date' => '2026-08-10',
        ]);

        return PurchaseOrderLine::create([
            'purchase_order_id' => $po->id, 'ingredient_id' => $t['ingredient']->id,
            'quantity' => 3, 'uom_id' => $this->kg->id, 'unit_cost' => 2, 'total_cost' => 6,
        ]);
    }

    private function purchase(array $t): void
    {
        $record = PurchaseRecord::create([
            'company_id' => $t['company']->id, 'outlet_id' => $t['outlet']->id,
            'purchase_date' => '2026-08-10', 'reference_number' => 'PR-' . $t['name'],
        ]);

        PurchaseRecordLine::create([
            'purchase_record_id' => $record->id, 'ingredient_id' => $t['ingredient']->id,
            'quantity' => 2, 'uom_id' => $this->kg->id, 'unit_cost' => 1, 'total_cost' => 2,
        ]);
    }

    private function inAugust($test)
    {
        return $test->set('dateFrom', '2026-08-01')->set('dateTo', '2026-08-31');
    }

    // ── The leaks ─────────────────────────────────────────────────────────

    public function test_stock_count_analysis_shows_only_the_active_company(): void
    {
        $this->stockCount($this->a);
        $this->stockCount($this->b);

        $this->inAugust(Livewire::actingAs($this->a['user'])->test(StockCountAnalysis::class))
            ->assertSee('ALPHA SAFFRON')
            ->assertDontSee('BRAVO TRUFFLE');
    }

    public function test_sales_menu_ingredients_shows_only_the_active_company(): void
    {
        $this->sale($this->a);
        $this->sale($this->b);

        $this->inAugust(Livewire::actingAs($this->a['user'])->test(SalesMenuIngredients::class))
            ->assertSee('ALPHA SAFFRON PLATE')
            ->assertDontSee('BRAVO TRUFFLE PLATE');
    }

    public function test_order_items_by_branch_shows_only_the_active_company(): void
    {
        $this->purchaseOrderLine($this->a);
        $this->purchaseOrderLine($this->b);

        $this->inAugust(Livewire::actingAs($this->a['user'])->test(OrderItemsByBranch::class))
            ->assertSee('ALPHA SAFFRON')
            ->assertDontSee('BRAVO TRUFFLE')
            ->assertDontSee('Bravo Main');
    }

    public function test_price_history_shows_only_the_active_company(): void
    {
        foreach ([$this->a, $this->b] as $t) {
            foreach ([['2026-08-05', 2.00], ['2026-08-20', 2.50]] as [$date, $cost]) {
                IngredientPriceHistory::create([
                    'ingredient_id' => $t['ingredient']->id, 'supplier_id' => $t['supplier']->id,
                    'cost' => $cost, 'uom_id' => $this->kg->id, 'effective_date' => $date, 'source' => 'manual',
                ]);
            }
        }

        $test = $this->inAugust(Livewire::actingAs($this->a['user'])->test(PriceHistory::class))
            ->assertSee('ALPHA SAFFRON')
            ->assertDontSee('BRAVO TRUFFLE');

        $stats = $test->viewData('stats');
        $this->assertSame(2, $stats['totalRecords'], 'only company A price records are counted');
        $this->assertSame(1, $stats['uniqueIngredients']);
        $this->assertSame(1, $stats['increases']);
    }

    public function test_stock_card_refuses_another_companys_ingredient(): void
    {
        $this->purchase($this->a);
        $this->purchase($this->b);

        $theirs = $this->inAugust(Livewire::actingAs($this->a['user'])->test(StockCard::class))
            ->set('ingredientFilter', $this->b['ingredient']->id);
        $this->assertCount(0, $theirs->viewData('movements'), 'another company\'s ingredient id must card nothing');
        $theirs->assertDontSee('PR-Bravo');

        $ours = $this->inAugust(Livewire::actingAs($this->a['user'])->test(StockCard::class))
            ->set('ingredientFilter', $this->a['ingredient']->id);
        $this->assertCount(1, $ours->viewData('movements'));
        $ours->assertSee('PR-Alpha');
    }

    public function test_stock_adjustment_shows_only_the_active_company(): void
    {
        foreach ([$this->a, $this->b] as $t) {
            OrderAdjustmentLog::create([
                'adjustable_type' => PurchaseOrderLine::class,
                'adjustable_id'   => $this->purchaseOrderLine($t)->id,
                'field'           => 'quantity',
                'old_value'       => '3', 'new_value' => '2',
                'reason'          => $t['name'] . ' short delivery',
                'adjusted_by'     => $t['user']->id,
                'created_at'      => '2026-08-10 10:00:00',
            ]);
        }

        $this->inAugust(Livewire::actingAs($this->a['user'])->test(StockAdjustment::class))
            ->assertSee('Alpha short delivery')
            ->assertDontSee('Bravo short delivery');
    }

    // ── Outlet reach inside the company ──────────────────────────────────

    public function test_an_outlet_restricted_user_sees_only_their_outlets(): void
    {
        $other = Outlet::create([
            'company_id' => $this->a['company']->id, 'name' => 'Alpha Annex', 'code' => 'ANX', 'is_active' => true,
        ]);
        $restricted = $this->reportUser($this->a['company'], [$this->a['outlet']], false);

        $this->stockCount($this->a, $other);

        $test = $this->inAugust(Livewire::actingAs($restricted)->test(StockCountAnalysis::class));

        $outletIds = $test->viewData('outlets')->pluck('id')->all();
        $this->assertSame([$this->a['outlet']->id], $outletIds, 'the dropdown lists only outlets the user may see');

        // Neither unfiltered nor by picking the outlet they cannot see.
        $test->assertDontSee('ALPHA SAFFRON');
        $test->set('outletFilter', $other->id)
            ->assertSet('outletFilter', null)
            ->assertDontSee('ALPHA SAFFRON');

        // Another company's outlet id is refused the same way.
        $test->set('outletFilter', $this->b['outlet']->id)->assertSet('outletFilter', null);
    }

    public function test_an_all_outlets_user_still_sees_every_outlet_of_their_company(): void
    {
        $other = Outlet::create([
            'company_id' => $this->a['company']->id, 'name' => 'Alpha Annex', 'code' => 'ANX', 'is_active' => true,
        ]);
        $this->stockCount($this->a, $other);

        $test = $this->inAugust(Livewire::actingAs($this->a['user'])->test(StockCountAnalysis::class))
            ->assertSee('ALPHA SAFFRON');

        $this->assertEqualsCanonicalizing(
            [$this->a['outlet']->id, $other->id],
            $test->viewData('outlets')->pluck('id')->all()
        );
    }

    // ── The hub follows the plan, as the sidebar does ────────────────────

    public function test_the_hub_locks_reports_the_plan_does_not_include(): void
    {
        // A self-signup company with no subscription is on Free.
        $this->a['company']->forceFill(['registered_via' => 'self_signup'])->save();

        $reports = collect(Livewire::actingAs($this->a['user'])->test(Hub::class)->viewData('categories'))
            ->flatMap(fn ($c) => $c['reports'])
            ->keyBy('route');

        $this->assertSame('basic', $reports['reports.purchase-analysis']['locked'] ?? null);
        $this->assertArrayNotHasKey('locked', $reports['reports.menu-ingredients'], 'core reports stay open');
    }

    public function test_the_hub_opens_everything_for_a_company_with_every_module(): void
    {
        $reports = collect(Livewire::actingAs($this->a['user'])->test(Hub::class)->viewData('categories'))
            ->flatMap(fn ($c) => $c['reports']);

        $this->assertTrue($reports->every(fn ($r) => empty($r['locked'])));
    }
}
