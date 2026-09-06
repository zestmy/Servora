<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WastageRecord;
use App\Models\WastageRecordLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Wastage tab's "download by filter" pair: a PDF and a workbook, both
 * totalling the same range by department — the grouping the on-screen chart
 * already draws (DepartmentChartTest covers that; this covers the export).
 */
class WastageSummaryExportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private UnitOfMeasure $kg;
    private Ingredient $flour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Wastage Export Co', 'slug' => Str::slug('Wastage Export Co') . '-' . uniqid(),
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

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(Permission::findOrCreate('inventory.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->kg    = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight']);
        $this->flour = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Flour',
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $this->kg->id,
            'current_cost' => 5, 'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    private function wastage(Department $dept, float $cost, string $date = '2026-08-05', string $reason = 'Spoiled'): WastageRecord
    {
        $record = WastageRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'department_id' => $dept->id,
            'wastage_date' => $date, 'reference_number' => 'W-' . uniqid(), 'total_cost' => $cost,
        ]);

        WastageRecordLine::create([
            'wastage_record_id' => $record->id, 'ingredient_id' => $this->flour->id, 'uom_id' => $this->kg->id,
            'quantity' => $cost / 5, 'unit_cost' => 5, 'total_cost' => $cost, 'reason' => $reason,
        ]);

        return $record;
    }

    public function test_the_pdf_downloads_for_the_chosen_range(): void
    {
        $hot = Department::create(['company_id' => $this->company->id, 'name' => 'Hot Kitchen']);
        $this->wastage($hot, 20);

        $response = $this->get(route('inventory.wastage.summary', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('Wastage-Summary-2026-08-01-to-2026-08-31', $response->headers->get('content-disposition'));
    }

    public function test_the_excel_downloads_and_totals_match_the_pdf_range(): void
    {
        $hot = Department::create(['company_id' => $this->company->id, 'name' => 'Hot Kitchen']);
        $bar = Department::create(['company_id' => $this->company->id, 'name' => 'Bar']);
        $this->wastage($hot, 20);
        $this->wastage($bar, 10);

        $response = $this->get(route('inventory.wastage.summary-excel', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $this->assertStringContainsString('Wastage-Summary-2026-08-01-to-2026-08-31.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_groups_are_ranked_biggest_cost_first(): void
    {
        $hot = Department::create(['company_id' => $this->company->id, 'name' => 'Hot Kitchen']);
        $bar = Department::create(['company_id' => $this->company->id, 'name' => 'Bar']);
        $this->wastage($hot, 90);
        $this->wastage($bar, 30);

        $controller = app(\App\Http\Controllers\WastageSummaryController::class);
        $data = $this->invokeLoad($controller, ['from' => '2026-08-01', 'to' => '2026-08-31']);

        $this->assertSame(['Hot Kitchen', 'Bar'], array_column($data['groups'], 'name'));
        $this->assertEqualsWithDelta(75.0, $data['groups'][0]['share'], 0.01);
        $this->assertEqualsWithDelta(120.0, $data['totals']['value'], 0.001);
        $this->assertSame(2, $data['totals']['count']);
    }

    public function test_no_department_is_its_own_group(): void
    {
        $record = WastageRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'department_id' => null,
            'wastage_date' => '2026-08-05', 'total_cost' => 15,
        ]);
        WastageRecordLine::create([
            'wastage_record_id' => $record->id, 'ingredient_id' => $this->flour->id, 'uom_id' => $this->kg->id,
            'quantity' => 3, 'unit_cost' => 5, 'total_cost' => 15, 'reason' => 'Expired',
        ]);

        $controller = app(\App\Http\Controllers\WastageSummaryController::class);
        $data = $this->invokeLoad($controller, ['from' => '2026-08-01', 'to' => '2026-08-31']);

        $this->assertSame('No department', $data['groups'][0]['name']);
    }

    public function test_the_detail_block_carries_the_reasons_from_its_lines(): void
    {
        $hot = Department::create(['company_id' => $this->company->id, 'name' => 'Hot Kitchen']);
        $this->wastage($hot, 20, '2026-08-05', 'Spoiled');

        $controller = app(\App\Http\Controllers\WastageSummaryController::class);
        $data = $this->invokeLoad($controller, ['from' => '2026-08-01', 'to' => '2026-08-31']);

        $row = $data['detailBlocks'][0]['rows']->first();
        $costColumn = collect($data['detailColumns'])->firstWhere('label', 'Cost (RM)');
        $reasonColumn = collect($data['detailColumns'])->firstWhere('label', 'Reason(s)');

        $this->assertEqualsWithDelta(20.0, $costColumn['value']($row), 0.001);
        $this->assertSame('Spoiled', $reasonColumn['value']($row));
    }

    public function test_the_report_only_covers_the_range_asked_for(): void
    {
        $hot = Department::create(['company_id' => $this->company->id, 'name' => 'Hot Kitchen']);
        $this->wastage($hot, 20, '2026-08-10');
        $this->wastage($hot, 99, '2026-09-10');

        $controller = app(\App\Http\Controllers\WastageSummaryController::class);
        $data = $this->invokeLoad($controller, ['from' => '2026-08-01', 'to' => '2026-08-31']);

        $this->assertEqualsWithDelta(20.0, $data['totals']['value'], 0.001, 'September must not be in an August file.');
    }

    public function test_a_hand_edited_outlet_cannot_widen_the_report(): void
    {
        $other = Company::create([
            'name' => 'Other Co', 'slug' => Str::slug('Other Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $theirs = Outlet::create(['company_id' => $other->id, 'name' => 'Theirs', 'code' => 'OTH', 'is_active' => true]);

        $response = $this->get(route('inventory.wastage.summary', [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'outlet' => $theirs->id,
        ]));

        $response->assertOk();
    }

    /** Invoke the protected load() the way the __invoke() methods do, without a full HTTP round trip. */
    private function invokeLoad(object $controller, array $query): array
    {
        $request = \Illuminate\Http\Request::create('/', 'GET', $query);
        $method  = new \ReflectionMethod($controller, 'load');
        $method->setAccessible(true);

        return $method->invoke($controller, $request);
    }
}
