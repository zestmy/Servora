<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\PurchaseRequestForm;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\RequestPriceEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The estimated price on a purchase request.
 *
 * A request carries no price of its own — cost is settled on the purchase
 * order — so this is derived, read-only, and never written back. What the
 * test is really holding is that the form and the printed sheet derive it
 * the same way, from the same service, so the two cannot disagree.
 */
class PurchaseRequestPriceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private UnitOfMeasure $piece;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'PR Price Co', 'slug' => Str::slug('PR Price Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pc', 'type' => 'count']);

        $this->supplier = Supplier::create([
            'company_id' => $this->company->id, 'name' => 'Kitchen Kit Sdn Bhd', 'is_active' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(collect([
            'purchasing.view', 'purchasing.requests.create', 'purchasing.requests.edit', 'assets.view',
        ])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user);
    }

    private function ingredient(float $purchasePrice = 12): Ingredient
    {
        return Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'FLOUR',
            'base_uom_id' => $this->piece->id, 'recipe_uom_id' => $this->piece->id,
            'is_active' => true, 'purchase_price' => $purchasePrice,
        ]);
    }

    private function asset(float $unitCost = 4250): Asset
    {
        return Asset::create([
            'company_id' => $this->company->id, 'name' => 'STAND MIXER',
            'uom_id' => $this->piece->id, 'unit_cost' => $unitCost, 'is_active' => true,
        ]);
    }

    public function test_the_supplier_price_wins_over_the_catalogue(): void
    {
        $ingredient = $this->ingredient(12);

        $ingredient->suppliers()->attach($this->supplier->id, ['last_cost' => 9.5, 'is_preferred' => true, 'uom_id' => $this->piece->id]);

        $this->assertSame(9.5, RequestPriceEstimator::estimate($ingredient->id, null, $this->supplier->id));
    }

    public function test_without_a_supplier_it_falls_back_to_the_item(): void
    {
        $ingredient = $this->ingredient(12);

        $this->assertSame(12.0, RequestPriceEstimator::estimate($ingredient->id, null, null));
    }

    public function test_an_asset_prices_from_its_own_supplier_table(): void
    {
        $asset = $this->asset(4250);

        $asset->supplierLinks()->create([
            'supplier_id' => $this->supplier->id, 'last_cost' => 4100, 'is_preferred' => true,
        ]);

        $this->assertSame(4100.0, RequestPriceEstimator::estimate(null, $asset->id, $this->supplier->id));
        $this->assertSame(4250.0, RequestPriceEstimator::estimate(null, $asset->id, null),
            'No link for that supplier, so the catalogue cost stands.');
    }

    public function test_a_hand_typed_line_has_no_price(): void
    {
        $this->assertSame(0.0, RequestPriceEstimator::estimate(null, null, $this->supplier->id));
    }

    public function test_the_form_carries_an_estimate_onto_the_line(): void
    {
        $asset = $this->asset(4250);
        $asset->supplierLinks()->create([
            'supplier_id' => $this->supplier->id, 'last_cost' => 4100, 'is_preferred' => true,
        ]);

        Livewire::test(PurchaseRequestForm::class)
            ->call('addAsset', $asset->id)
            ->assertSet('lines.0.est_price', 4100.0);
    }

    /** Changing the supplier must re-read the price, not keep the old one. */
    public function test_changing_the_supplier_re_estimates_the_line(): void
    {
        $other = Supplier::create([
            'company_id' => $this->company->id, 'name' => 'Dry Goods Sdn Bhd', 'is_active' => true,
        ]);

        $asset = $this->asset(4250);
        $asset->supplierLinks()->create([
            'supplier_id' => $this->supplier->id, 'last_cost' => 4100, 'is_preferred' => true,
        ]);
        $asset->supplierLinks()->create([
            'supplier_id' => $other->id, 'last_cost' => 3900, 'is_preferred' => false,
        ]);

        Livewire::test(PurchaseRequestForm::class)
            ->call('addAsset', $asset->id)
            ->assertSet('lines.0.est_price', 4100.0)
            ->set('lines.0.preferred_supplier_id', $other->id)
            ->assertSet('lines.0.est_price', 3900.0);
    }

    /** The bulk path the PDF uses must agree with the single-line one. */
    public function test_the_pdf_path_agrees_with_the_form_path(): void
    {
        $ingredient = $this->ingredient(12);
        $ingredient->suppliers()->attach($this->supplier->id, ['last_cost' => 9.5, 'is_preferred' => true, 'uom_id' => $this->piece->id]);

        $asset = $this->asset(4250);
        $asset->supplierLinks()->create([
            'supplier_id' => $this->supplier->id, 'last_cost' => 4100, 'is_preferred' => true,
        ]);

        $pr = PurchaseRequest::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'pr_number' => 'PR-' . uniqid(), 'requested_date' => now()->toDateString(),
            'status' => 'approved', 'created_by' => auth()->id(),
        ]);

        $ingLine = $pr->lines()->create([
            'ingredient_id' => $ingredient->id, 'quantity' => 4, 'uom_id' => $this->piece->id,
            'preferred_supplier_id' => $this->supplier->id, 'source' => PurchaseRequestLine::SOURCE_SUPPLIER,
        ]);
        $assetLine = $pr->lines()->create([
            'asset_id' => $asset->id, 'quantity' => 2, 'uom_id' => $this->piece->id,
            'preferred_supplier_id' => $this->supplier->id, 'source' => PurchaseRequestLine::SOURCE_ASSET,
        ]);
        $customLine = $pr->lines()->create([
            'custom_name' => 'BLUE ROPE, 10M', 'quantity' => 1, 'uom_id' => $this->piece->id,
            'source' => PurchaseRequestLine::SOURCE_SUPPLIER,
        ]);

        $prices = RequestPriceEstimator::forLines($pr->lines()->with(['ingredient', 'asset'])->get());

        $this->assertSame(9.5, $prices[$ingLine->id]);
        $this->assertSame(4100.0, $prices[$assetLine->id]);
        $this->assertSame(0.0, $prices[$customLine->id], 'A typed name has nothing behind it to price.');

        // Same numbers as the single-line path the form uses.
        $this->assertSame(
            RequestPriceEstimator::estimate($ingredient->id, null, $this->supplier->id),
            $prices[$ingLine->id]
        );
        $this->assertSame(
            RequestPriceEstimator::estimate(null, $asset->id, $this->supplier->id),
            $prices[$assetLine->id]
        );
    }
}
