<?php

namespace Tests\Feature;

use App\Livewire\Reports\Inventory\StockCard;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\PurchaseRecord;
use App\Models\PurchaseRecordLine;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WastageRecord;
use App\Models\WastageRecordLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A stock card is a running balance, so it has to be in one unit.
 *
 * It was not. The four sources each record in their own — a purchase arrives
 * in the unit it was bought in, a transfer moves in the base unit, wastage and
 * counts are in the recipe unit — and their quantities were added to a single
 * balance as bare numbers. Two kilograms in, five hundred grams out, and the
 * card said 1.5 of something.
 *
 * The header made it worse by naming the purchase unit, so a card of grams was
 * labelled "kg" and the wrong figure looked like the right one.
 *
 * Every movement is now converted to the unit the item is COUNTED in, because a
 * count sets the balance and the balance can only mean something in that unit.
 */
class StockCardKeepsOneUnitTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private UnitOfMeasure $kg;
    private UnitOfMeasure $g;
    private Ingredient $flour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Card Co', 'slug' => Str::slug('Card Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(Permission::findOrCreate('reports.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->kg = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight', 'base_unit_factor' => 1000]);
        $this->g  = UnitOfMeasure::create(['name' => 'Gram', 'abbreviation' => 'g', 'type' => 'weight', 'base_unit_factor' => 1]);

        // Bought by the kilogram, counted by the gram — the ordinary shape here.
        $this->flour = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Flour',
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $this->g->id,
            'current_cost' => 3.00, 'is_active' => true,
        ]);
    }

    private function purchase(string $date, float $qty, UnitOfMeasure $uom): void
    {
        $record = PurchaseRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'purchase_date' => $date, 'reference_number' => 'PR-1',
        ]);

        PurchaseRecordLine::create([
            'purchase_record_id' => $record->id, 'ingredient_id' => $this->flour->id,
            'quantity' => $qty, 'uom_id' => $uom->id, 'unit_cost' => 1, 'total_cost' => $qty,
        ]);
    }

    private function wastage(string $date, float $qty, UnitOfMeasure $uom): void
    {
        $record = WastageRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'wastage_date' => $date, 'reference_number' => 'WST-1', 'total_cost' => 0,
        ]);

        WastageRecordLine::create([
            'wastage_record_id' => $record->id, 'ingredient_id' => $this->flour->id,
            'quantity' => $qty, 'uom_id' => $uom->id, 'unit_cost' => 1, 'total_cost' => $qty,
        ]);
    }

    private function stockCount(string $date, float $qty, UnitOfMeasure $uom): void
    {
        $take = StockTake::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'status' => 'completed', 'method' => 'detailed',
            'stock_take_date' => $date, 'reference_number' => 'ST-1',
            'total_stock_cost' => 0, 'total_variance_cost' => 0,
        ]);

        StockTakeLine::create([
            'stock_take_id' => $take->id, 'ingredient_id' => $this->flour->id,
            'uom_id' => $uom->id, 'system_quantity' => 0,
            'actual_quantity' => $qty, 'variance_quantity' => 0, 'unit_cost' => 0.003, 'variance_cost' => 0,
        ]);
    }

    private function card()
    {
        return Livewire::actingAs($this->user)->test(StockCard::class)
            ->set('ingredientFilter', $this->flour->id)
            ->set('dateFrom', '2026-08-01')
            ->set('dateTo', '2026-08-31');
    }

    public function test_a_purchase_in_kilograms_lands_on_a_card_kept_in_grams(): void
    {
        $this->purchase('2026-08-01', 2, $this->kg);

        $movements = $this->card()->viewData('movements');

        $this->assertSame(2000.0, round($movements->first()['quantity'], 4),
            '2 kg bought is 2,000 g on a card counted in grams.');
    }

    public function test_the_balance_adds_movements_that_arrived_in_different_units(): void
    {
        // 2 kg in, 500 g out. Added raw that is 1.5; converted it is 1,500 g.
        $this->purchase('2026-08-01', 2, $this->kg);
        $this->wastage('2026-08-02', 500, $this->g);

        $balance = $this->card()->viewData('movements')->last()['balance'];

        $this->assertSame(1500.0, round($balance, 4),
            'Two kilograms minus five hundred grams is 1,500 g, not 1.5 of anything.');
    }

    public function test_a_count_resets_the_balance_in_the_same_unit(): void
    {
        $this->purchase('2026-08-01', 2, $this->kg);
        $this->stockCount('2026-08-03', 800, $this->g);

        $balance = $this->card()->viewData('movements')->last()['balance'];

        $this->assertSame(800.0, round($balance, 4), 'A count sets the balance to what was counted.');
    }

    public function test_the_card_names_the_unit_it_is_kept_in(): void
    {
        $this->purchase('2026-08-01', 2, $this->kg);

        $card = $this->card();

        $this->assertSame('g', $card->viewData('stockUom'),
            'The card is kept in the counted unit, so that is what it must name.');
        $card->assertSee('All quantities in g', false);
        $card->assertDontSee('UOM: kg', false);
    }

    public function test_an_item_counted_in_its_purchase_unit_is_unaffected(): void
    {
        $sugar = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Sugar',
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $this->kg->id,
            'current_cost' => 3.00, 'is_active' => true,
        ]);

        $record = PurchaseRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'purchase_date' => '2026-08-01', 'reference_number' => 'PR-2',
        ]);
        PurchaseRecordLine::create([
            'purchase_record_id' => $record->id, 'ingredient_id' => $sugar->id,
            'quantity' => 7, 'uom_id' => $this->kg->id, 'unit_cost' => 1, 'total_cost' => 7,
        ]);

        $movements = Livewire::actingAs($this->user)->test(StockCard::class)
            ->set('ingredientFilter', $sugar->id)
            ->set('dateFrom', '2026-08-01')->set('dateTo', '2026-08-31')
            ->viewData('movements');

        $this->assertSame(7.0, round($movements->first()['quantity'], 4),
            'Where the two units are the same thing, nothing should change.');
    }
}
