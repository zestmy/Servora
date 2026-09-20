<?php

namespace Tests\Feature;

use App\Livewire\Assets\CountForm;
use App\Livewire\Assets\Index as AssetsIndex;
use App\Livewire\Assets\MovementForm;
use App\Livewire\Assets\Records;
use App\Livewire\Assets\Register;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetCount;
use App\Models\AssetMovement;
use App\Models\Company;
use App\Models\Outlet;
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
 * The Asset module end to end: catalogue, count, receipt, disposal, register.
 *
 * The rules being pinned here are the ones that are decisions rather than
 * plumbing — who may set a price, what a blank line on a count sheet means,
 * where a disposal gets its cost from, and the fact that the register and the
 * count sheet always agree because they read the same service.
 */
class AssetModuleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private UnitOfMeasure $piece;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Asset Screens Co', 'slug' => Str::slug('Asset Screens Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pc', 'type' => 'count']);
    }

    /** @param array<int, string> $permissions */
    private function userWith(array $permissions): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(collect($permissions)->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function asset(string $name, float $cost = 10, ?AssetCategory $category = null): Asset
    {
        return Asset::create([
            'company_id'        => $this->company->id,
            'name'              => $name,
            'uom_id'            => $this->piece->id,
            'unit_cost'         => $cost,
            'asset_category_id' => $category?->id,
            'is_active'         => true,
        ]);
    }

    // ── The catalogue ─────────────────────────────────────────────────────

    public function test_an_asset_can_be_added_with_a_preferred_supplier(): void
    {
        $this->actingAs($this->userWith(['assets.view', 'assets.manage', 'assets.cost']));

        $supplier = Supplier::create([
            'company_id' => $this->company->id, 'name' => 'Kitchen Kit Sdn Bhd', 'is_active' => true,
        ]);

        Livewire::test(AssetsIndex::class)
            ->call('openCreate')
            ->set('name', 'stand mixer')
            ->set('code', 'EQ-001')
            ->set('uom_id', $this->piece->id)
            ->set('unit_cost', '4250.00')
            ->set('brand', 'Hobart')
            ->call('addSupplierRow')
            ->set('supplierLinks.0.supplier_id', $supplier->id)
            ->set('supplierLinks.0.last_cost', '4100')
            ->call('save')
            ->assertHasNoErrors();

        $asset = Asset::where('code', 'EQ-001')->firstOrFail();

        $this->assertSame('STAND MIXER', $asset->name, 'Names upper-case on save, as ingredients do.');
        $this->assertSame(4250.0, (float) $asset->unit_cost);
        $this->assertSame($supplier->id, $asset->preferredSupplier()?->id);
        $this->assertSame(4100.0, (float) $asset->supplierLinks()->first()->last_cost);
    }

    /**
     * `assets.cost` is split out of `assets.manage` for the same reason
     * `ingredients.cost` is: renaming a thing is housekeeping, repricing it
     * moves the value of everything the register reports.
     */
    public function test_editing_without_the_cost_ability_leaves_the_price_alone(): void
    {
        $this->actingAs($this->userWith(['assets.view', 'assets.manage']));

        $asset = $this->asset('DINNER PLATE', 12.50);

        Livewire::test(AssetsIndex::class)
            ->call('openEdit', $asset->id)
            ->set('name', 'DINNER PLATE 28CM')
            ->set('unit_cost', '99.99')
            ->call('save')
            ->assertHasNoErrors();

        $asset->refresh();

        $this->assertSame('DINNER PLATE 28CM', $asset->name, 'The edit they were allowed still saves.');
        $this->assertSame(12.5, (float) $asset->unit_cost, 'The price they were not allowed to set is left as it stands.');
    }

    public function test_a_category_still_holding_assets_is_not_deleted(): void
    {
        $this->actingAs($this->userWith(['assets.view', 'assets.manage', 'assets.delete']));

        $category = AssetCategory::create([
            'company_id' => $this->company->id, 'name' => 'Equipment', 'color' => '#14b8a6',
        ]);
        $this->asset('COMBI OVEN', 30000, $category);

        Livewire::test(AssetsIndex::class)
            ->call('deleteCategory', $category->id)
            ->assertSee('still has 1 asset');

        $this->assertNotNull(AssetCategory::find($category->id));
    }

    // ── Counting ──────────────────────────────────────────────────────────

    public function test_a_completed_count_stores_its_value_and_drops_the_lines_nobody_counted(): void
    {
        $this->actingAs($this->userWith(['assets.view', 'assets.counts.record']));

        $plate = $this->asset('DINNER PLATE', 12.50);
        $knife = $this->asset('CHEF KNIFE', 80);

        // loadAll() orders by name, so line 0 is CHEF KNIFE and line 1 is
        // DINNER PLATE. The plate is counted; the knife is left blank.
        Livewire::test(CountForm::class)
            ->call('loadAll')
            ->assertCount('lines', 2)
            ->set('count_date', '2026-04-01')
            ->set('lines.1.counted_quantity', '96')
            ->call('save', 'complete')
            ->assertHasNoErrors();

        $count = AssetCount::firstOrFail();

        $this->assertSame(AssetCount::STATUS_COMPLETED, $count->status);
        $this->assertSame(1, $count->lines()->count(), 'A blank line was never counted, so it is not filed as a zero.');
        $this->assertSame(1200.0, (float) $count->total_asset_value, '96 plates at 12.50.');
        $this->assertSame($plate->id, (int) $count->lines()->first()->asset_id);

        $onHand = app(\App\Services\AssetOnHandService::class);

        $this->assertSame(96.0, $onHand->quantity($plate->id, $this->outlet->id));
        $this->assertSame(0.0, $onHand->quantity($knife->id, $this->outlet->id),
            'The knife was never counted and never received, so it has nothing on hand — not a counted zero.');
    }

    public function test_a_typed_zero_is_counted_and_shows_as_a_variance(): void
    {
        $this->actingAs($this->userWith(['assets.view', 'assets.counts.record', 'assets.movements.record']));

        $plate = $this->asset('DINNER PLATE', 12.50);

        $receipt = AssetMovement::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'movement_type' => AssetMovement::TYPE_RECEIPT, 'movement_date' => '2026-01-01', 'total_cost' => 0,
        ]);
        $receipt->lines()->create(['asset_id' => $plate->id, 'quantity' => 10, 'unit_cost' => 12.50, 'total_cost' => 125]);

        Livewire::test(CountForm::class)
            ->call('addAsset', $plate->id)
            ->set('count_date', '2026-04-01')
            ->set('lines.0.counted_quantity', '0')
            ->call('save', 'complete')
            ->assertHasNoErrors();

        $line = AssetCount::firstOrFail()->lines()->firstOrFail();

        $this->assertSame(10.0, (float) $line->system_quantity);
        $this->assertSame(0.0, (float) $line->counted_quantity);
        $this->assertSame(-10.0, (float) $line->variance_quantity);
        $this->assertSame(-125.0, (float) $line->variance_cost, 'Ten plates missing at 12.50 each.');
    }

    /**
     * The unit cost input is readonly on the sheet, but `lines` is a public
     * Livewire property, so the browser can still put any number on the wire.
     * The cost written must come from the server-authored #[Locked] map.
     */
    public function test_a_cost_sent_from_the_browser_is_ignored(): void
    {
        $this->actingAs($this->userWith(['assets.view', 'assets.counts.record']));

        $plate = $this->asset('DINNER PLATE', 12.50);

        Livewire::test(CountForm::class)
            ->call('addAsset', $plate->id)
            ->set('count_date', '2026-04-01')
            ->set('lines.0.counted_quantity', '10')
            ->set('lines.0.unit_cost', '9999')
            ->call('save', 'complete')
            ->assertHasNoErrors();

        $line = AssetCount::firstOrFail()->lines()->firstOrFail();

        $this->assertSame(12.5, (float) $line->unit_cost);
        $this->assertSame(125.0, (float) $line->line_value);
    }

    public function test_reopening_a_count_needs_its_own_ability(): void
    {
        $plate = $this->asset('DINNER PLATE', 12.50);

        $count = AssetCount::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'status' => AssetCount::STATUS_COMPLETED, 'count_date' => '2026-04-01',
            'total_asset_value' => 1200,
        ]);
        $count->lines()->create([
            'asset_id' => $plate->id, 'counted_quantity' => 96, 'unit_cost' => 12.50, 'line_value' => 1200,
        ]);

        // Recording a count is not the same as unlocking a filed one.
        $this->actingAs($this->userWith(['assets.view', 'assets.counts.record']));

        Livewire::test(CountForm::class, ['id' => $count->id])
            ->call('reopen')
            ->assertForbidden();

        $this->actingAs($this->userWith(['assets.view', 'assets.counts.record', 'assets.counts.reopen']));

        Livewire::test(CountForm::class, ['id' => $count->id])->call('reopen');

        $this->assertSame(AssetCount::STATUS_DRAFT, $count->fresh()->status);
    }

    // ── Receipts and disposals ────────────────────────────────────────────

    public function test_a_receipt_books_the_assets_in_and_writes_the_price_back(): void
    {
        $this->actingAs($this->userWith([
            'assets.view', 'assets.movements.record', 'assets.cost',
        ]));

        $supplier = Supplier::create([
            'company_id' => $this->company->id, 'name' => 'Kitchen Kit Sdn Bhd', 'is_active' => true,
        ]);
        $mixer = $this->asset('STAND MIXER', 4000);

        Livewire::test(MovementForm::class)
            ->set('movement_date', '2026-04-10')
            ->set('supplier_id', $supplier->id)
            ->call('addAsset', $mixer->id)
            ->set('lines.0.quantity', '2')
            ->set('lines.0.unit_cost', '4250')
            ->call('save')
            ->assertHasNoErrors();

        $movement = AssetMovement::firstOrFail();

        $this->assertSame(AssetMovement::TYPE_RECEIPT, $movement->movement_type);
        $this->assertSame(8500.0, (float) $movement->total_cost);
        $this->assertSame(2.0, app(\App\Services\AssetOnHandService::class)->quantity($mixer->id, $this->outlet->id));

        $this->assertSame(4250.0, (float) $mixer->fresh()->unit_cost, 'What was paid becomes what it costs.');
        $this->assertSame(4250.0, (float) $mixer->supplierLinks()->where('supplier_id', $supplier->id)->value('last_cost'));
    }

    public function test_a_receipt_without_the_cost_ability_still_files_but_leaves_the_catalogue_alone(): void
    {
        $this->actingAs($this->userWith(['assets.view', 'assets.movements.record']));

        $mixer = $this->asset('STAND MIXER', 4000);

        Livewire::test(MovementForm::class)
            ->set('movement_date', '2026-04-10')
            ->call('addAsset', $mixer->id)
            ->set('lines.0.quantity', '1')
            ->set('lines.0.unit_cost', '4250')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(4250.0, (float) AssetMovement::firstOrFail()->lines()->first()->unit_cost,
            'The receipt files at the price on the invoice.');
        $this->assertSame(4000.0, (float) $mixer->fresh()->unit_cost,
            'The catalogue price needs assets.cost and is left alone without it.');
    }

    /**
     * A disposal spends what the register already carries the asset at. A cost
     * arriving on the wire would move asset value with no document behind it.
     */
    public function test_a_disposal_is_priced_from_the_catalogue_not_from_the_wire(): void
    {
        $this->actingAs($this->userWith(['assets.view', 'assets.movements.record']));

        $plate = $this->asset('DINNER PLATE', 12.50);

        Livewire::test(MovementForm::class, ['type' => 'disposal'])
            ->set('movement_date', '2026-04-11')
            ->set('reason', 'broken')
            ->call('addAsset', $plate->id)
            ->set('lines.0.quantity', '4')
            ->set('lines.0.unit_cost', '9999')
            ->call('save')
            ->assertHasNoErrors();

        $movement = AssetMovement::firstOrFail();

        $this->assertSame(AssetMovement::TYPE_DISPOSAL, $movement->movement_type);
        $this->assertSame(12.5, (float) $movement->lines()->first()->unit_cost);
        $this->assertSame(50.0, (float) $movement->total_cost);
        $this->assertSame(-4.0, app(\App\Services\AssetOnHandService::class)->quantity($plate->id, $this->outlet->id));
    }

    public function test_a_disposal_must_say_why(): void
    {
        $this->actingAs($this->userWith(['assets.view', 'assets.movements.record']));

        $plate = $this->asset('DINNER PLATE', 12.50);

        Livewire::test(MovementForm::class, ['type' => 'disposal'])
            ->call('addAsset', $plate->id)
            ->set('lines.0.quantity', '4')
            ->call('save')
            ->assertHasErrors(['reason']);
    }

    // ── The register ──────────────────────────────────────────────────────

    public function test_the_register_values_what_is_held_and_agrees_with_the_count(): void
    {
        $this->actingAs($this->userWith([
            'assets.view', 'assets.counts.record', 'assets.movements.record',
        ]));

        $plate = $this->asset('DINNER PLATE', 12.50);
        $knife = $this->asset('CHEF KNIFE', 80);

        $receipt = AssetMovement::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'movement_type' => AssetMovement::TYPE_RECEIPT, 'movement_date' => '2026-01-01', 'total_cost' => 0,
        ]);
        $receipt->lines()->create(['asset_id' => $plate->id, 'quantity' => 100, 'unit_cost' => 12.50, 'total_cost' => 1250]);
        $receipt->lines()->create(['asset_id' => $knife->id, 'quantity' => 5, 'unit_cost' => 80, 'total_cost' => 400]);

        Livewire::test(Register::class)
            ->assertViewHas('totalValue', 1650.0)   // 100 × 12.50 + 5 × 80
            ->assertViewHas('lineCount', 2)
            ->assertSee('DINNER PLATE');
    }

    public function test_the_register_hides_assets_with_nothing_here_until_asked(): void
    {
        $this->actingAs($this->userWith(['assets.view']));

        $this->asset('NEVER RECEIVED', 99);

        Livewire::test(Register::class)
            ->assertViewHas('lineCount', 0)
            ->assertViewHas('neverSeen', 1)
            ->set('includeZero', true)
            ->assertViewHas('lineCount', 1)
            ->assertSee('NEVER RECEIVED');
    }

    // ── The records list ──────────────────────────────────────────────────

    public function test_each_tab_lists_only_its_own_document_type(): void
    {
        $this->actingAs($this->userWith(['assets.view']));

        $plate = $this->asset('DINNER PLATE', 12.50);

        AssetCount::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'status' => AssetCount::STATUS_COMPLETED, 'count_date' => now()->toDateString(),
            'reference_number' => 'COUNT-1', 'total_asset_value' => 1250,
        ]);

        AssetMovement::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'movement_type' => AssetMovement::TYPE_RECEIPT, 'movement_date' => now()->toDateString(),
            'reference_number' => 'INV-77', 'total_cost' => 500,
        ]);

        AssetMovement::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'movement_type' => AssetMovement::TYPE_DISPOSAL, 'movement_date' => now()->toDateString(),
            'reference_number' => 'WO-3', 'reason' => 'broken', 'total_cost' => 25,
        ]);

        Livewire::test(Records::class)
            ->assertSee('COUNT-1')->assertDontSee('INV-77')
            ->assertViewHas('rangeTotal', 1250.0)
            ->set('tab', 'receipts')
            ->assertSee('INV-77')->assertDontSee('WO-3')
            ->assertViewHas('rangeTotal', 500.0)
            ->set('tab', 'disposals')
            ->assertSee('WO-3')->assertDontSee('INV-77')
            ->assertViewHas('rangeTotal', 25.0);
    }

    public function test_the_asset_screens_are_closed_without_the_view_ability(): void
    {
        $this->actingAs($this->userWith(['ingredients.view']));

        $this->get(route('assets.index'))->assertForbidden();
        $this->get(route('assets.register'))->assertForbidden();
        $this->get(route('assets.records'))->assertForbidden();
    }
}
