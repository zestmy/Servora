<?php

namespace Tests\Feature;

use App\Livewire\Inventory\Index as StockManagement;
use App\Livewire\Purchasing\Index as Purchasing;
use App\Livewire\Sales\Index as Sales;
use App\Models\Company;
use App\Models\Department;
use App\Models\Outlet;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A list comes back on ALL of its filters, not just the outlet.
 *
 * The outlet was made sticky first and the rest were left: returning to Stock
 * Management or Purchasing kept the branch and the tab, then reset the date
 * range, status, supplier and department. bootQuickRange() re-applies the
 * default on every mount, so "last 7 days" became "last 30" on the way back
 * from every record you opened.
 *
 * Search stays deliberately unremembered. It is how you find one record, not a
 * place in a list, and a search box that refills itself when you return reads
 * as a broken list rather than a helpful one. So does a tab: that belongs to
 * the URL, and the forms already redirect back with it.
 */
class StickyListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $own;
    private Outlet $other;
    private Department $dept;
    private Supplier $supplier;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Sticky Co', 'slug' => Str::slug('Sticky Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->own   = $this->outlet('ALPHA', 'ALP');
        $this->other = $this->outlet('BETA', 'BET');

        $this->dept = Department::create([
            'company_id' => $this->company->id, 'name' => 'Kitchen', 'is_active' => true,
        ]);
        $this->supplier = Supplier::create([
            'company_id' => $this->company->id, 'name' => 'Acme', 'code' => 'ACM', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->own->id, $this->other->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['inventory.view', 'purchasing.view', 'sales.view'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(Permission::all()->pluck('name')->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    /**
     * Set some filters, then mount afresh — which is what returning from a
     * form is — and report what came back.
     *
     * @param  array<string, string>  $filters
     * @return array<string, string>
     */
    private function roundTrip(string $class, array $filters, array $mountParams = []): array
    {
        $set = Livewire::actingAs($this->user)->test($class, $mountParams);
        foreach ($filters as $property => $value) {
            $set->set($property, $value);
        }

        $back = Livewire::actingAs($this->user)->test($class, $mountParams);

        $out = [];
        foreach (array_keys($filters) as $property) {
            $out[$property] = (string) $back->get($property);
        }

        return $out;
    }

    public function test_stock_management_comes_back_on_its_whole_filter_strip(): void
    {
        $chosen = [
            'outletFilter'     => (string) $this->other->id,
            'departmentFilter' => (string) $this->dept->id,
            'supplierFilter'   => (string) $this->supplier->id,
            'quickRange'       => 'last_7_days',
        ];

        $this->assertSame($chosen, $this->roundTrip(StockManagement::class, $chosen));
    }

    public function test_purchasing_comes_back_on_its_whole_filter_strip(): void
    {
        $chosen = [
            'outletFilter'   => (string) $this->other->id,
            'supplierFilter' => (string) $this->supplier->id,
            'statusFilter'   => 'draft',
            'quickRange'     => 'last_7_days',
        ];

        $this->assertSame($chosen, $this->roundTrip(Purchasing::class, $chosen));
    }

    /** A hand-typed range is a range too, and drops the named one. */
    public function test_a_typed_date_range_survives(): void
    {
        $chosen = ['dateFrom' => '2026-01-01', 'dateTo' => '2026-01-31'];

        $this->assertSame($chosen, $this->roundTrip(Sales::class, $chosen));
    }

    public function test_sales_comes_back_on_its_period_and_outlet(): void
    {
        $chosen = ['outletFilter' => (string) $this->other->id, 'quickRange' => 'last_7_days'];

        $this->assertSame($chosen, $this->roundTrip(Sales::class, $chosen));
    }

    // ── What must NOT stick ───────────────────────────────────────────────

    public function test_the_search_box_does_not_refill_itself(): void
    {
        $this->assertSame(['search' => ''],
            $this->roundTrip(StockManagement::class, ['search' => 'widget']));
    }

    /**
     * Each tab keeps its own. A supplier chosen while looking at Purchases
     * must not be waiting on Wastage, which cannot even use it.
     */
    public function test_one_tabs_filters_do_not_follow_you_to_another(): void
    {
        Livewire::actingAs($this->user)->test(StockManagement::class, ['tab' => 'purchases'])
            ->set('supplierFilter', (string) $this->supplier->id);

        $wastage = Livewire::actingAs($this->user)->test(StockManagement::class, ['tab' => 'wastage']);

        $this->assertSame('', (string) $wastage->get('supplierFilter'),
            'A filter restored onto a tab that cannot use it is a filter doing nothing while looking like it is.');
    }

    /** And the tab itself still comes from the URL, never the session. */
    public function test_the_tab_is_not_remembered(): void
    {
        Livewire::actingAs($this->user)->test(StockManagement::class, ['tab' => 'purchases']);

        $this->assertSame('stock-takes',
            (string) Livewire::actingAs($this->user)->test(StockManagement::class)->get('tab'),
            'Opening the screen plain must land on its own default tab.');
    }

    /** Nothing remembered yet still means the shipped defaults. */
    public function test_a_first_visit_gets_the_default_range(): void
    {
        $fresh = Livewire::actingAs($this->user)->test(StockManagement::class);

        $this->assertSame('last_30', (string) $fresh->get('quickRange'));
        $this->assertSame('', (string) $fresh->get('supplierFilter'));
    }
}
