<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\PosAgent;
use App\Models\PosSalesBatch;
use App\Models\SalesCategory;
use App\Models\SalesClosure;
use App\Models\SalesRecord;
use App\Models\ZeoniqDepartmentMapping;
use App\Scopes\CompanyScope;
use App\Services\Sales\PosSalesBatchProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * The batch processor end to end: a real xlsx in the shape of Zeoniq's
 * Daily Summary Listing goes in, and the batch either applies through the
 * shared writer or parks with a status the POS Sync screen can act on.
 *
 * The processor runs the way the queue runs it — no authenticated user —
 * so any CompanyScope leak in the write path fails here, not in prod.
 */
class PosSalesBatchProcessingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private PosAgent $agent;
    private SalesCategory $food;
    private SalesCategory $beverage;
    private PosSalesBatchProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        // The AI mapping matcher calls out over HTTP; never let a test
        // actually do that.
        Http::fake(['*' => Http::response(['error' => 'faked'], 500)]);
        Storage::fake('local');

        $this->company = Company::create([
            'name' => 'Batch Co', 'slug' => Str::slug('Batch Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->agent = PosAgent::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Front counter till',
        ]);

        $this->food = SalesCategory::create([
            'company_id' => $this->company->id, 'name' => 'Food', 'type' => 'food',
        ]);
        $this->beverage = SalesCategory::create([
            'company_id' => $this->company->id, 'name' => 'Beverage', 'type' => 'beverage',
        ]);

        $this->processor = app(PosSalesBatchProcessor::class);
    }

    // ── Fixtures ────────────────────────────────────────────────────────

    /**
     * A minimal file in the Daily Summary Listing shape the parser knows:
     * title row, "Department" section label, a header row doubling as the
     * department-names row (each name's Net Total in the column after it),
     * an "Outlet:" row, then one data row per [date, net, total, departments].
     *
     * @param  array<int, array{date: string, net: float, total: float, departments: array<string, float>}>  $rows
     */
    private function makeReport(array $rows, array $departmentColumns = ['Food' => 5, 'Drinks' => 7]): PosSalesBatch
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Daily Summary Listing');

        // Section label above the header: department names start at this column.
        $firstDeptCol = min(array_values($departmentColumns));
        $sheet->setCellValue([$firstDeptCol + 1, 2], 'Department');

        $sheet->fromArray(['Business Date', 'Trans. Count', 'Guest Count', 'Net Amount Excl', 'Total Sales'], null, 'A3');
        foreach ($departmentColumns as $name => $col) {
            $sheet->setCellValue([$col + 1, 3], $name);
        }

        $sheet->setCellValue('A4', 'Outlet: KLCC');

        $rowIdx = 5;
        foreach ($rows as $row) {
            $sheet->setCellValue('A' . $rowIdx, $row['date']);
            $sheet->setCellValue('B' . $rowIdx, $row['transactions'] ?? 40);
            $sheet->setCellValue('C' . $rowIdx, $row['pax'] ?? 55);
            $sheet->setCellValue('D' . $rowIdx, $row['net']);
            $sheet->setCellValue('E' . $rowIdx, $row['total']);
            foreach ($row['departments'] as $name => $revenue) {
                // Net Total sits one column after the department's name.
                $sheet->setCellValue([$departmentColumns[$name] + 2, $rowIdx], $revenue);
            }
            $rowIdx++;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'zeoniq');
        (new Xlsx($spreadsheet))->save($tmp);
        $content = file_get_contents($tmp);
        unlink($tmp);

        return $this->makeBatch($content);
    }

    private function makeBatch(string $content): PosSalesBatch
    {
        $sha = hash('sha256', $content);
        $path = 'pos-batches/' . $this->company->id . '/' . $sha . '.xlsx';
        Storage::disk('local')->put($path, $content);

        return PosSalesBatch::withoutGlobalScope(CompanyScope::class)->create([
            'company_id'      => $this->company->id,
            'pos_agent_id'    => $this->agent->id,
            'outlet_id'       => $this->outlet->id,
            'source_filename' => 'DailySummary.xlsx',
            'sha256'          => $sha,
            'file_path'       => $path,
            'status'          => PosSalesBatch::STATUS_RECEIVED,
        ]);
    }

    private function mapDepartments(): void
    {
        foreach (['Food' => $this->food->id, 'Drinks' => $this->beverage->id] as $dept => $categoryId) {
            ZeoniqDepartmentMapping::withoutGlobalScope(CompanyScope::class)->create([
                'company_id' => $this->company->id,
                'zeoniq_department_name' => $dept,
                'sales_category_id' => $categoryId,
            ]);
        }
    }

    private function records()
    {
        return SalesRecord::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->company->id)
            ->get();
    }

    // ── The happy path ──────────────────────────────────────────────────

    public function test_a_mapped_report_applies_end_to_end(): void
    {
        $this->mapDepartments();

        $batch = $this->makeReport([
            ['date' => '8/15/2026', 'net' => 950.0, 'total' => 1000.0, 'departments' => ['Food' => 700.0, 'Drinks' => 250.0]],
            ['date' => '8/16/2026', 'net' => 500.0, 'total' => 520.0, 'departments' => ['Food' => 500.0]],
        ]);

        $this->processor->process($batch);
        $batch->refresh();

        $this->assertSame(PosSalesBatch::STATUS_APPLIED, $batch->status, (string) $batch->error);
        $this->assertSame(['created' => 2, 'replaced' => 0, 'skipped' => 0], $batch->result);
        $this->assertSame('2026-08-15', $batch->date_from->toDateString());
        $this->assertSame('2026-08-16', $batch->date_to->toDateString());
        $this->assertNull($batch->applied_by);

        $records = $this->records();
        $this->assertCount(2, $records);

        $day1 = $records->first(fn ($r) => $r->sale_date->toDateString() === '2026-08-15');
        $this->assertSame('pos_agent', $day1->source);
        $this->assertSame($batch->id, $day1->pos_sales_batch_id);
        $this->assertSame($this->outlet->id, $day1->outlet_id);
        $this->assertEquals(950.0, (float) $day1->total_revenue);

        $lines = $day1->lines()->get();
        $this->assertCount(2, $lines);
        $this->assertEquals(700.0, (float) $lines->firstWhere('sales_category_id', $this->food->id)->total_revenue);
        $this->assertEquals(250.0, (float) $lines->firstWhere('sales_category_id', $this->beverage->id)->total_revenue);
    }

    public function test_a_changed_re_export_replaces_the_agents_own_records(): void
    {
        $this->mapDepartments();

        $first = $this->makeReport([
            ['date' => '8/15/2026', 'net' => 950.0, 'total' => 1000.0, 'departments' => ['Food' => 950.0]],
        ]);
        $this->processor->process($first);

        // Same day re-exported after a void — different bytes, new batch.
        $second = $this->makeReport([
            ['date' => '8/15/2026', 'net' => 900.0, 'total' => 940.0, 'departments' => ['Food' => 900.0]],
        ]);
        $this->processor->process($second);
        $second->refresh();

        $this->assertSame(PosSalesBatch::STATUS_APPLIED, $second->status, (string) $second->error);
        $this->assertSame(1, $second->result['replaced']);

        $record = $this->records()->sole();
        $this->assertEquals(900.0, (float) $record->total_revenue);
        $this->assertSame($second->id, $record->pos_sales_batch_id);
    }

    // ── Parking ─────────────────────────────────────────────────────────

    public function test_unmapped_departments_park_the_batch_and_write_nothing(): void
    {
        // Only Food is mapped; Drinks is new.
        ZeoniqDepartmentMapping::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $this->company->id,
            'zeoniq_department_name' => 'Food',
            'sales_category_id' => $this->food->id,
        ]);

        $batch = $this->makeReport([
            ['date' => '8/15/2026', 'net' => 950.0, 'total' => 1000.0, 'departments' => ['Food' => 700.0, 'Drinks' => 250.0]],
        ]);

        $this->processor->process($batch);
        $batch->refresh();

        $this->assertSame(PosSalesBatch::STATUS_NEEDS_MAPPING, $batch->status);
        $this->assertSame(['Drinks'], $batch->parsed_summary['unmapped_departments']);
        $this->assertCount(0, $this->records());

        // Mapping arrives (the review screen saves it), re-apply succeeds.
        ZeoniqDepartmentMapping::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $this->company->id,
            'zeoniq_department_name' => 'Drinks',
            'sales_category_id' => $this->beverage->id,
        ]);

        $this->processor->reapply($batch->refresh());
        $this->assertSame(PosSalesBatch::STATUS_APPLIED, $batch->refresh()->status);
        $this->assertCount(1, $this->records());
    }

    public function test_hand_typed_records_park_the_batch_instead_of_dying(): void
    {
        $this->mapDepartments();

        // A person already entered this day.
        SalesRecord::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'sale_date' => '2026-08-15', 'meal_period' => 'all_day',
            'total_revenue' => 800, 'total_cost' => 0, 'pax' => 1,
            'source' => SalesRecord::SOURCE_MANUAL,
        ]);

        $batch = $this->makeReport([
            ['date' => '8/15/2026', 'net' => 950.0, 'total' => 1000.0, 'departments' => ['Food' => 950.0]],
        ]);

        $this->processor->process($batch);
        $batch->refresh();

        $this->assertSame(PosSalesBatch::STATUS_NEEDS_REVIEW, $batch->status);
        // Nothing was changed: the manual record survived alone.
        $record = $this->records()->sole();
        $this->assertSame('manual', $record->source);
        $this->assertEquals(800.0, (float) $record->total_revenue);

        // A human decides the POS figures win.
        $this->processor->reapply($batch, null, overrideConflicts: true);
        $batch->refresh();

        $this->assertSame(PosSalesBatch::STATUS_APPLIED, $batch->status, (string) $batch->error);
        $record = $this->records()->sole();
        $this->assertSame('pos_agent', $record->source);
        $this->assertEquals(950.0, (float) $record->total_revenue);
    }

    public function test_sales_on_a_closed_day_park_for_review(): void
    {
        $this->mapDepartments();

        SalesClosure::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'closure_date' => '2026-08-15', 'reason' => 'Renovation',
        ]);

        $batch = $this->makeReport([
            ['date' => '8/15/2026', 'net' => 950.0, 'total' => 1000.0, 'departments' => ['Food' => 950.0]],
        ]);

        $this->processor->process($batch);

        $this->assertSame(PosSalesBatch::STATUS_NEEDS_REVIEW, $batch->refresh()->status);
        $this->assertCount(0, $this->records());
    }

    public function test_an_unrecognisable_file_fails_with_a_readable_error(): void
    {
        $batch = $this->makeBatch('not,a,zeoniq,report');

        $this->processor->process($batch);
        $batch->refresh();

        $this->assertSame(PosSalesBatch::STATUS_FAILED, $batch->status);
        $this->assertNotEmpty($batch->error);
        $this->assertCount(0, $this->records());
    }
}
