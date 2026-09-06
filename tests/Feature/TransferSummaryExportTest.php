<?php

namespace Tests\Feature;

use App\Livewire\Inventory\Index as StockManagement;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use App\Models\OutletTransferLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Transfers tab's "download by filter" pair and its interactive chart.
 *
 * A transfer carries no cost of its own (App\Livewire\Inventory\Index::TABS
 * marks 'amount' => null for this tab) — the value lives on its lines, so
 * both the export and the chart sum quantity × unit_cost per line first and
 * group the result by the outlet that sent it.
 */
class TransferSummaryExportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet1;
    private Outlet $outlet2;
    private User $user;
    private UnitOfMeasure $kg;
    private Ingredient $flour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Transfer Export Co', 'slug' => Str::slug('Transfer Export Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet1 = Outlet::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $this->outlet2 = Outlet::create(['company_id' => $this->company->id, 'name' => 'Second', 'code' => 'SEC', 'is_active' => true]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet1->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet1->id, $this->outlet2->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(Permission::findOrCreate('inventory.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->kg    = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight']);
        $this->flour = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Flour',
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $this->kg->id,
            'current_cost' => 5, 'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    private function transfer(Outlet $from, Outlet $to, float $qty, float $unitCost, string $date = '2026-08-05', string $status = 'received'): OutletTransfer
    {
        $transfer = OutletTransfer::create([
            'company_id' => $this->company->id, 'from_outlet_id' => $from->id, 'to_outlet_id' => $to->id,
            'transfer_number' => 'T-' . uniqid(), 'status' => $status, 'transfer_date' => $date,
        ]);

        OutletTransferLine::create([
            'outlet_transfer_id' => $transfer->id, 'ingredient_id' => $this->flour->id,
            'quantity' => $qty, 'uom_id' => $this->kg->id, 'unit_cost' => $unitCost,
        ]);

        return $transfer;
    }

    // ── The exports ──────────────────────────────────────────────────────

    public function test_the_pdf_downloads_for_the_chosen_range(): void
    {
        $this->transfer($this->outlet1, $this->outlet2, 10, 5);

        $response = $this->get(route('inventory.transfers.summary', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_excel_downloads(): void
    {
        $this->transfer($this->outlet1, $this->outlet2, 10, 5);

        $response = $this->get(route('inventory.transfers.summary-excel', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_value_is_computed_from_the_lines_not_the_header(): void
    {
        // 10kg at RM5/kg = RM50; the transfer header itself carries no amount.
        $this->transfer($this->outlet1, $this->outlet2, 10, 5);

        $controller = app(\App\Http\Controllers\TransferSummaryController::class);
        $data = $this->invokeLoad($controller, ['from' => '2026-08-01', 'to' => '2026-08-31']);

        $this->assertEqualsWithDelta(50.0, $data['totals']['value'], 0.001);
        $this->assertSame('Main', $data['groups'][0]['name']);
    }

    public function test_groups_by_the_sending_outlet(): void
    {
        $this->transfer($this->outlet1, $this->outlet2, 10, 5); // Main sends RM50
        $this->transfer($this->outlet2, $this->outlet1, 2, 5);  // Second sends RM10

        $controller = app(\App\Http\Controllers\TransferSummaryController::class);
        $data = $this->invokeLoad($controller, ['from' => '2026-08-01', 'to' => '2026-08-31']);

        $this->assertSame(['Main', 'Second'], array_column($data['groups'], 'name'));
        $this->assertEqualsWithDelta(50.0, $data['groups'][0]['value'], 0.001);
        $this->assertEqualsWithDelta(10.0, $data['groups'][1]['value'], 0.001);
    }

    public function test_a_status_filter_narrows_the_report(): void
    {
        $this->transfer($this->outlet1, $this->outlet2, 10, 5, '2026-08-05', 'received');
        $this->transfer($this->outlet1, $this->outlet2, 4, 5, '2026-08-06', 'draft');

        $controller = app(\App\Http\Controllers\TransferSummaryController::class);
        $data = $this->invokeLoad($controller, ['from' => '2026-08-01', 'to' => '2026-08-31', 'status' => 'received']);

        $this->assertEqualsWithDelta(50.0, $data['totals']['value'], 0.001);
        $this->assertSame(1, $data['totals']['count']);
    }

    // ── The chart ─────────────────────────────────────────────────────────

    public function test_the_chart_is_only_built_on_transfers(): void
    {
        foreach (['stock-takes', 'wastage', 'staff-meals', 'purchases'] as $tab) {
            $this->assertNull(
                Livewire::test(StockManagement::class)->set('tab', $tab)->call('setQuickRange', 'all_time')
                    ->viewData('transferChartData'),
                "{$tab} should not build the transfer outlet chart."
            );
        }
    }

    public function test_chart_matches_the_exports_computed_value(): void
    {
        $this->transfer($this->outlet1, $this->outlet2, 10, 5);

        $data = Livewire::test(StockManagement::class)
            ->set('tab', 'transfers')->call('setQuickRange', 'all_time')
            ->viewData('transferChartData');

        $this->assertSame(['Main'], $data['labels']);
        $this->assertEqualsWithDelta(50.0, $data['values'][0], 0.001);
        $this->assertSame($this->outlet1->id, $data['outletIds'][0]);
        $this->assertSame('transfer', $data['noun']);
    }

    public function test_clicking_a_bar_sets_the_outlet_dropdown_filter(): void
    {
        $this->transfer($this->outlet1, $this->outlet2, 10, 5);

        $component = Livewire::test(StockManagement::class)
            ->set('tab', 'transfers')->call('setQuickRange', 'all_time')
            ->call('filterByOutletChart', $this->outlet1->id);

        $component->assertSet('outletFilter', (string) $this->outlet1->id);
    }

    /** Invoke the protected load() the way the __invoke() methods do, without a full HTTP round trip. */
    private function invokeLoad(object $controller, array $query): array
    {
        $request = \Illuminate\Http\Request::create('/', 'GET', $query);
        $method  = new \ReflectionMethod($controller, 'load');
        $method->setAccessible(true);

        return $method->invoke($controller, $request);
    }
}
