<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\CreditNoteForm;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AssetDisposalFromCreditNoteService;
use App\Services\AssetOnHandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Issuing a credit note takes a returned asset out of the register.
 *
 * The completion of the loop: receiving a delivery puts an asset in, and
 * crediting one back takes it out, without anybody keying the second
 * document by hand.
 *
 * WHAT THIS FILE REALLY GUARDS IS THE BOUNDARY. Only `return` and `damaged`
 * describe an asset leaving an outlet that was actually holding it. A
 * `rejected` or `short_delivery` line never entered the register — a GRN
 * only receives what actually arrived — so disposing on those would subtract
 * a second time and understate what the outlet holds, on the number it is
 * audited against. Every code is pinned here, including the ones that must
 * do nothing.
 */
class CreditNoteDisposesAssetTest extends TestCase
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
            'name' => 'CN Dispose Co', 'slug' => Str::slug('CN Dispose Co') . '-' . uniqid(),
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

    private function asset(string $name = 'STAND MIXER'): Asset
    {
        return Asset::create([
            'company_id' => $this->company->id, 'name' => $name,
            'uom_id' => $this->piece->id, 'unit_cost' => 4250, 'is_active' => true,
        ]);
    }

    /** Put it in the register first, so a change is visible. */
    private function hold(Asset $asset, float $qty = 3): void
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
    }

    private function onHand(Asset $asset): float
    {
        return (float) app(AssetOnHandService::class)->quantity($asset->id, $this->outlet->id);
    }

    /** Issue a note for one asset line with the given reason code. */
    private function issue(Asset $asset, string $reasonCode, float $qty = 1, string $action = 'issue')
    {
        return Livewire::test(CreditNoteForm::class)
            ->set('supplier_id', $this->supplier->id)
            ->set('issued_date', now()->toDateString())
            ->call('addAsset', $asset->id)
            ->set('lines.0.reason_code', $reasonCode)
            ->set('lines.0.quantity', (string) $qty)
            ->call('save', $action)
            ->assertHasNoErrors();
    }

    public function test_a_returned_asset_leaves_the_register(): void
    {
        $mixer = $this->asset();
        $this->hold($mixer, 3);

        $this->issue($mixer, 'return', 1);

        $this->assertSame(2.0, $this->onHand($mixer), 'Three held, one returned.');

        $disposal = AssetMovement::disposals()->with('lines')->firstOrFail();

        $this->assertSame('returned', $disposal->reason);
        $this->assertSame($this->outlet->id, (int) $disposal->outlet_id);
        $this->assertSame(CreditNote::firstOrFail()->id, (int) $disposal->credit_note_id);
        $this->assertSame(1.0, (float) $disposal->lines->first()->quantity);
    }

    public function test_a_damaged_asset_leaves_the_register(): void
    {
        $mixer = $this->asset();
        $this->hold($mixer, 3);

        $this->issue($mixer, 'damaged', 2);

        $this->assertSame(1.0, $this->onHand($mixer));
    }

    /**
     * The boundary. These three never entered the register, or never left.
     *
     * @dataProvider nonDisposingReasons
     */
    public function test_the_other_reasons_leave_the_register_alone(string $reasonCode): void
    {
        $mixer = $this->asset();
        $this->hold($mixer, 3);

        $this->issue($mixer, $reasonCode, 1);

        $this->assertSame(3.0, $this->onHand($mixer),
            "A '{$reasonCode}' line must not move the register.");
        $this->assertSame(0, AssetMovement::disposals()->count());
    }

    public static function nonDisposingReasons(): array
    {
        return [
            'rejected — refused at the door, never received' => ['rejected'],
            'short delivery — never arrived'                 => ['short_delivery'],
            'overcharge — money only'                        => ['overcharge'],
            'other — unknown, guessing would be a write'     => ['other'],
        ];
    }

    /** A draft is thinking, not a decision. */
    public function test_a_draft_does_not_move_the_register(): void
    {
        $mixer = $this->asset();
        $this->hold($mixer, 3);

        $this->issue($mixer, 'return', 1, 'save');

        $this->assertSame(3.0, $this->onHand($mixer));
        $this->assertSame(0, AssetMovement::disposals()->count());
    }

    /** Issuing the same note twice must not subtract twice. */
    public function test_re_syncing_the_same_note_does_not_double_the_disposal(): void
    {
        $mixer = $this->asset();
        $this->hold($mixer, 3);

        $this->issue($mixer, 'return', 1);

        $note = CreditNote::with('lines')->firstOrFail();
        AssetDisposalFromCreditNoteService::sync($note);
        AssetDisposalFromCreditNoteService::sync($note);

        $this->assertSame(1, AssetMovement::disposals()->count());
        $this->assertSame(2.0, $this->onHand($mixer));
    }

    /** A note whose qualifying line is taken away must not leave one standing. */
    public function test_removing_the_qualifying_line_removes_the_disposal(): void
    {
        $mixer = $this->asset();
        $this->hold($mixer, 3);

        $this->issue($mixer, 'return', 1);
        $this->assertSame(2.0, $this->onHand($mixer));

        $note = CreditNote::with('lines')->firstOrFail();
        $note->lines->first()->update(['reason_code' => 'overcharge']);

        AssetDisposalFromCreditNoteService::sync($note->fresh('lines'));

        $this->assertSame(3.0, $this->onHand($mixer), 'The register is given the asset back.');
        $this->assertSame(0, AssetMovement::disposals()->count());
    }

    public function test_an_ingredient_line_never_disposes_of_anything(): void
    {
        $flour = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'FLOUR',
            'base_uom_id' => $this->piece->id, 'recipe_uom_id' => $this->piece->id,
            'is_active' => true, 'purchase_price' => 10,
        ]);

        Livewire::test(CreditNoteForm::class)
            ->set('supplier_id', $this->supplier->id)
            ->set('issued_date', now()->toDateString())
            ->call('addIngredient', $flour->id)
            ->set('lines.0.reason_code', 'return')
            ->set('lines.0.quantity', '5')
            ->call('save', 'issue')
            ->assertHasNoErrors();

        $this->assertSame(0, AssetMovement::count(),
            'A returned ingredient is stock, not an asset — it has no business in the register.');
    }

    public function test_only_the_qualifying_lines_of_a_mixed_note_are_disposed(): void
    {
        $mixer = $this->asset('STAND MIXER');
        $oven  = $this->asset('COMBI OVEN');
        $this->hold($mixer, 3);
        $this->hold($oven, 3);

        Livewire::test(CreditNoteForm::class)
            ->set('supplier_id', $this->supplier->id)
            ->set('issued_date', now()->toDateString())
            ->call('addAsset', $mixer->id)
            ->set('lines.0.reason_code', 'return')
            ->set('lines.0.quantity', '1')
            ->call('addAsset', $oven->id)
            ->set('lines.1.reason_code', 'rejected')
            ->set('lines.1.quantity', '1')
            ->call('save', 'issue')
            ->assertHasNoErrors();

        $this->assertSame(2.0, $this->onHand($mixer), 'Returned — gone from the register.');
        $this->assertSame(3.0, $this->onHand($oven), 'Rejected — it never arrived, so nothing to remove.');

        $this->assertCount(1, AssetMovement::disposals()->firstOrFail()->lines);
    }
}
