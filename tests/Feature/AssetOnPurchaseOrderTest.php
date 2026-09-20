<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\ConvertToDoForm;
use App\Livewire\Purchasing\OrderForm;
use App\Models\Asset;
use App\Models\Company;
use App\Models\GoodsReceivedNote;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
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
 * Assets on a purchase order — phase one.
 *
 * An asset could be ASKED for on a request but never ordered: the line was
 * dropped on conversion because purchase_order_lines.ingredient_id was NOT
 * NULL. It now carries.
 *
 * The other half of this file is the part that must not move. A purchase
 * order becomes a delivery order, which becomes a GRN, and receiving a GRN
 * writes a PurchaseRecord — the inventory receipt, whose ingredient_id is
 * also NOT NULL. An asset reaching there is either a hard 500 or a phantom
 * stock movement against nothing, so both doorways into that chain leave
 * asset lines behind and say so. Receiving an ordered asset into the asset
 * register is phase two; until it exists, these tests are what stops an
 * asset getting into stock.
 */
class AssetOnPurchaseOrderTest extends TestCase
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
            'name' => 'Asset PO Co', 'slug' => Str::slug('Asset PO Co') . '-' . uniqid(),
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

    private function asset(string $name = 'STAND MIXER', float $cost = 4250): Asset
    {
        return Asset::create([
            'company_id' => $this->company->id, 'name' => $name,
            'uom_id' => $this->piece->id, 'unit_cost' => $cost, 'is_active' => true,
        ]);
    }

    private function ingredient(string $name = 'FLOUR'): Ingredient
    {
        return Ingredient::create([
            'company_id' => $this->company->id, 'name' => $name,
            'base_uom_id' => $this->piece->id, 'recipe_uom_id' => $this->piece->id,
            'is_active' => true, 'purchase_price' => 10,
        ]);
    }

    private function requestWith(array $lines): PurchaseRequest
    {
        $pr = PurchaseRequest::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'pr_number' => 'PR-' . uniqid(), 'requested_date' => now()->toDateString(),
            'status' => 'approved', 'created_by' => $this->user->id,
        ]);

        foreach ($lines as $line) {
            $pr->lines()->create(array_merge([
                'quantity' => 1, 'uom_id' => $this->piece->id,
                'preferred_supplier_id' => $this->supplier->id,
            ], $line));
        }

        return $pr;
    }

    private function openFrom(PurchaseRequest $pr)
    {
        request()->merge(['pr_id' => $pr->id]);

        return Livewire::withQueryParams(['pr_id' => $pr->id])->test(OrderForm::class);
    }

    /** An order raised for a PO with one asset and one ingredient on it. */
    private function orderWithAssetAndIngredient(): PurchaseOrder
    {
        $pr = $this->requestWith([
            ['ingredient_id' => $this->ingredient()->id, 'quantity' => 20, 'source' => PurchaseRequestLine::SOURCE_SUPPLIER],
            ['asset_id' => $this->asset()->id, 'quantity' => 2, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        $this->openFrom($pr)->call('save')->assertHasNoErrors();

        return PurchaseOrder::with('lines')->firstOrFail();
    }

    public function test_an_asset_on_a_request_becomes_an_order_line(): void
    {
        $po = $this->orderWithAssetAndIngredient();

        $this->assertCount(2, $po->lines, 'The asset line is no longer dropped on conversion.');

        $assetLine = $po->lines->firstWhere('asset_id', '!=', null);

        $this->assertNotNull($assetLine);
        $this->assertNull($assetLine->ingredient_id, 'An asset line carries no ingredient.');
        $this->assertSame(2.0, (float) $assetLine->quantity);
        $this->assertSame(4250.0, (float) $assetLine->unit_cost, 'Cost comes off the asset, not a supplier price list.');
        $this->assertSame('STAND MIXER', $assetLine->displayName());
        $this->assertTrue($assetLine->isAssetItem());
    }

    public function test_an_asset_only_request_now_makes_a_real_order(): void
    {
        $pr = $this->requestWith([
            ['asset_id' => $this->asset('IPAD 11TH GENERATION', 2199)->id, 'quantity' => 3, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        $this->openFrom($pr)->assertCount('lines', 1)->call('save')->assertHasNoErrors();

        $po = PurchaseOrder::with('lines')->firstOrFail();

        $this->assertCount(1, $po->lines);
        $this->assertSame('IPAD 11TH GENERATION', $po->lines->first()->displayName());
    }

    public function test_the_order_reopens_with_the_asset_line_intact(): void
    {
        $po = $this->orderWithAssetAndIngredient();

        Livewire::test(OrderForm::class, ['id' => $po->id])
            ->assertCount('lines', 2)
            ->call('save')
            ->assertHasNoErrors();

        $po->refresh()->load('lines');

        $assetLine = $po->lines->firstWhere('asset_id', '!=', null);

        $this->assertNotNull($assetLine, 'Re-saving must not lose the asset line.');
        $this->assertSame(4250.0, (float) $assetLine->unit_cost,
            'The supplier price lookup has no row for an asset and must not overwrite its cost.');
    }

    public function test_a_line_naming_neither_an_ingredient_nor_an_asset_is_refused(): void
    {
        $pr = $this->requestWith([
            ['ingredient_id' => $this->ingredient()->id, 'quantity' => 5, 'source' => PurchaseRequestLine::SOURCE_SUPPLIER],
        ]);

        $this->openFrom($pr)
            ->set('lines.0.ingredient_id', null)
            ->call('save')
            ->assertHasErrors('lines.0.ingredient_id');
    }

    /* ── Down the chain ─────────────────────────────────────────────────
       Phase two opened the delivery chain to assets, so what this once
       pinned — the asset being left off — is no longer the right answer.
       Receiving itself is covered in ReceiveAnOrderedAssetTest; what stays
       here is that the ingredient half is untouched by any of it. */

    public function test_the_asset_travels_with_the_delivery(): void
    {
        $po = $this->orderWithAssetAndIngredient();
        $po->update(['status' => 'approved']);

        Livewire::test(ConvertToDoForm::class, ['id' => $po->id])
            ->assertCount('lines', 2)
            ->set('delivery_date', now()->addDay()->toDateString())
            ->call('convert');

        $grn = GoodsReceivedNote::with('lines')->first();

        $this->assertNotNull($grn);
        $this->assertCount(2, $grn->lines);

        // Exactly one of each, and never both on a line.
        $this->assertSame(1, $grn->lines->whereNotNull('asset_id')->count());
        $this->assertSame(1, $grn->lines->whereNotNull('ingredient_id')->count());

        foreach ($grn->lines as $line) {
            $this->assertTrue(
                ($line->ingredient_id === null) !== ($line->asset_id === null),
                'A line names an ingredient or an asset, never both and never neither.'
            );
        }
    }

    public function test_two_assets_on_one_order_stay_two_lines(): void
    {
        $pr = $this->requestWith([
            ['asset_id' => $this->asset('STAND MIXER', 4250)->id, 'quantity' => 1, 'source' => PurchaseRequestLine::SOURCE_ASSET],
            ['asset_id' => $this->asset('COMBI OVEN', 18000)->id, 'quantity' => 1, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        $this->openFrom($pr)->call('save')->assertHasNoErrors();

        $po = PurchaseOrder::with('lines')->firstOrFail();

        // Both have a null ingredient_id, so anything keying on that alone
        // collapses them into one.
        $this->assertCount(2, $po->lines);
        $this->assertEqualsCanonicalizing(
            ['STAND MIXER', 'COMBI OVEN'],
            $po->lines->map(fn ($l) => $l->displayName())->all()
        );
    }

    /**
     * A split order must not lose the asset it was raised for.
     *
     * PoSplitService drops any line it cannot put under a supplier, and an
     * asset keeps its supplier in asset_suppliers rather than in
     * supplier_ingredients — so the ingredient-only lookup left the asset
     * with no supplier and it disappeared, while its money stayed on the
     * header total.
     */
    public function test_a_split_order_keeps_the_asset_line(): void
    {
        $otherSupplier = Supplier::create([
            'company_id' => $this->company->id, 'name' => 'Dry Goods Sdn Bhd', 'is_active' => true,
        ]);

        $asset = $this->asset();
        $asset->supplierLinks()->create([
            'supplier_id' => $this->supplier->id, 'last_cost' => 4250, 'is_preferred' => true,
        ]);

        $pr = $this->requestWith([
            ['ingredient_id' => $this->ingredient()->id, 'quantity' => 20,
             'preferred_supplier_id' => $otherSupplier->id, 'source' => PurchaseRequestLine::SOURCE_SUPPLIER],
            ['asset_id' => $asset->id, 'quantity' => 2, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        // Two suppliers named and no header supplier: the save splits.
        $component = $this->openFrom($pr)
            ->set('supplier_id', null)
            ->set('lines.0.supplier_id_override', $otherSupplier->id)
            ->set('lines.1.supplier_id_override', $this->supplier->id);

        $component->call('save')->assertHasNoErrors();

        $assetLines = PurchaseOrderLine::whereNotNull('asset_id')->get();

        $this->assertCount(1, $assetLines, 'The asset survived the split.');
        $this->assertSame($asset->id, (int) $assetLines->first()->asset_id);
    }

    public function test_the_scopes_separate_the_two_kinds(): void
    {
        $po = $this->orderWithAssetAndIngredient();

        $this->assertSame(1, PurchaseOrderLine::where('purchase_order_id', $po->id)->ingredientLines()->count());
        $this->assertSame(1, PurchaseOrderLine::where('purchase_order_id', $po->id)->assetLines()->count());
    }
}
