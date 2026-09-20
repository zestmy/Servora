<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\ConvertToDoForm;
use App\Livewire\Purchasing\GrnReceiveForm;
use App\Livewire\Purchasing\OrderForm;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Company;
use App\Models\GoodsReceivedNote;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRecord;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AssetOnHandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase two: an ordered asset is received into the register.
 *
 * The asset now rides the delivery and the GRN alongside the ingredients it
 * was ordered with, and parts company at the moment of receiving: the
 * ingredient becomes a PurchaseRecord line — the inventory receipt — and the
 * asset becomes an AssetMovement receipt, the same document somebody would
 * otherwise key by hand.
 *
 * The load-bearing assertion in this file is the one that says stock never
 * saw it. Everything else is convenience; that one is correctness.
 */
class ReceiveAnOrderedAssetTest extends TestCase
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
            'name' => 'Receive Asset Co', 'slug' => Str::slug('Receive Asset Co') . '-' . uniqid(),
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
            'purchasing.receive', 'purchasing.requests.create',
            'assets.view', 'assets.cost', 'assets.movements.record',
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

    /** Raise a request, convert it to an order, and send it. */
    private function order(array $lines): PurchaseOrder
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

        request()->merge(['pr_id' => $pr->id]);
        Livewire::withQueryParams(['pr_id' => $pr->id])->test(OrderForm::class)
            ->call('save')->assertHasNoErrors();

        $po = PurchaseOrder::with('lines')->firstOrFail();
        $po->update(['status' => 'approved']);

        return $po->refresh();
    }

    /** Convert to a delivery, then receive everything on it. */
    private function receive(PurchaseOrder $po): GoodsReceivedNote
    {
        Livewire::test(ConvertToDoForm::class, ['id' => $po->id])
            ->set('delivery_date', now()->addDay()->toDateString())
            ->call('convert');

        $grn = GoodsReceivedNote::with('lines')->firstOrFail();

        Livewire::test(GrnReceiveForm::class, ['id' => $grn->id])
            ->set('received_date', now()->toDateString())
            ->call('confirm');

        return $grn->refresh();
    }

    private function mixedOrder(): PurchaseOrder
    {
        return $this->order([
            ['ingredient_id' => $this->ingredient()->id, 'quantity' => 20, 'source' => PurchaseRequestLine::SOURCE_SUPPLIER],
            ['asset_id' => $this->asset()->id, 'quantity' => 2, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);
    }

    public function test_the_asset_reaches_the_delivery_and_the_grn(): void
    {
        $po = $this->mixedOrder();

        Livewire::test(ConvertToDoForm::class, ['id' => $po->id])
            ->assertCount('lines', 2)
            ->set('delivery_date', now()->addDay()->toDateString())
            ->call('convert');

        $grn = GoodsReceivedNote::with('lines')->firstOrFail();

        $this->assertCount(2, $grn->lines, 'The asset is no longer left off the delivery.');
        $this->assertNotNull($grn->lines->firstWhere('asset_id', '!=', null));
    }

    public function test_receiving_puts_the_asset_in_the_register(): void
    {
        $asset = $this->asset();

        $po = $this->order([
            ['ingredient_id' => $this->ingredient()->id, 'quantity' => 20, 'source' => PurchaseRequestLine::SOURCE_SUPPLIER],
            ['asset_id' => $asset->id, 'quantity' => 2, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        $grn = $this->receive($po);

        $movement = AssetMovement::with('lines')->where('goods_received_note_id', $grn->id)->first();

        $this->assertNotNull($movement, 'Confirming the delivery records the asset receipt.');
        $this->assertSame(AssetMovement::TYPE_RECEIPT, $movement->movement_type);
        $this->assertSame($this->outlet->id, (int) $movement->outlet_id);
        $this->assertSame($this->supplier->id, (int) $movement->supplier_id);
        $this->assertCount(1, $movement->lines);
        $this->assertSame(2.0, (float) $movement->lines->first()->quantity);

        // The whole point: the register counts it, through the one service
        // that decides what an outlet holds.
        $this->assertSame(2.0, (float) app(AssetOnHandService::class)
            ->quantity($asset->id, $this->outlet->id));
    }

    /** The assertion this phase exists to protect. */
    public function test_the_asset_never_becomes_stock(): void
    {
        $po = $this->mixedOrder();
        $this->receive($po);

        $record = PurchaseRecord::with('lines')->first();

        $this->assertNotNull($record, 'The ingredient still becomes an inventory receipt.');
        $this->assertCount(1, $record->lines, 'Only the ingredient is stock.');
        $this->assertNotNull($record->lines->first()->ingredient_id);

        // Stock money must not include the mixer.
        $this->assertSame(200.0, (float) $record->total_amount,
            '20 flour at 10 — the asset is not a food cost.');
    }

    public function test_a_delivery_of_only_assets_writes_no_inventory_receipt(): void
    {
        $po = $this->order([
            ['asset_id' => $this->asset()->id, 'quantity' => 1, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        $grn = $this->receive($po);

        $this->assertNull(PurchaseRecord::first(),
            'An empty purchase record would read as a purchase of nothing.');
        $this->assertNotNull(AssetMovement::where('goods_received_note_id', $grn->id)->first());
    }

    public function test_two_assets_are_credited_to_their_own_order_lines(): void
    {
        $mixer = $this->asset('STAND MIXER', 4250);
        $oven  = $this->asset('COMBI OVEN', 18000);

        $po = $this->order([
            ['asset_id' => $mixer->id, 'quantity' => 1, 'source' => PurchaseRequestLine::SOURCE_ASSET],
            ['asset_id' => $oven->id,  'quantity' => 1, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        $this->receive($po);

        $po->refresh()->load('lines');

        // Matching on ingredient_id alone credited the first asset line twice
        // and left the second showing as never delivered.
        foreach ($po->lines as $line) {
            $this->assertSame(1.0, (float) $line->received_quantity,
                $line->displayName() . ' should be received exactly once.');
        }

        $this->assertSame('received', $po->status);
    }

    public function test_the_delivery_cost_becomes_the_assets_cost(): void
    {
        $asset = $this->asset('STAND MIXER', 4250);

        $po = $this->order([
            ['asset_id' => $asset->id, 'quantity' => 1, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        // The invoice came in higher than the catalogue said.
        $grn = GoodsReceivedNote::first();
        Livewire::test(ConvertToDoForm::class, ['id' => $po->id])
            ->set('delivery_date', now()->addDay()->toDateString())
            ->call('convert');

        $grn = GoodsReceivedNote::with('lines')->firstOrFail();

        Livewire::test(GrnReceiveForm::class, ['id' => $grn->id])
            ->set('received_date', now()->toDateString())
            ->set('lines.0.unit_cost', '4600')
            ->call('confirm');

        $this->assertSame(4600.0, (float) $asset->refresh()->unit_cost,
            'What it actually cost is what the catalogue should say.');

        $this->assertSame(4600.0, (float) $asset->supplierLinks()
            ->where('supplier_id', $this->supplier->id)->value('last_cost'));
    }

    public function test_receiving_the_same_delivery_again_does_not_double_the_register(): void
    {
        $asset = $this->asset();

        $po = $this->order([
            ['asset_id' => $asset->id, 'quantity' => 2, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        $grn = $this->receive($po);

        // The form refuses a GRN that is no longer pending, but the register
        // is what an outlet is audited against — so the service holds the
        // property too.
        \App\Services\AssetReceiptFromGrnService::record($grn, [
            ['asset_id' => $asset->id, 'quantity' => 2, 'unit_cost' => 4250],
        ]);

        $this->assertSame(1, AssetMovement::where('goods_received_note_id', $grn->id)->count());
        $this->assertSame(2.0, (float) app(AssetOnHandService::class)
            ->quantity($asset->id, $this->outlet->id));
    }
}
