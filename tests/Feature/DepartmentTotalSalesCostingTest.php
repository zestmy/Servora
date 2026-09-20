<?php

namespace Tests\Feature;

use App\Livewire\Settings\Departments;
use App\Models\Company;
use App\Models\Department;
use App\Models\Outlet;
use App\Models\PurchaseRecord;
use App\Models\SalesCategory;
use App\Models\SalesRecord;
use App\Models\SalesRecordLine;
use App\Models\User;
use App\Services\CostSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A department that buys for the whole outlet — consumables, packaging — is
 * costed against TOTAL sales, not one sales category, and gets a P&L row of
 * its own without its revenue being counted twice.
 */
class DepartmentTotalSalesCostingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private SalesCategory $food;
    private SalesCategory $drinks;
    private Department $kitchen;
    private Department $consumable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Total Co', 'slug' => Str::slug('Total Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);
        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);
        $this->actingAs($this->user);

        $this->food   = SalesCategory::create(['company_id' => $this->company->id, 'name' => 'Food', 'is_active' => true, 'sort_order' => 1]);
        $this->drinks = SalesCategory::create(['company_id' => $this->company->id, 'name' => 'Beverage', 'is_active' => true, 'sort_order' => 2]);

        $this->kitchen = Department::create([
            'company_id' => $this->company->id, 'name' => 'Kitchen', 'sales_category_id' => $this->food->id, 'is_active' => true,
        ]);
        $this->consumable = Department::create([
            'company_id' => $this->company->id, 'name' => 'Consumable', 'costs_against_total_sales' => true, 'is_active' => true,
        ]);

        // RM 1,500 of sales: 1,000 food, 500 beverage.
        $record = SalesRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'sale_date' => '2026-08-10', 'total_revenue' => 1500, 'total_cost' => 0,
        ]);
        foreach ([[$this->food, 1000], [$this->drinks, 500]] as [$cat, $amount]) {
            SalesRecordLine::create([
                'sales_record_id' => $record->id, 'sales_category_id' => $cat->id, 'item_name' => $cat->name,
                'quantity' => 1, 'unit_price' => $amount, 'unit_cost' => 0, 'total_revenue' => $amount, 'total_cost' => 0,
            ]);
        }

        foreach ([[$this->kitchen, 300], [$this->consumable, 150]] as [$dept, $amount]) {
            PurchaseRecord::create([
                'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
                'department_id' => $dept->id, 'purchase_date' => '2026-08-12', 'total_amount' => $amount,
            ]);
        }
    }

    public function test_the_cost_summary_gives_it_a_row_measured_against_total_sales(): void
    {
        $summary = (new CostSummaryService())->generate('2026-08');
        $rows = collect($summary['categories'])->keyBy('name');

        $this->assertEquals(1000, $rows['Food']['revenue']);
        $this->assertEquals(300, $rows['Food']['cogs']);
        $this->assertEquals(30.0, $rows['Food']['cost_pct']);

        $consumable = $rows['Consumable · total sales'];
        $this->assertSame('total_sales', $consumable['basis']);
        $this->assertSame('Consumable', $consumable['short_name'], 'Screens with a note under the name use the plain one.');
        $this->assertEquals(1500, $consumable['revenue'], 'Measured against every sale, not one category.');
        $this->assertEquals(150, $consumable['cogs']);
        $this->assertEquals(10.0, $consumable['cost_pct'], '150 of 1,500.');

        $this->assertEquals(1500, $summary['totals']['revenue'], 'Its revenue is total sales for reference, not added again.');
        $this->assertEquals(450, $summary['totals']['cogs'], 'Its cost is counted once, and no longer as non-revenue.');
        $this->assertEquals(30.0, $summary['totals']['cost_pct']);
    }

    public function test_the_monthly_wip_review_shows_cost_of_goods_from_stock_takes(): void
    {
        // Opening stock (July's count) and closing stock (August's), per department.
        foreach ([
            [$this->kitchen, '2026-07-31', 200], [$this->kitchen, '2026-08-31', 250],
            [$this->consumable, '2026-07-31', 50], [$this->consumable, '2026-08-31', 20],
        ] as [$dept, $date, $value]) {
            \App\Models\StockTake::create([
                'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'department_id' => $dept->id,
                'status' => 'completed', 'method' => 'summary', 'stock_take_date' => $date, 'total_stock_cost' => $value,
            ]);
        }

        $report = app(\App\Services\Reports\WeeklyWipReview::class)->build(
            $this->company->id, [$this->outlet->id], \Carbon\Carbon::parse('2026-08-01'), 3, 'month', true, false,
        );
        $cs = $report['cost_summary'];
        $rows = collect($cs['rows'])->keyBy('name');

        $food = $rows['Food'];
        $this->assertEquals(200, $food['opening_stock']);
        $this->assertEquals(300, $food['purchases']);
        $this->assertEquals(250, $food['closing_stock']);
        $this->assertEquals(250, $food['cogs'], '200 + 300 − 250.');
        $this->assertEquals(25.0, $food['cost_pct'], '250 of 1,000 food sales.');

        $this->assertEquals(0, $rows['Beverage']['cogs'], 'Sold, with no department buying for it — still listed.');

        $consumable = $rows['Consumable · total sales'];
        $this->assertEquals(180, $consumable['cogs'], '50 + 150 − 20.');
        $this->assertEquals(12.0, $consumable['cost_pct'], '180 of all 1,500 in sales.');

        $this->assertEquals(430, $cs['total']['cogs']);
        $this->assertEquals(1500, $cs['total']['revenue']);
        $this->assertEquals(28.7, $cs['total']['cost_pct']);
        $this->assertTrue($cs['has_opening']);
        $this->assertTrue($cs['has_closing']);
        $this->assertFalse($cs['company_wide']);

        // The month trend: August has a count at both ends; July has no June count.
        $this->assertSame(['Jun 2026', 'Jul 2026', 'Aug 2026'], $cs['trend']['labels']);
        $this->assertSame([false, false, true], $cs['trend']['complete']);
        $this->assertEquals(28.7, $cs['trend']['cogs_pct'][2]);
        $this->assertNull($cs['trend']['cogs_pct'][0], 'No sales in June — no percentage.');

        // Never built for a company other than the signed-in user's.
        $other = Company::create(['name' => 'Other Co', 'slug' => 'other-' . uniqid(), 'currency' => 'MYR', 'is_active' => true]);
        $this->assertNull(app(\App\Services\Reports\WeeklyWipReview::class)->build(
            $other->id, [], \Carbon\Carbon::parse('2026-08-01'), 3, 'month', true, false,
        )['cost_summary']);

        // Weekly has no stock takes to work from.
        $weekly = app(\App\Services\Reports\WeeklyWipReview::class)->build(
            $this->company->id, [$this->outlet->id], \Carbon\Carbon::parse('2026-08-10'), 4, 'week', false, false,
        );
        $this->assertNull($weekly['cost_summary']);
    }

    public function test_the_settings_screen_saves_and_reloads_total_sales(): void
    {
        $dept = Department::create(['company_id' => $this->company->id, 'name' => 'Packaging', 'sales_category_id' => $this->food->id, 'is_active' => true]);

        Livewire::test(Departments::class)
            ->call('openEdit', $dept->id)
            ->assertSet('sales_category_id', (string) $this->food->id)
            ->set('sales_category_id', Departments::TOTAL_SALES)
            ->call('save')
            ->assertHasNoErrors();

        $dept->refresh();
        $this->assertTrue($dept->costs_against_total_sales);
        $this->assertNull($dept->sales_category_id, 'Total sales replaces the category, it does not sit beside it.');

        Livewire::test(Departments::class)
            ->call('openEdit', $dept->id)
            ->assertSet('sales_category_id', Departments::TOTAL_SALES)
            ->assertSee('Total sales (all categories)');

        // And back to a single category clears the flag.
        Livewire::test(Departments::class)
            ->call('openEdit', $dept->id)
            ->set('sales_category_id', (string) $this->drinks->id)
            ->call('save');

        $dept->refresh();
        $this->assertFalse($dept->costs_against_total_sales);
        $this->assertSame($this->drinks->id, (int) $dept->sales_category_id);
    }

    public function test_the_settings_screen_rejects_an_unknown_category(): void
    {
        Livewire::test(Departments::class)
            ->call('openCreate')
            ->set('name', 'Bogus')
            ->set('sales_category_id', '999999')
            ->call('save')
            ->assertHasErrors('sales_category_id');
    }
}
