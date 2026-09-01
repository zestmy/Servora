<?php

namespace Tests\Feature;

use App\Livewire\Reports\Inventory\StockBalancePackage;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Last stock take qty" means the last COUNT — not the last row written.
 *
 * The subquery took MAX(stock_take_lines.id) over every stock take it could
 * join to, with no filter on status or deleted_at. So a half-filled draft, or
 * a count somebody had deleted, outranked the real count underneath it; and
 * because a draft starts every line at zero, the column reported a confident
 * 0.00 for items nobody had counted. On production this was every ingredient:
 * 499 read from drafts, 50 from deleted stock takes, none from a real count.
 *
 * Ordering by id was the second half of it. Saving a stock take deletes and
 * recreates its lines, so ids track when a sheet was last touched, not when
 * the stock was counted — a backdated count completed today would win.
 */
class StockBalanceUsesCompletedCountsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private Ingredient $flour;
    private UnitOfMeasure $kg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Balance Co', 'slug' => Str::slug('Balance Co') . '-' . uniqid(),
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

        $this->kg = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight']);

        $this->flour = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Flour',
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $this->kg->id,
            'current_cost' => 3.00, 'is_active' => true,
        ]);
    }

    private function stockTake(string $status, string $date, float $qty, bool $trashed = false): StockTake
    {
        $take = StockTake::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'status' => $status, 'method' => 'detailed', 'stock_take_date' => $date,
            'total_stock_cost' => $qty * 3, 'total_variance_cost' => 0,
        ]);

        StockTakeLine::create([
            'stock_take_id' => $take->id, 'ingredient_id' => $this->flour->id,
            'uom_id' => $this->flour->base_uom_id,
            'system_quantity' => $qty, 'actual_quantity' => $qty, 'variance_quantity' => 0,
            'unit_cost' => 3.00, 'variance_cost' => 0,
        ]);

        if ($trashed) {
            $take->delete();
        }

        return $take;
    }

    private function lastQty()
    {
        $items = Livewire::actingAs($this->user)->test(StockBalancePackage::class)->viewData('items');

        return $items->firstWhere('id', $this->flour->id)?->last_qty;
    }

    public function test_the_quantity_carries_the_unit_it_was_counted_in(): void
    {
        // Bought by the kilogram, counted by the gram. Pack size, purchase price
        // and current cost are all per purchase unit, so a bare number in the
        // quantity column read as though it shared theirs — 3,860 next to "kg"
        // when the count was 3,860 grams.
        $g = UnitOfMeasure::create(['name' => 'Gram', 'abbreviation' => 'g', 'type' => 'weight', 'base_unit_factor' => 1]);

        $chilli = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Chilli Flakes',
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $g->id,
            'current_cost' => 3.00, 'is_active' => true,
        ]);

        $take = StockTake::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'status' => 'completed', 'method' => 'detailed',
            'stock_take_date' => '2026-08-01',
            'total_stock_cost' => 0, 'total_variance_cost' => 0,
        ]);

        StockTakeLine::create([
            'stock_take_id' => $take->id, 'ingredient_id' => $chilli->id,
            'uom_id' => $g->id,   // counted in grams
            'system_quantity' => 0, 'actual_quantity' => 3860, 'variance_quantity' => 3860,
            'unit_cost' => 0.003, 'variance_cost' => 0,
        ]);

        $row = Livewire::actingAs($this->user)->test(StockBalancePackage::class)
            ->viewData('items')->firstWhere('id', $chilli->id);

        $this->assertSame('g', $row->last_qty_uom, 'The quantity must name the unit it was counted in.');
        $this->assertSame('kg', $row->uom, 'The purchase unit column is still the purchase unit.');
    }

    public function test_the_two_units_are_named_apart_on_screen(): void
    {
        $html = Livewire::actingAs($this->user)->test(StockBalancePackage::class)->html();

        $this->assertStringContainsString('Purchase UOM', $html,
            'One column headed just "UOM" is ambiguous once the row carries two.');
    }

    public function test_a_completed_count_is_reported(): void
    {
        $this->stockTake('completed', '2026-08-01', 40);

        $this->assertSame(40.0, (float) $this->lastQty());
    }

    public function test_a_later_draft_does_not_displace_the_completed_count(): void
    {
        $this->stockTake('completed', '2026-08-01', 40);
        $this->stockTake('draft', '2026-08-20', 0);   // sheet opened, nothing counted yet

        $this->assertSame(
            40.0,
            (float) $this->lastQty(),
            'An untouched draft reported 0 as though the stock had been counted and found empty.'
        );
    }

    public function test_a_deleted_count_does_not_displace_the_completed_count(): void
    {
        $this->stockTake('completed', '2026-08-01', 40);
        $this->stockTake('completed', '2026-08-20', 999, trashed: true);

        $this->assertSame(40.0, (float) $this->lastQty());
    }

    public function test_an_ingredient_with_only_a_draft_reads_as_never_counted(): void
    {
        $this->stockTake('draft', '2026-08-20', 0);

        $this->assertNull(
            $this->lastQty(),
            'Never counted must stay blank — the view prints "-" for null and 0.00 for a real zero.'
        );
    }

    public function test_the_latest_count_date_wins_not_the_last_row_written(): void
    {
        // Completed in this order, but dated in the other: the August count is
        // the answer even though the July sheet was written afterwards.
        $this->stockTake('completed', '2026-08-15', 40);
        $this->stockTake('completed', '2026-07-10', 12);

        $this->assertSame(40.0, (float) $this->lastQty());
    }
}
