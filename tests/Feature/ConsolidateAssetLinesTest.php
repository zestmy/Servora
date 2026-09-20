<?php

namespace Tests\Feature;

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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * CPU consolidation carries asset lines.
 *
 * It was the last place where the same request behaved differently depending
 * on the route it took: a direct PR → PO conversion carried the asset, and
 * consolidating the very same request dropped it. Whether a mixer gets
 * ordered should not depend on which button somebody pressed.
 *
 * There are three paths through the service and all three are exercised here,
 * because they each merge lines separately: consolidate(), the costed preview
 * the screen renders, and consolidateFromCustomized() which runs when that
 * preview is confirmed.
 */
class ConsolidateAssetLinesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private UnitOfMeasure $piece;
    private User $user;
    private Supplier $supplier;
    private CentralPurchasingUnit $cpu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Consolidate Asset Co', 'slug' => Str::slug('Consolidate Asset Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pc', 'type' => 'count']);

        $this->supplier = Supplier::create([
            'company_id' => $this->company->id, 'name' => 'Kitchen Kit Sdn Bhd', 'is_active' => true,
        ]);

        $this->cpu = CentralPurchasingUnit::create([
            'company_id' => $this->company->id, 'name' => 'Central Purchasing', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(collect([
            'purchasing.view', 'purchasing.consolidate', 'assets.view',
        ])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->user);
    }

    private function asset(string $name, float $cost = 4250, ?float $supplierCost = null): Asset
    {
        $asset = Asset::create([
            'company_id' => $this->company->id, 'name' => $name,
            'uom_id' => $this->piece->id, 'unit_cost' => $cost, 'is_active' => true,
        ]);

        if ($supplierCost !== null) {
            $asset->supplierLinks()->create([
                'supplier_id' => $this->supplier->id, 'last_cost' => $supplierCost, 'is_preferred' => true,
            ]);
        }

        return $asset;
    }

    private function ingredient(string $name = 'FLOUR'): Ingredient
    {
        return Ingredient::create([
            'company_id' => $this->company->id, 'name' => $name,
            'base_uom_id' => $this->piece->id, 'recipe_uom_id' => $this->piece->id,
            'is_active' => true, 'purchase_price' => 10,
        ]);
    }

    private function request(array $lines): PurchaseRequest
    {
        $pr = PurchaseRequest::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'pr_number' => 'PR-' . uniqid(), 'requested_date' => now()->toDateString(),
            'status' => PurchaseRequest::STATUS_APPROVED, 'created_by' => $this->user->id,
        ]);

        foreach ($lines as $line) {
            $pr->lines()->create(array_merge([
                'quantity' => 1, 'uom_id' => $this->piece->id,
                'preferred_supplier_id' => $this->supplier->id,
            ], $line));
        }

        return $pr;
    }

    public function test_consolidation_puts_the_asset_on_the_order(): void
    {
        $asset = $this->asset('STAND MIXER', 4250, 4100);

        $pr = $this->request([
            ['ingredient_id' => $this->ingredient()->id, 'quantity' => 20, 'source' => PurchaseRequestLine::SOURCE_SUPPLIER],
            ['asset_id' => $asset->id, 'quantity' => 2, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        PurchaseRequestService::consolidate([$pr->id], $this->cpu->id);

        $po = PurchaseOrder::with('lines')->firstOrFail();

        $this->assertCount(2, $po->lines, 'The asset is no longer dropped by consolidation.');

        $assetLine = $po->lines->firstWhere('asset_id', '!=', null);

        $this->assertNotNull($assetLine);
        $this->assertSame(2.0, (float) $assetLine->quantity);
        $this->assertSame(4100.0, (float) $assetLine->unit_cost,
            "The supplier's own price for the asset, not the catalogue figure.");
    }

    /** No asset_suppliers row: fall back to what the catalogue says. */
    public function test_an_asset_with_no_supplier_price_uses_its_catalogue_cost(): void
    {
        $pr = $this->request([
            ['asset_id' => $this->asset('COMBI OVEN', 18000)->id, 'quantity' => 1, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        PurchaseRequestService::consolidate([$pr->id], $this->cpu->id);

        $line = PurchaseOrder::with('lines')->firstOrFail()->lines->first();

        $this->assertSame(18000.0, (float) $line->unit_cost);
    }

    /**
     * The collision this whole change had to avoid.
     *
     * Every asset line carries a null ingredient_id, so grouping on that
     * alone merged a mixer and an oven into one order line — the quantities
     * added together and one of the two stopped existing.
     */
    public function test_two_different_assets_do_not_merge_into_one_line(): void
    {
        $pr = $this->request([
            ['asset_id' => $this->asset('STAND MIXER', 4250)->id, 'quantity' => 1, 'source' => PurchaseRequestLine::SOURCE_ASSET],
            ['asset_id' => $this->asset('COMBI OVEN', 18000)->id, 'quantity' => 1, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        PurchaseRequestService::consolidate([$pr->id], $this->cpu->id);

        $po = PurchaseOrder::with('lines.asset')->firstOrFail();

        $this->assertCount(2, $po->lines);
        $this->assertEqualsCanonicalizing(
            ['STAND MIXER', 'COMBI OVEN'],
            $po->lines->map(fn ($l) => $l->displayName())->all()
        );
    }

    /** The same asset asked for twice IS one line, with the quantities added. */
    public function test_the_same_asset_across_two_requests_merges(): void
    {
        $asset = $this->asset('STAND MIXER', 4250);

        $a = $this->request([['asset_id' => $asset->id, 'quantity' => 1, 'source' => PurchaseRequestLine::SOURCE_ASSET]]);
        $b = $this->request([['asset_id' => $asset->id, 'quantity' => 2, 'source' => PurchaseRequestLine::SOURCE_ASSET]]);

        PurchaseRequestService::consolidate([$a->id, $b->id], $this->cpu->id);

        $po = PurchaseOrder::with('lines')->firstOrFail();

        $this->assertCount(1, $po->lines);
        $this->assertSame(3.0, (float) $po->lines->first()->quantity);
    }

    public function test_the_preview_shows_the_asset_and_prices_it(): void
    {
        $asset = $this->asset('STAND MIXER', 4250, 4100);

        $pr = $this->request([
            ['ingredient_id' => $this->ingredient()->id, 'quantity' => 20, 'source' => PurchaseRequestLine::SOURCE_SUPPLIER],
            ['asset_id' => $asset->id, 'quantity' => 2, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        $data = PurchaseRequestService::consolidationPreviewWithCosts([$pr->id]);

        $lines = collect($data['groups'])->flatMap(fn ($g) => $g['lines']);
        $assetRow = $lines->firstWhere('asset_id', $asset->id);

        $this->assertNotNull($assetRow, 'The asset appears on the preview it used to be counted out of.');
        $this->assertSame('STAND MIXER', $assetRow['ingredient_name']);
        $this->assertSame(4100.0, (float) $assetRow['unit_cost']);
        $this->assertSame('asset', $assetRow['source']);
        $this->assertSame(1, $data['asset_line_count'], 'Still counted, so the screen can explain what happens next.');
    }

    public function test_two_assets_stay_two_rows_on_the_preview(): void
    {
        $pr = $this->request([
            ['asset_id' => $this->asset('STAND MIXER', 4250)->id, 'quantity' => 1, 'source' => PurchaseRequestLine::SOURCE_ASSET],
            ['asset_id' => $this->asset('COMBI OVEN', 18000)->id, 'quantity' => 1, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        $data = PurchaseRequestService::consolidationPreviewWithCosts([$pr->id]);

        $lines = collect($data['groups'])->flatMap(fn ($g) => $g['lines']);

        $this->assertCount(2, $lines);
        $this->assertEqualsCanonicalizing(
            ['STAND MIXER', 'COMBI OVEN'],
            $lines->pluck('ingredient_name')->all()
        );
    }

    /** Confirming the edited preview is a separate path with its own merge. */
    public function test_confirming_the_preview_creates_the_asset_line(): void
    {
        $asset = $this->asset('STAND MIXER', 4250, 4100);

        $pr = $this->request([
            ['ingredient_id' => $this->ingredient()->id, 'quantity' => 20, 'source' => PurchaseRequestLine::SOURCE_SUPPLIER],
            ['asset_id' => $asset->id, 'quantity' => 2, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        $data = PurchaseRequestService::consolidationPreviewWithCosts([$pr->id]);

        PurchaseRequestService::consolidateFromCustomized($data['groups'], $this->cpu->id, [$pr->id]);

        $po = PurchaseOrder::with('lines')->firstOrFail();

        $this->assertCount(2, $po->lines);

        $assetLine = $po->lines->firstWhere('asset_id', '!=', null);

        $this->assertNotNull($assetLine, 'The confirmed preview must write the asset line too.');
        $this->assertSame(2.0, (float) $assetLine->quantity);
        $this->assertSame(4100.0, (float) $assetLine->unit_cost);
    }

    public function test_confirming_the_preview_keeps_two_assets_apart(): void
    {
        $pr = $this->request([
            ['asset_id' => $this->asset('STAND MIXER', 4250)->id, 'quantity' => 1, 'source' => PurchaseRequestLine::SOURCE_ASSET],
            ['asset_id' => $this->asset('COMBI OVEN', 18000)->id, 'quantity' => 1, 'source' => PurchaseRequestLine::SOURCE_ASSET],
        ]);

        $data = PurchaseRequestService::consolidationPreviewWithCosts([$pr->id]);

        PurchaseRequestService::consolidateFromCustomized($data['groups'], $this->cpu->id, [$pr->id]);

        $po = PurchaseOrder::with('lines.asset')->firstOrFail();

        $this->assertCount(2, $po->lines);
        $this->assertEqualsCanonicalizing(
            ['STAND MIXER', 'COMBI OVEN'],
            $po->lines->map(fn ($l) => $l->displayName())->all()
        );
    }

    /** A hand-typed name still has nothing behind it to order. */
    public function test_a_hand_typed_line_is_still_left_out(): void
    {
        $pr = $this->request([
            ['ingredient_id' => $this->ingredient()->id, 'quantity' => 5, 'source' => PurchaseRequestLine::SOURCE_SUPPLIER],
            ['custom_name' => 'BLUE ROPE, 10M', 'quantity' => 1, 'source' => PurchaseRequestLine::SOURCE_SUPPLIER],
        ]);

        PurchaseRequestService::consolidate([$pr->id], $this->cpu->id);

        $po = PurchaseOrder::with('lines')->firstOrFail();

        $this->assertCount(1, $po->lines);
        $this->assertNotNull($po->lines->first()->ingredient_id);
    }
}
