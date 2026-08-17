<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\SalesCategory;
use App\Models\SalesRecord;
use App\Scopes\CompanyScope;
use App\Services\Sales\ZeoniqSalesWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The shared Zeoniq write path, exercised the way the POS sync agent's
 * queued ingest will call it: with NOBODY authenticated. Every assertion
 * that reads the database drops CompanyScope for the same reason the writer
 * does — the scope silently narrows to nothing without an auth user.
 */
class ZeoniqSalesWriterTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private SalesCategory $food;
    private SalesCategory $beverage;
    private ZeoniqSalesWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Writer Co', 'slug' => Str::slug('Writer Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->food = SalesCategory::create([
            'company_id' => $this->company->id, 'name' => 'Food', 'type' => 'food',
        ]);
        $this->beverage = SalesCategory::create([
            'company_id' => $this->company->id, 'name' => 'Beverage', 'type' => 'beverage',
        ]);

        $this->writer = app(ZeoniqSalesWriter::class);
    }

    private function write(array $overrides = [], array $record = null): string|bool
    {
        return $this->writer->write(
            companyId: $overrides['companyId'] ?? $this->company->id,
            outletId: $overrides['outletId'] ?? $this->outlet->id,
            userId: $overrides['userId'] ?? null,
            date: $overrides['date'] ?? '2026-08-15',
            mealPeriod: $overrides['mealPeriod'] ?? 'lunch',
            record: $record ?? [
                'total_sales' => 1000.0,
                'net_sales' => 950.0,
                'transactions' => 40,
                'pax' => 55,
                'departments' => ['Mains' => 700.0, 'Dessert' => 150.0, 'Drinks' => 100.0],
            ],
            departmentMapping: $overrides['departmentMapping'] ?? [
                'Mains' => $this->food->id, 'Dessert' => $this->food->id, 'Drinks' => $this->beverage->id,
            ],
            replaceExisting: $overrides['replaceExisting'] ?? false,
            source: $overrides['source'] ?? SalesRecord::SOURCE_POS_AGENT,
            batchId: $overrides['batchId'] ?? null,
            replaceOnlySources: array_key_exists('replaceOnlySources', $overrides)
                ? $overrides['replaceOnlySources'] : null,
        );
    }

    private function records()
    {
        return SalesRecord::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->company->id)
            ->get();
    }

    public function test_writes_record_and_merges_departments_by_category(): void
    {
        $this->assertTrue($this->write());

        $record = $this->records()->sole();
        $this->assertSame('lunch', $record->meal_period);
        $this->assertSame('pos_agent', $record->source);
        $this->assertEquals(950.0, (float) $record->total_revenue);
        $this->assertSame(55, $record->pax);

        // Mains + Dessert both map to Food and merge into one line.
        $lines = $record->lines()->orderBy('total_revenue', 'desc')->get();
        $this->assertCount(2, $lines);
        $this->assertSame($this->food->id, $lines[0]->sales_category_id);
        $this->assertEquals(850.0, (float) $lines[0]->total_revenue);
        $this->assertSame($this->beverage->id, $lines[1]->sales_category_id);
        $this->assertEquals(100.0, (float) $lines[1]->total_revenue);
    }

    public function test_writes_without_an_authenticated_user(): void
    {
        // The queue-worker condition: no session, no auth, explicit company.
        $this->assertGuest();
        $this->assertTrue($this->write());
        $this->assertCount(1, $this->records());
    }

    public function test_no_departments_falls_back_to_a_single_line(): void
    {
        $this->assertTrue($this->write(record: ['total_sales' => 500.0]));

        $line = $this->records()->sole()->lines()->sole();
        $this->assertNull($line->sales_category_id);
        $this->assertSame('Lunch Sales', $line->item_name);
        $this->assertEquals(500.0, (float) $line->total_revenue);
    }

    public function test_same_meal_period_is_a_duplicate_without_replace(): void
    {
        $this->assertTrue($this->write());
        $this->assertSame('duplicate', $this->write());
        $this->assertCount(1, $this->records());
    }

    public function test_all_day_blocks_and_is_blocked_by_meal_periods(): void
    {
        $this->assertTrue($this->write(['mealPeriod' => 'all_day']));
        // An all_day record covers the whole date.
        $this->assertSame('duplicate', $this->write(['mealPeriod' => 'dinner']));

        // And the reverse: writing all_day over any existing period.
        $this->write(['date' => '2026-08-16', 'mealPeriod' => 'dinner']);
        $this->assertSame('duplicate', $this->write(['date' => '2026-08-16', 'mealPeriod' => 'all_day']));
    }

    public function test_replace_swaps_only_the_written_meal_period(): void
    {
        $this->write(['mealPeriod' => 'lunch']);
        $this->write(['mealPeriod' => 'dinner']);

        $result = $this->write(
            ['mealPeriod' => 'lunch', 'replaceExisting' => true],
            ['total_sales' => 111.0],
        );

        $this->assertSame('replaced', $result);
        $records = $this->records();
        $this->assertCount(2, $records);
        $this->assertEquals(111.0, (float) $records->firstWhere('meal_period', 'lunch')->total_revenue);
        // Dinner was untouched by the lunch replace.
        $this->assertEquals(950.0, (float) $records->firstWhere('meal_period', 'dinner')->total_revenue);
    }

    public function test_replace_refuses_to_delete_foreign_sources(): void
    {
        $this->write(['source' => SalesRecord::SOURCE_MANUAL]);

        $result = $this->write([
            'replaceExisting' => true,
            'replaceOnlySources' => [SalesRecord::SOURCE_POS_AGENT],
        ]);

        $this->assertSame('conflict', $result);
        // The hand-typed record survived, and nothing new was written.
        $record = $this->records()->sole();
        $this->assertSame('manual', $record->source);
    }

    public function test_replace_may_delete_its_own_source(): void
    {
        $this->write(); // pos_agent

        $result = $this->write([
            'replaceExisting' => true,
            'replaceOnlySources' => [SalesRecord::SOURCE_POS_AGENT],
        ], ['total_sales' => 222.0]);

        $this->assertSame('replaced', $result);
        $this->assertEquals(222.0, (float) $this->records()->sole()->total_revenue);
    }
}
