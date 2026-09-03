<?php

namespace Tests\Feature;

use App\Livewire\Inventory\Index as StockManagement;
use App\Models\Company;
use App\Models\Department;
use App\Models\Outlet;
use App\Models\PurchaseCapture;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Captured purchases, added up per supplier and filed as a PDF.
 *
 * The list answers "what did we buy". This answers "who are we paying", which
 * is the question the list is usually a step towards. The export carries the
 * screen's own filters, so what comes out of the button is a summary of exactly
 * the rows that were on screen when it was pressed — and never of more than
 * those, whatever the query string is edited to say.
 */
class PurchaseSupplierSummaryExportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Outlet $other;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Purchase Co', 'slug' => Str::slug('Purchase Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->other = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Annexe', 'code' => 'ANNX', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id, $this->other->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo([
            Permission::findOrCreate('inventory.view', 'web'),
            Permission::findOrCreate('inventory.purchases.record', 'web'),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->user);
    }

    private function supplier(string $name): Supplier
    {
        return Supplier::create(['company_id' => $this->company->id, 'name' => $name, 'is_active' => true]);
    }

    private function purchase(array $attributes = []): PurchaseCapture
    {
        return PurchaseCapture::create(array_merge([
            'company_id'    => $this->company->id,
            'outlet_id'     => $this->outlet->id,
            'purchase_date' => '2026-08-10',
            'amount'        => 100,
            'created_by'    => $this->user->id,
        ], $attributes));
    }

    /** Two linked suppliers and one typed by hand, across two months. */
    private function aMonthOfBuying(): void
    {
        $meat = $this->supplier('Fresh Meats');
        $veg  = $this->supplier('Green Grocer');

        $this->purchase(['supplier_id' => $meat->id, 'amount' => 600, 'purchase_date' => '2026-08-05', 'reference_number' => 'INV-1']);
        $this->purchase(['supplier_id' => $meat->id, 'amount' => 400, 'purchase_date' => '2026-09-02', 'reference_number' => 'INV-2']);
        $this->purchase(['supplier_id' => $veg->id,  'amount' => 300, 'purchase_date' => '2026-08-20', 'reference_number' => 'INV-3']);
        $this->purchase(['supplier_name' => 'Corner Shop', 'amount' => 200, 'purchase_date' => '2026-09-10', 'reference_number' => 'INV-4']);
    }

    private function summary(array $query = [])
    {
        return $this->get(route('inventory.purchases.supplier-summary', array_merge([
            'from' => '2026-08-01', 'to' => '2026-09-30',
        ], $query)));
    }

    // ── The file ─────────────────────────────────────────────────────────

    public function test_the_summary_downloads_as_a_pdf(): void
    {
        $this->aMonthOfBuying();

        $response = $this->summary();

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'Purchases-by-Supplier-2026-08-01-to-2026-09-30.pdf',
            $response->headers->get('content-disposition')
        );
    }

    public function test_a_range_with_no_purchases_still_renders(): void
    {
        // Nothing captured at all. The report has to say so rather than divide
        // by a zero total working out each supplier's share.
        $this->summary()->assertOk();
    }

    public function test_the_summary_route_does_not_swallow_the_capture_form(): void
    {
        // /inventory/purchases/supplier-summary and /inventory/purchases/{id}
        // are the same shape to the router. Registered the wrong way round, one
        // of them stops working — so both are asked for here.
        $purchase = $this->purchase(['amount' => 120]);

        $this->get(route('inventory.purchases.show', $purchase->id))->assertOk();
        $this->summary()->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_route_needs_the_inventory_permission(): void
    {
        $outsider = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
        ]);
        $outsider->companies()->syncWithoutDetaching([$this->company->id]);
        $outsider->outlets()->syncWithoutDetaching([$this->outlet->id]);

        $this->actingAs($outsider)->summary()->assertForbidden();
    }

    // ── The numbers ──────────────────────────────────────────────────────

    /**
     * The controller's own arithmetic, without going through dompdf.
     *
     * The PDF is a binary blob, so asserting on the rendered file tells you
     * only that it rendered. The view data is where the report is either right
     * or wrong, so that is what is checked.
     */
    private function reportData(array $query = []): array
    {
        $captured = [];

        \Illuminate\Support\Facades\View::creator('pdf.purchase-supplier-summary', function ($view) use (&$captured) {
            $captured = $view->getData();
        });

        $this->summary($query);

        return $captured;
    }

    public function test_it_totals_every_supplier_biggest_first(): void
    {
        $this->aMonthOfBuying();

        $data = $this->reportData();

        $this->assertSame(
            ['Fresh Meats', 'Green Grocer', 'Corner Shop'],
            array_column($data['suppliers'], 'name'),
            'Suppliers should be ranked by spend.'
        );

        $this->assertEqualsWithDelta(1000.0, $data['suppliers'][0]['spend'], 0.001);
        $this->assertSame(2, $data['suppliers'][0]['purchases']);
        // 1000 of 1500.
        $this->assertEqualsWithDelta(66.667, $data['suppliers'][0]['share'], 0.01);
        $this->assertEqualsWithDelta(500.0, $data['suppliers'][0]['average'], 0.001);

        $this->assertEqualsWithDelta(1500.0, $data['totals']['spend'], 0.001);
        $this->assertSame(4, $data['totals']['purchases']);
        $this->assertSame(3, $data['totals']['suppliers']);
        $this->assertSame('Fresh Meats', $data['totals']['topName']);
    }

    public function test_a_hand_typed_supplier_is_counted_as_a_supplier(): void
    {
        $this->aMonthOfBuying();

        $corner = collect($this->reportData()['suppliers'])->firstWhere('name', 'Corner Shop');

        $this->assertNotNull($corner, 'A capture with only a typed name is still spend with someone.');
        $this->assertNull($corner['supplier_id'], 'It has no Supplier record behind it.');
        $this->assertEqualsWithDelta(200.0, $corner['spend'], 0.001);
    }

    public function test_the_same_vendor_linked_and_typed_lands_in_one_row(): void
    {
        $meat = $this->supplier('Fresh Meats');

        $this->purchase(['supplier_id' => $meat->id, 'amount' => 300]);
        // Same vendor, entered before anybody made the supplier record.
        $this->purchase(['supplier_name' => 'Fresh Meats', 'amount' => 200]);

        $suppliers = $this->reportData()['suppliers'];

        $this->assertCount(1, $suppliers, 'One vendor is one row, however it was entered.');
        $this->assertEqualsWithDelta(500.0, $suppliers[0]['spend'], 0.001);
        $this->assertSame(2, $suppliers[0]['purchases']);
    }

    public function test_a_capture_naming_nobody_is_not_dropped(): void
    {
        $this->purchase(['amount' => 250]);

        $data = $this->reportData();

        $this->assertSame('Unspecified supplier', $data['suppliers'][0]['name']);
        // The point: it is money that left, so it has to be in the total even
        // though nobody can say who got it.
        $this->assertEqualsWithDelta(250.0, $data['totals']['spend'], 0.001);
    }

    public function test_spend_is_bucketed_by_month_oldest_first(): void
    {
        $this->aMonthOfBuying();

        $months = $this->reportData()['months'];

        $this->assertSame(['Aug 26', 'Sep 26'], array_column($months, 'label'));
        $this->assertEqualsWithDelta(900.0, $months[0]['spend'], 0.001);
        $this->assertEqualsWithDelta(600.0, $months[1]['spend'], 0.001);
        // Columns are drawn against the tallest month, so the biggest is full height.
        $this->assertEqualsWithDelta(100.0, $months[0]['height'], 0.001);
    }

    public function test_every_supplier_has_a_detail_block_to_link_to(): void
    {
        $this->aMonthOfBuying();

        $data = $this->reportData();

        $this->assertFalse($data['details']['omitted']);
        $this->assertCount(3, $data['details']['blocks']);

        $first = $data['details']['blocks'][0];
        $this->assertSame('Fresh Meats', $first['supplier']['name']);
        $this->assertCount(2, $first['rows'], 'The block lists that supplier own purchases.');
        $this->assertSame(0, $first['more']);

        // The anchor the chart links to is the anchor the block carries.
        $this->assertSame($data['suppliers'][0]['anchor'], $first['supplier']['anchor']);
    }

    // ── Scope ────────────────────────────────────────────────────────────

    public function test_it_covers_only_the_range_asked_for(): void
    {
        $this->aMonthOfBuying();

        $data = $this->reportData(['from' => '2026-09-01', 'to' => '2026-09-30']);

        $this->assertEqualsWithDelta(600.0, $data['totals']['spend'], 0.001);
        $this->assertSame(2, $data['totals']['purchases']);
    }

    public function test_a_backwards_range_is_read_forwards(): void
    {
        $this->aMonthOfBuying();

        // Nobody types this, but a hand-edited URL should narrow to something
        // sensible rather than to nothing at all.
        $data = $this->reportData(['from' => '2026-09-30', 'to' => '2026-09-01']);

        $this->assertEqualsWithDelta(600.0, $data['totals']['spend'], 0.001);
    }

    public function test_the_department_and_supplier_filters_travel_with_it(): void
    {
        $dept = Department::create(['company_id' => $this->company->id, 'name' => 'Hot Kitchen']);
        $meat = $this->supplier('Fresh Meats');
        $veg  = $this->supplier('Green Grocer');

        $this->purchase(['supplier_id' => $meat->id, 'amount' => 500, 'department_id' => $dept->id]);
        $this->purchase(['supplier_id' => $veg->id,  'amount' => 400, 'department_id' => $dept->id]);
        $this->purchase(['supplier_id' => $meat->id, 'amount' => 300]);

        $byDept = $this->reportData(['department' => (string) $dept->id]);
        $this->assertEqualsWithDelta(900.0, $byDept['totals']['spend'], 0.001);

        $unassigned = $this->reportData(['department' => 'none']);
        $this->assertEqualsWithDelta(300.0, $unassigned['totals']['spend'], 0.001);

        $oneSupplier = $this->reportData(['supplier' => (string) $meat->id]);
        $this->assertEqualsWithDelta(800.0, $oneSupplier['totals']['spend'], 0.001);
        $this->assertSame(1, $oneSupplier['totals']['suppliers']);
    }

    public function test_an_out_of_reach_outlet_never_widens_the_report(): void
    {
        $meat = $this->supplier('Fresh Meats');
        $this->purchase(['supplier_id' => $meat->id, 'amount' => 500, 'outlet_id' => $this->outlet->id]);
        $this->purchase(['supplier_id' => $meat->id, 'amount' => 700, 'outlet_id' => $this->other->id]);

        $narrowed = $this->reportData(['outlet' => (string) $this->other->id]);
        $this->assertEqualsWithDelta(700.0, $narrowed['totals']['spend'], 0.001);

        // An outlet this user cannot see is ignored, not honoured: the report
        // falls back to everything they may see, which is never more.
        $stranger = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Not Mine', 'code' => 'NOPE', 'is_active' => true,
        ]);
        $this->purchase(['supplier_id' => $meat->id, 'amount' => 900, 'outlet_id' => $stranger->id]);

        $single = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => false,
        ]);
        $single->companies()->syncWithoutDetaching([$this->company->id]);
        $single->outlets()->syncWithoutDetaching([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $single->givePermissionTo(Permission::findOrCreate('inventory.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($single);
        $data = $this->reportData(['outlet' => (string) $stranger->id]);

        $this->assertEqualsWithDelta(500.0, $data['totals']['spend'], 0.001, 'Only their own outlet.');
    }

    // ── The screen ───────────────────────────────────────────────────────

    public function test_the_button_carries_the_filters_the_table_is_showing(): void
    {
        $meat = $this->supplier('Fresh Meats');
        $this->purchase(['supplier_id' => $meat->id, 'amount' => 500]);

        $html = Livewire::actingAs($this->user)->test(StockManagement::class)
            ->set('tab', 'purchases')
            ->call('setQuickRange', 'all_time')
            ->set('supplierFilter', (string) $meat->id)
            ->html();

        $this->assertStringContainsString('supplier-summary', $html, 'The Purchases tab should offer the summary.');
        $this->assertStringContainsString('supplier=' . $meat->id, $html, 'It should carry the supplier filter over.');
    }

    public function test_the_button_is_offered_only_on_the_purchases_tab(): void
    {
        $this->aMonthOfBuying();

        $html = Livewire::actingAs($this->user)->test(StockManagement::class)
            ->set('tab', 'wastage')
            ->call('setQuickRange', 'all_time')
            ->html();

        $this->assertStringNotContainsString('supplier-summary', $html);
    }

    public function test_an_empty_list_offers_nothing_to_summarise(): void
    {
        $html = Livewire::actingAs($this->user)->test(StockManagement::class)
            ->set('tab', 'purchases')
            ->call('setQuickRange', 'all_time')
            ->html();

        $this->assertStringNotContainsString('supplier-summary', $html);
    }
}
