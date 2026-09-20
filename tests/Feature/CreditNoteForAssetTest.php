<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\CreditNoteForm;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\GoodsReceivedNote;
use App\Models\Ingredient;
use App\Models\Outlet;
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
 * Crediting an asset back to a supplier.
 *
 * The last document in the chain that could not name one. An asset could be
 * requested, ordered, delivered and received; what could not be done was
 * credit it back when the supplier sent the wrong mixer or billed for two
 * and delivered one.
 *
 * THE ASSERTION THAT MATTERS HERE IS THE ONE ABOUT THE REGISTER. A credit
 * note is financial — issuing one has never moved ingredient stock — so an
 * asset line must not move the register either. Beyond consistency, it is
 * what stops a double-count: a `rejected` or `short_delivery` line describes
 * something that never entered the register in the first place, because the
 * GRN only ever receives what actually arrived.
 */
class CreditNoteForAssetTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private UnitOfMeasure $piece;
    private Supplier $supplier;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'CN Asset Co', 'slug' => Str::slug('CN Asset Co') . '-' . uniqid(),
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
            'purchasing.view', 'purchasing.credit_notes.create', 'purchasing.credit_notes.edit',
            'assets.view', 'assets.movements.record',
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

    private function ingredient(): Ingredient
    {
        return Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'FLOUR',
            'base_uom_id' => $this->piece->id, 'recipe_uom_id' => $this->piece->id,
            'is_active' => true, 'purchase_price' => 10,
        ]);
    }

    /** Put the asset in the register first, so a change would show. */
    private function receiveIntoRegister(Asset $asset, float $qty = 2): AssetMovement
    {
        $movement = AssetMovement::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'movement_type' => AssetMovement::TYPE_RECEIPT, 'movement_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id, 'total_cost' => 4250 * $qty,
            'created_by' => $this->user->id,
        ]);

        $movement->lines()->create([
            'asset_id' => $asset->id, 'quantity' => $qty,
            'unit_cost' => 4250, 'total_cost' => 4250 * $qty,
        ]);

        return $movement;
    }

    private function form()
    {
        return Livewire::test(CreditNoteForm::class)
            ->set('supplier_id', $this->supplier->id)
            ->set('issued_date', now()->toDateString());
    }

    public function test_an_asset_can_be_credited_back(): void
    {
        $mixer = $this->asset();

        $this->form()
            ->call('addAsset', $mixer->id)
            ->assertSet('lines.0.asset_id', $mixer->id)
            ->assertSet('lines.0.reason_code', 'return')
            ->set('lines.0.quantity', '1')
            ->call('save', 'issue')
            ->assertHasNoErrors();

        $line = CreditNote::with('lines')->firstOrFail()->lines->first();

        $this->assertSame($mixer->id, (int) $line->asset_id);
        $this->assertNull($line->ingredient_id, 'An asset line carries no ingredient.');
        $this->assertSame('STAND MIXER', $line->displayName());
        $this->assertTrue($line->isAssetItem());
        $this->assertSame(4250.0, (float) $line->unit_price, 'Priced from the asset itself.');
    }

    /** The property this whole design rests on. */
    public function test_crediting_an_asset_does_not_move_the_register(): void
    {
        $mixer = $this->asset();
        $this->receiveIntoRegister($mixer, 2);

        $before = app(AssetOnHandService::class)->quantity($mixer->id, $this->outlet->id);
        $this->assertSame(2.0, (float) $before);

        $this->form()
            ->call('addAsset', $mixer->id)
            ->set('lines.0.quantity', '1')
            ->call('save', 'issue')
            ->assertHasNoErrors();

        $this->assertSame(2.0, (float) app(AssetOnHandService::class)->quantity($mixer->id, $this->outlet->id),
            'A credit note settles money. Taking the asset out of the register is a disposal, recorded separately.');

        $this->assertSame(1, AssetMovement::count(), 'No movement was invented by issuing the note.');
    }

    public function test_the_same_asset_is_not_added_twice(): void
    {
        $mixer = $this->asset();

        $this->form()
            ->call('addAsset', $mixer->id)
            ->call('addAsset', $mixer->id)
            ->assertCount('lines', 1);
    }

    public function test_two_assets_stay_two_lines(): void
    {
        $this->form()
            ->call('addAsset', $this->asset('STAND MIXER', 4250)->id)
            ->call('addAsset', $this->asset('COMBI OVEN', 18000)->id)
            ->set('lines.0.quantity', '1')
            ->set('lines.1.quantity', '1')
            ->call('save', 'issue')
            ->assertHasNoErrors();

        $lines = CreditNote::with('lines.asset')->firstOrFail()->lines;

        $this->assertCount(2, $lines);
        $this->assertEqualsCanonicalizing(
            ['STAND MIXER', 'COMBI OVEN'],
            $lines->map(fn ($l) => $l->displayName())->all()
        );
    }

    public function test_an_ingredient_and_an_asset_sit_on_the_same_note(): void
    {
        $this->form()
            ->call('addIngredient', $this->ingredient()->id)
            ->call('addAsset', $this->asset()->id)
            ->set('lines.0.quantity', '5')
            ->set('lines.1.quantity', '1')
            ->call('save', 'issue')
            ->assertHasNoErrors();

        $lines = CreditNote::with('lines')->firstOrFail()->lines;

        $this->assertCount(2, $lines);
        $this->assertSame(1, $lines->whereNotNull('asset_id')->count());
        $this->assertSame(1, $lines->whereNotNull('ingredient_id')->count());
    }

    public function test_a_line_naming_neither_is_refused(): void
    {
        $this->form()
            ->call('addIngredient', $this->ingredient()->id)
            ->set('lines.0.quantity', '1')
            ->set('lines.0.ingredient_id', null)
            ->call('save')
            ->assertHasErrors('lines.0.ingredient_id');
    }

    /**
     * Found while adding asset lines, not looked for.
     *
     * The form validates `reason` as nullable and save() writes null when it
     * is blank, but the column was created NOT NULL with no default — and
     * production runs STRICT_TRANS_TABLES, which makes that a hard error
     * rather than a coerced empty string. Issuing a note without typing a
     * reason was a 500. The same shape as the `source` enum that could not
     * hold 'asset': code and column disagreeing, the column winning at the
     * worst moment.
     */
    public function test_a_note_can_be_issued_without_a_reason(): void
    {
        $this->form()
            ->set('reason', '')
            ->call('addAsset', $this->asset()->id)
            ->set('lines.0.quantity', '1')
            ->call('save', 'issue')
            ->assertHasNoErrors();

        $this->assertNull(CreditNote::firstOrFail()->reason);
    }

    /** A delivery can hold assets since phase two, so its variance can too. */
    public function test_a_damaged_asset_on_a_delivery_prefills_the_note(): void
    {
        $mixer = $this->asset();

        $grn = GoodsReceivedNote::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'supplier_id' => $this->supplier->id, 'grn_number' => 'GRN-' . uniqid(),
            'status' => 'received', 'received_date' => now()->toDateString(),
            'total_amount' => 4250, 'created_by' => $this->user->id,
        ]);

        $grn->lines()->create([
            'asset_id' => $mixer->id, 'expected_quantity' => 1, 'received_quantity' => 1,
            'uom_id' => $this->piece->id, 'unit_cost' => 4250, 'total_cost' => 4250,
            'condition' => 'damaged',
        ]);

        Livewire::test(CreditNoteForm::class)
            ->set('supplier_id', $this->supplier->id)
            ->set('goods_received_note_id', $grn->id)
            ->assertSet('lines.0.asset_id', $mixer->id)
            ->assertSet('lines.0.reason_code', 'damaged')
            ->assertSet('lines.0.ingredient_name', 'STAND MIXER');
    }
}
