<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\PurchaseCapture;
use App\Models\PurchaseRecord;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Dashboard purchases count BOTH places a purchase is recorded.
 *
 * Stock Management > Purchases saves to purchase_captures; only goods received
 * against a PO reach purchase_records, which was all the dashboard read — so a
 * company that records its buying in Stock Management saw almost no spend.
 */
class DashboardPurchasesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-12 10:00:00'));

        $this->company = Company::create([
            'name' => 'Spend Co', 'slug' => Str::slug('Spend Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        // purchasing.view without sales.view opens the purchasing dashboard,
        // which carries spend, top suppliers and the monthly trend together.
        setPermissionsTeamId($this->company->id);
        Permission::findOrCreate('purchasing.view', 'web');
        $this->user->givePermissionTo('purchasing.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_spend_suppliers_and_trend_include_stock_management_purchases(): void
    {
        $meats = Supplier::create(['company_id' => $this->company->id, 'name' => 'Fresh Meats', 'is_active' => true]);

        // Received against a PO.
        PurchaseRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'supplier_id' => $meats->id, 'purchase_date' => '2026-08-06', 'total_amount' => 400,
        ]);
        // Keyed in on Stock Management — the same supplier by typed name, and another.
        PurchaseCapture::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'supplier_name' => 'fresh meats', 'purchase_date' => '2026-08-05', 'amount' => 120,
        ]);
        PurchaseCapture::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'supplier_name' => 'Veg Farm', 'purchase_date' => '2026-08-07', 'amount' => 80,
        ]);
        // Last month, for the comparison.
        PurchaseCapture::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'supplier_name' => 'Veg Farm', 'purchase_date' => '2026-07-08', 'amount' => 50,
        ]);

        $c = Livewire::actingAs($this->user)->test(Dashboard::class, ['period' => 'this_month']);

        $this->assertSame('purchasing', $c->viewData('dashboardType'));

        $spend = $c->viewData('spend');
        $this->assertEquals(600, $spend['value'], 'RM400 received + RM200 keyed in — keyed-in purchases were missing.');
        $this->assertEquals(50, $spend['prior']);

        $suppliers = $c->viewData('topSuppliers');
        $this->assertSame('Fresh Meats', $suppliers[0]['name']);
        $this->assertEquals(520, $suppliers[0]['total'], 'Linked and typed "Fresh Meats" are one supplier.');
        $this->assertSame('Veg Farm', $suppliers[1]['name']);
        $this->assertCount(2, $suppliers);

        $trend = collect($c->viewData('trendMonths'))->keyBy('label');
        $this->assertEquals(600, $trend['Aug']['purchases']);
        $this->assertEquals(50, $trend['Jul']['purchases']);
    }
}
