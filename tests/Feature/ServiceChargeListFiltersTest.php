<?php

namespace Tests\Feature;

use App\Livewire\Hr\ServiceCharge;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Section;
use App\Models\ServiceChargePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Section, employment status and employment type filters on the service
 * charge table — as on Attendance Records — that only HIDE names.
 *
 * Narrowing the list to check one section must never move anybody's money:
 * the pool, its RM/point and the totals are always worked out over everyone
 * it pays, and a saved pool still includes the hidden staff.
 */
class ServiceChargeListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Section $kitchen;
    private Section $floor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Filter Co', 'slug' => Str::slug('Filter Co') . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create(['company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true]);

        $this->kitchen = Section::create(['company_id' => $this->company->id, 'name' => 'Kitchen', 'is_active' => true]);
        $this->floor   = Section::create(['company_id' => $this->company->id, 'name' => 'Floor', 'is_active' => true]);

        foreach (['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function staff(string $name, Section $section, string $status = 'confirmed', ?string $type = null): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'section_id' => $section->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2025-01-01',
            'employment_status' => $status, 'employment_type' => $type,
            'service_points_entitlement' => 10,
            'basic_salary' => 2000, 'pay_type' => 'monthly',
        ]);
    }

    private function panel()
    {
        $user = User::factory()->create(['company_id' => $this->company->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return Livewire::actingAs($user)
            ->test(ServiceCharge::class)
            ->set('outletFilter', (string) $this->outlet->id)
            ->set('scAmount', '2000');
    }

    private function visibleNames($component): array
    {
        return $component->viewData('scVisibleRows')->map(fn ($r) => $r['employee']->name)->values()->all();
    }

    public function test_a_section_filter_hides_names_but_not_money(): void
    {
        $this->staff('ALI COOK', $this->kitchen);
        $this->staff('SITI WAITER', $this->floor);

        // The table appears once the pool is saved.
        $page = $this->panel()->call('saveServiceCharge');
        $before = $page->viewData('serviceCharge')['totals'];

        $page->set('sectionFilter', (string) $this->kitchen->id);

        $this->assertSame(['ALI COOK'], $this->visibleNames($page));
        $this->assertSame(1, $page->viewData('scHiddenCount'));
        $this->assertEquals($before, $page->viewData('serviceCharge')['totals'],
            'Filtering the list must not change the pool totals.');
        $page->assertSee('hidden staff are still in the pool');
    }

    public function test_employment_status_and_type_filters_match_attendance_records(): void
    {
        $this->staff('CONFIRMED STAFF', $this->kitchen, 'confirmed');
        $this->staff('PROBATION STAFF', $this->kitchen, 'probation');
        $this->staff('OUTSOURCED STAFF', $this->kitchen, 'confirmed', Employee::TYPE_OUTSOURCING);

        $page = $this->panel()->set('employmentStatusFilter', 'probation');
        $this->assertSame(['PROBATION STAFF'], $this->visibleNames($page));

        $page->set('employmentStatusFilter', '')->set('employmentTypeFilter', 'exclude_outsourcing');
        $this->assertNotContains('OUTSOURCED STAFF', $this->visibleNames($page));
        $this->assertContains('CONFIRMED STAFF', $this->visibleNames($page));
    }

    public function test_a_pool_saved_while_filtered_still_pays_the_hidden_staff(): void
    {
        $this->staff('ALI COOK', $this->kitchen);
        $hidden = $this->staff('SITI WAITER', $this->floor);

        $this->panel()
            ->set('sectionFilter', (string) $this->kitchen->id)
            ->call('saveServiceCharge')
            ->assertHasNoErrors();

        $pool = ServiceChargePeriod::withoutGlobalScopes()->where('company_id', $this->company->id)->firstOrFail();
        $rows = collect($pool->distribution['rows']);

        $this->assertEquals(1000.0, (float) $rows[(string) $hidden->id]['net'],
            'A filtered-out name is still in the pool and still paid its share.');
        $this->assertSame([], $pool->excludedEmployeeIds());
    }

    /**
     * The PDF takes the same filters and prints only the matching rows — but
     * with the whole pool's totals, and a note saying what was left out.
     */
    public function test_the_pdf_export_takes_the_same_filters_and_keeps_pool_totals(): void
    {
        $this->staff('ALI COOK', $this->kitchen);
        $this->staff('SITI WAITER', $this->floor);

        $page = $this->panel()->call('saveServiceCharge');
        [$from, $to] = [$page->viewData('from'), $page->viewData('to')];

        $captured = null;
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()
            ->andReturnUsing(function ($view, $data) use (&$captured) {
                $captured = $data;
                $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
                $pdf->shouldReceive('setPaper')->andReturnSelf();
                $pdf->shouldReceive('stream')->andReturn(response('pdf'));
                return $pdf;
            });

        $this->get(route('hr.attendance.distribution-pdf', [
            'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'),
            'outlet' => $this->outlet->id, 'section' => $this->kitchen->id,
        ]))->assertOk();

        $names = collect($captured['serviceCharge']['rows'])->map(fn ($r) => $r['employee']->name)->all();
        $this->assertSame(['ALI COOK'], $names);
        $this->assertSame(1, $captured['scHiddenCount']);
        $this->assertSame('Kitchen', $captured['scSectionName']);
        $this->assertEquals(2000.0, (float) $captured['serviceCharge']['totals']['net'],
            'Totals stay those of the whole pool, not of the filtered rows.');
    }

    public function test_all_except_resigned_hides_only_the_resigned(): void
    {
        $this->staff('STILL HERE', $this->kitchen, 'confirmed');
        $this->staff('ON PROBATION', $this->kitchen, 'probation');
        $this->staff('NO STATUS YET', $this->kitchen, 'confirmed')->update(['employment_status' => null]);
        // Resigned during the period, so still in the pool and still paid.
        Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'section_id' => $this->kitchen->id,
            'name' => 'LEFT US', 'is_active' => true, 'join_date' => '2025-01-01',
            'employment_status' => 'resigned', 'employment_status_date' => now()->endOfMonth()->toDateString(),
            'service_points_entitlement' => 10, 'basic_salary' => 2000, 'pay_type' => 'monthly',
        ]);

        $page = $this->panel()->set('employmentStatusFilter', Employee::STATUS_EXCLUDE_RESIGNED);
        $names = $this->visibleNames($page);

        $this->assertEqualsCanonicalizing(['STILL HERE', 'ON PROBATION', 'NO STATUS YET'], $names);

        // The same rule as a query, which the grid, the employee list and
        // the PDF/Excel exports use.
        $query = Employee::query()->where('company_id', $this->company->id);
        Employee::applyEmploymentFilters($query, Employee::STATUS_EXCLUDE_RESIGNED, null);
        $this->assertEqualsCanonicalizing(['STILL HERE', 'ON PROBATION', 'NO STATUS YET'], $query->pluck('name')->all());

        $this->assertSame('All except Resigned', Employee::employmentFilterLabels(Employee::STATUS_EXCLUDE_RESIGNED, null)['status']);
    }

    public function test_the_excel_export_lists_only_the_filtered_staff_and_says_so(): void
    {
        $this->staff('ALI COOK', $this->kitchen);
        $this->staff('SITI WAITER', $this->floor);

        $page = $this->panel()->call('saveServiceCharge');
        [$from, $to] = [$page->viewData('from'), $page->viewData('to')];

        $response = $this->get(route('hr.attendance.distribution-excel', [
            'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'),
            'outlet' => $this->outlet->id, 'section' => $this->kitchen->id,
        ]))->assertOk();

        $cells = collect(\PhpOffice\PhpSpreadsheet\IOFactory::load($response->baseResponse->getFile()->getPathname())
            ->getActiveSheet()->toArray(null, false, false, true))->flatten()->filter()->implode('|');

        $this->assertStringContainsString('ALI COOK', $cells);
        $this->assertStringNotContainsString('SITI WAITER', $cells);
        $this->assertStringContainsString('1 staff not listed are still in the pool', $cells);
        $this->assertStringContainsString('LISTED STAFF TOTAL', $cells);
    }
}
