<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\OrderForm;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Department;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
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
 * Turning a purchase request into a purchase order.
 *
 * Reported as "the details is not copied, no item listed". Both halves were
 * real: the prefill carried only the notes, and every line without an
 * ingredient was dropped without a word — so an asset-only request opened a
 * completely empty order that looked broken rather than explained.
 */
class PurchaseRequestToOrderTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private UnitOfMeasure $piece;
    private User $user;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'PR2PO Co', 'slug' => Str::slug('PR2PO Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pc', 'type' => 'count']);

        $this->supplier = Supplier::create([
            'company_id' => $this->company->id, 'name' => 'Kitchen Kit Sdn Bhd', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(collect([
            'purchasing.view', 'purchasing.orders.create', 'purchasing.orders.edit',
            'purchasing.requests.create', 'assets.view',
        ])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->user);
        session(['active_outlet_id' => $this->outlet->id]);
    }

    private function request(array $attributes = []): PurchaseRequest
    {
        return PurchaseRequest::create(array_merge([
            'company_id'     => $this->company->id,
            'outlet_id'      => $this->outlet->id,
            'pr_number'      => 'PR-' . uniqid(),
            'requested_date' => now()->toDateString(),
            'status'         => 'approved',
            'created_by'     => $this->user->id,
        ], $attributes));
    }

    /** The form reads pr_id off the query string, as the link that opens it does. */
    private function openFrom(PurchaseRequest $pr)
    {
        request()->merge(['pr_id' => $pr->id]);

        return Livewire::withQueryParams(['pr_id' => $pr->id])->test(OrderForm::class);
    }

    private function ingredient(string $name, float $price = 0): Ingredient
    {
        return Ingredient::create([
            'company_id'     => $this->company->id,
            'name'           => $name,
            'base_uom_id'    => $this->piece->id,
            'recipe_uom_id'  => $this->piece->id,
            'is_active'      => true,
            'purchase_price' => $price,
        ]);
    }

    private function asset(string $name): Asset
    {
        return Asset::create([
            'company_id' => $this->company->id,
            'name'       => $name,
            'uom_id'     => $this->piece->id,
            'unit_cost'  => 2199,
            'is_active'  => true,
        ]);
    }

    public function test_the_requests_details_come_across(): void
    {
        $dept = Department::create([
            'company_id' => $this->company->id, 'name' => 'HOT KITCHEN', 'sort_order' => 1,
        ]);

        $pr = $this->request([
            'department_id'  => $dept->id,
            'needed_by_date' => now()->addDays(5)->toDateString(),
        ]);

        $pr->lines()->create([
            'ingredient_id'         => $this->ingredient('CHICKEN THIGH')->id,
            'quantity'              => 4,
            'uom_id'                => $this->piece->id,
            'preferred_supplier_id' => $this->supplier->id,
            'source'                => PurchaseRequestLine::SOURCE_SUPPLIER,
        ]);

        $this->openFrom($pr)
            ->assertSet('department_id', $dept->id)
            ->assertSet('expected_delivery_date', now()->addDays(5)->toDateString())
            ->assertSet('supplier_id', $this->supplier->id)
            ->assertSet('sourcePrId', $pr->id)
            ->assertCount('lines', 1);
    }

    public function test_an_asset_only_request_says_why_it_has_no_lines(): void
    {
        $pr = $this->request();
        $pr->lines()->create([
            'asset_id' => $this->asset('IPAD 11TH GENERATION')->id,
            'quantity' => 2,
            'uom_id'   => $this->piece->id,
            'source'   => PurchaseRequestLine::SOURCE_ASSET,
        ]);

        $this->openFrom($pr)->assertCount('lines', 0);

        $this->assertNotNull(session('error'), 'An empty order with no explanation is the bug.');
        $this->assertStringContainsString('1 asset line', session('error'));
    }

    public function test_a_mixed_request_orders_the_ingredient_and_flags_the_asset(): void
    {
        $pr = $this->request();

        $pr->lines()->create([
            'ingredient_id'         => $this->ingredient('FLOUR')->id,
            'quantity'              => 20,
            'uom_id'                => $this->piece->id,
            'preferred_supplier_id' => $this->supplier->id,
            'source'                => PurchaseRequestLine::SOURCE_SUPPLIER,
        ]);

        $pr->lines()->create([
            'asset_id' => $this->asset('STAND MIXER')->id,
            'quantity' => 1,
            'uom_id'   => $this->piece->id,
            'source'   => PurchaseRequestLine::SOURCE_ASSET,
        ]);

        $this->openFrom($pr)->assertCount('lines', 1);

        $this->assertNotNull(session('warning'));
        $this->assertStringContainsString('1 asset line', session('warning'));
    }

    public function test_the_order_records_and_belongs_to_the_request_it_came_from(): void
    {
        $pr = $this->request();
        $pr->lines()->create([
            'ingredient_id'         => $this->ingredient('RICE', 12)->id,
            'quantity'              => 3,
            'uom_id'                => $this->piece->id,
            'preferred_supplier_id' => $this->supplier->id,
            'source'                => PurchaseRequestLine::SOURCE_SUPPLIER,
        ]);

        $this->openFrom($pr)->call('save')->assertHasNoErrors();

        $po = PurchaseOrder::firstOrFail();

        $this->assertSame($pr->id, (int) $po->purchase_request_id, 'The link column was never filled.');
        $this->assertSame($this->outlet->id, (int) $po->outlet_id, 'The order belongs to the branch that asked.');
    }
}
