<?php

namespace Tests\Feature;

use App\Livewire\Assets\MovementForm;
use App\Livewire\Purchasing\PurchaseRequestForm;
use App\Models\Asset;
use App\Models\CentralPurchasingUnit;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Asking for an asset on an ordinary purchase request.
 *
 * The point of putting asset lines on the existing request rather than a second
 * document is that somebody asking for a stand mixer goes through the same
 * approver and the same queue as somebody asking for flour. The point of
 * keeping them OUT of consolidation is that an asset is not received against a
 * food PO — so the two things this pins are that the line survives the round
 * trip, and that the consolidator says out loud that it left it behind.
 */
class AssetPurchaseRequestTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private UnitOfMeasure $piece;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Asset PR Co', 'slug' => Str::slug('Asset PR Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pc', 'type' => 'count']);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(collect([
            'purchasing.view', 'purchasing.requests.create', 'purchasing.requests.edit',
            'assets.view', 'assets.movements.record',
        ])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->user);
    }

    private function asset(string $name, float $cost = 4000, ?Supplier $supplier = null): Asset
    {
        $asset = Asset::create([
            'company_id' => $this->company->id, 'name' => $name,
            'uom_id' => $this->piece->id, 'unit_cost' => $cost, 'is_active' => true,
        ]);

        if ($supplier) {
            $asset->supplierLinks()->create([
                'supplier_id' => $supplier->id, 'last_cost' => $cost, 'is_preferred' => true,
            ]);
        }

        return $asset;
    }

    public function test_an_asset_line_can_be_raised_on_a_purchase_request(): void
    {
        $supplier = Supplier::create([
            'company_id' => $this->company->id, 'name' => 'Kitchen Kit Sdn Bhd', 'is_active' => true,
        ]);
        $mixer = $this->asset('STAND MIXER', 4250, $supplier);

        Livewire::test(PurchaseRequestForm::class)
            ->call('addAsset', $mixer->id)
            ->set('lines.0.quantity', 2)
            ->call('save', 'submit')
            ->assertHasNoErrors();

        $line = PurchaseRequestLine::firstOrFail();

        $this->assertSame($mixer->id, (int) $line->asset_id);
        $this->assertNull($line->ingredient_id, 'An asset line carries no ingredient — that is what keeps it out of a food PO.');
        $this->assertSame(PurchaseRequestLine::SOURCE_ASSET, $line->source);
        $this->assertSame($supplier->id, (int) $line->preferred_supplier_id, 'The preferred supplier comes across.');
        $this->assertSame(2.0, (float) $line->quantity);
        $this->assertTrue($line->isAssetItem());
        $this->assertSame('STAND MIXER', $line->displayName());
    }

    public function test_the_same_asset_is_not_added_twice(): void
    {
        $mixer = $this->asset('STAND MIXER');

        Livewire::test(PurchaseRequestForm::class)
            ->call('addAsset', $mixer->id)
            ->call('addAsset', $mixer->id)
            ->assertCount('lines', 1);
    }

    /**
     * Consolidation carries the asset now.
     *
     * This test was originally the safety argument for the opposite rule —
     * that an asset line has no ingredient and every consolidation path
     * therefore skipped it. That was true until consolidation was taught to
     * carry assets, so that the same request stops behaving differently
     * depending on whether it was converted directly or consolidated. The
     * merge and pricing rules it now depends on are covered in full by
     * ConsolidateAssetLinesTest.
     */
    public function test_consolidation_orders_an_asset_line(): void
    {
        $supplier = Supplier::create([
            'company_id' => $this->company->id, 'name' => 'Kitchen Kit Sdn Bhd', 'is_active' => true,
        ]);
        $mixer = $this->asset('STAND MIXER', 4250, $supplier);

        $cpu = CentralPurchasingUnit::create([
            'company_id' => $this->company->id, 'name' => 'HQ Purchasing', 'is_active' => true,
        ]);

        $pr = PurchaseRequest::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'pr_number' => 'PR-TEST-001', 'status' => PurchaseRequest::STATUS_APPROVED,
            'requested_date' => now()->toDateString(), 'created_by' => $this->user->id,
        ]);
        $pr->lines()->create([
            'asset_id' => $mixer->id, 'quantity' => 2, 'uom_id' => $this->piece->id,
            'preferred_supplier_id' => $supplier->id, 'source' => PurchaseRequestLine::SOURCE_ASSET,
        ]);

        $preview = PurchaseRequestService::consolidationPreviewWithCosts([$pr->id]);

        $this->assertSame(1, $preview['asset_line_count'],
            'Still counted, so the screen can say what happens to it after the order.');
        $this->assertCount(1, $preview['groups'], 'An asset-only request now produces a supplier group.');

        PurchaseRequestService::consolidate([$pr->id], $cpu->id);

        $po = PurchaseOrder::with('lines')->firstOrFail();

        $this->assertCount(1, $po->lines);
        $this->assertSame($mixer->id, (int) $po->lines->first()->asset_id);
        $this->assertNull($po->lines->first()->ingredient_id);
    }

    public function test_an_ingredient_and_an_asset_both_consolidate(): void
    {
        $supplier = Supplier::create([
            'company_id' => $this->company->id, 'name' => 'Dry Goods Sdn Bhd', 'is_active' => true,
        ]);
        $mixer = $this->asset('STAND MIXER', 4250, $supplier);

        $flour = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'FLOUR',
            'base_uom_id' => $this->piece->id, 'recipe_uom_id' => $this->piece->id,
            'purchase_price' => 5, 'pack_size' => 1, 'yield_percent' => 100,
            'current_cost' => 5, 'is_active' => true,
        ]);

        $cpu = CentralPurchasingUnit::create([
            'company_id' => $this->company->id, 'name' => 'HQ Purchasing', 'is_active' => true,
        ]);

        $pr = PurchaseRequest::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'pr_number' => 'PR-TEST-002', 'status' => PurchaseRequest::STATUS_APPROVED,
            'requested_date' => now()->toDateString(), 'created_by' => $this->user->id,
        ]);
        $pr->lines()->create([
            'asset_id' => $mixer->id, 'quantity' => 1, 'uom_id' => $this->piece->id,
            'preferred_supplier_id' => $supplier->id, 'source' => PurchaseRequestLine::SOURCE_ASSET,
        ]);
        $pr->lines()->create([
            'ingredient_id' => $flour->id, 'quantity' => 20, 'uom_id' => $this->piece->id,
            'preferred_supplier_id' => $supplier->id, 'source' => PurchaseRequestLine::SOURCE_SUPPLIER,
        ]);

        PurchaseRequestService::consolidate([$pr->id], $cpu->id);

        $po = PurchaseOrder::firstOrFail();

        // Both are ordered, from the one supplier they share, and they stay
        // two lines — the mixer's null ingredient_id must not fold it into
        // the flour.
        $this->assertSame(2, $po->lines()->count());
        $this->assertSame($flour->id, (int) $po->lines()->whereNotNull('ingredient_id')->first()->ingredient_id);
        $this->assertSame($mixer->id, (int) $po->lines()->whereNotNull('asset_id')->first()->asset_id);
    }

    /**
     * The other half of the loop: the request said what was wanted, the receipt
     * says what turned up. Quantities carry; prices do not, because a request
     * has none on it.
     */
    public function test_a_receipt_prefills_from_the_asset_lines_of_a_request(): void
    {
        $supplier = Supplier::create([
            'company_id' => $this->company->id, 'name' => 'Kitchen Kit Sdn Bhd', 'is_active' => true,
        ]);
        $mixer = $this->asset('STAND MIXER', 4250, $supplier);

        $flour = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'FLOUR',
            'base_uom_id' => $this->piece->id, 'recipe_uom_id' => $this->piece->id,
            'purchase_price' => 5, 'pack_size' => 1, 'yield_percent' => 100,
            'current_cost' => 5, 'is_active' => true,
        ]);

        $pr = PurchaseRequest::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'pr_number' => 'PR-TEST-003', 'status' => PurchaseRequest::STATUS_APPROVED,
            'requested_date' => now()->toDateString(), 'created_by' => $this->user->id,
        ]);
        $pr->lines()->create([
            'asset_id' => $mixer->id, 'quantity' => 3, 'uom_id' => $this->piece->id,
            'preferred_supplier_id' => $supplier->id, 'source' => PurchaseRequestLine::SOURCE_ASSET,
        ]);
        $pr->lines()->create([
            'ingredient_id' => $flour->id, 'quantity' => 20, 'uom_id' => $this->piece->id,
            'source' => PurchaseRequestLine::SOURCE_SUPPLIER,
        ]);

        Livewire::withQueryParams(['pr' => $pr->id])
            ->test(MovementForm::class)
            ->assertCount('lines', 1)
            ->assertSet('lines.0.asset_id', $mixer->id)
            ->assertSet('lines.0.quantity', '3')
            ->assertSet('supplier_id', $supplier->id)
            ->assertSet('reference_number', 'PR-TEST-003');
    }
}
