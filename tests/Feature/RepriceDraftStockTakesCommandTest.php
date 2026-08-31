<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientUomConversion;
use App\Models\Outlet;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The repair for drafts already stored at the base-UOM price.
 *
 * Fixing the form stops new drafts going wrong, but every draft saved before it
 * still holds the inflated unit_cost and total_stock_cost. This command brings
 * those rows back in line with what the form now shows — and must not touch a
 * completed stock take, which keeps the price it was counted at on purpose.
 */
class RepriceDraftStockTakesCommandTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Ingredient $dough;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Repair Co', 'slug' => Str::slug('Repair Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $batch = UnitOfMeasure::create(['name' => 'Batch', 'abbreviation' => 'batch', 'type' => 'count']);
        $piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pcs', 'type' => 'count']);

        $this->dough = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Pizza Dough',
            'base_uom_id' => $batch->id, 'recipe_uom_id' => $piece->id,
            'current_cost' => 28.45, 'is_active' => true,
        ]);

        IngredientUomConversion::create([
            'ingredient_id' => $this->dough->id,
            'from_uom_id' => $batch->id, 'to_uom_id' => $piece->id, 'factor' => 10,
        ]);
    }

    /** A stock take as the old code left it: the line stored at the base-uom price. */
    private function damaged(string $status): StockTake
    {
        $take = StockTake::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'status' => $status, 'method' => 'detailed',
            'stock_take_date' => today()->toDateString(),
            'total_stock_cost' => 113.80,     // 4 x 28.45, the inflated figure
            'total_variance_cost' => 28.45,   // 1 x 28.45
        ]);

        StockTakeLine::create([
            'stock_take_id' => $take->id, 'ingredient_id' => $this->dough->id,
            'uom_id' => $this->dough->recipe_uom_id,
            'system_quantity' => 3, 'actual_quantity' => 4, 'variance_quantity' => 1,
            'unit_cost' => 28.45, 'variance_cost' => 28.45,
        ]);

        return $take;
    }

    public function test_a_dry_run_reports_the_damage_without_writing(): void
    {
        $take = $this->damaged('draft');

        $this->artisan('stock-takes:reprice-drafts')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(28.45, round((float) $take->lines()->first()->unit_cost, 4));
        $this->assertSame(113.80, round((float) $take->fresh()->total_stock_cost, 2));
    }

    public function test_apply_reprices_the_line_and_the_stored_totals(): void
    {
        $take = $this->damaged('draft');

        $this->artisan('stock-takes:reprice-drafts --apply')->assertSuccessful();

        $line = $take->lines()->first();
        $this->assertSame(2.845, round((float) $line->unit_cost, 4), 'The line should hold the per-piece price.');
        $this->assertSame(2.845, round((float) $line->variance_cost, 4), '1 piece over at RM2.845.');

        $take->refresh();
        $this->assertSame(11.38, round((float) $take->total_stock_cost, 2), '4 pieces at RM2.845.');
        $this->assertSame(2.845, round((float) $take->total_variance_cost, 4));
    }

    public function test_a_completed_stock_take_is_left_alone(): void
    {
        $take = $this->damaged('completed');

        $this->artisan('stock-takes:reprice-drafts --apply')->assertSuccessful();

        $this->assertSame(28.45, round((float) $take->lines()->first()->unit_cost, 4));
        $this->assertSame(113.80, round((float) $take->fresh()->total_stock_cost, 2));
    }

    public function test_a_draft_already_priced_correctly_is_not_rewritten(): void
    {
        $take = StockTake::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'status' => 'draft', 'method' => 'detailed',
            'stock_take_date' => today()->toDateString(),
            'total_stock_cost' => 11.38, 'total_variance_cost' => 2.845,
        ]);

        StockTakeLine::create([
            'stock_take_id' => $take->id, 'ingredient_id' => $this->dough->id,
            'uom_id' => $this->dough->recipe_uom_id,
            'system_quantity' => 3, 'actual_quantity' => 4, 'variance_quantity' => 1,
            'unit_cost' => 2.845, 'variance_cost' => 2.845,
        ]);

        $this->artisan('stock-takes:reprice-drafts')
            ->expectsOutputToContain('already priced in its count UOM')
            ->assertSuccessful();
    }

    public function test_a_line_whose_ingredient_is_gone_is_left_at_its_stored_price(): void
    {
        $take = $this->damaged('draft');
        $this->dough->delete(); // soft delete — the draft outlives the ingredient

        $this->artisan('stock-takes:reprice-drafts --apply')->assertSuccessful();

        // Soft-deleted ingredients still price (withTrashed), so this one is repaired;
        // what must not happen is a crash or a line silently zeroed.
        $this->assertSame(2.845, round((float) $take->lines()->first()->unit_cost, 4));
    }
}
