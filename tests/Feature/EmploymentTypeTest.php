<?php

namespace Tests\Feature;

use App\Livewire\Hr\AttendanceRecords;
use App\Livewire\Hr\Employees;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Employment TYPE (Local Malaysian / Direct Hire Foreign Worker / Outsourcing
 * Foreign Worker), split out of employment STATUS.
 *
 * REPORTED AS: a resigned outsourcing worker cannot be filtered properly.
 * Outsourcing was a status, so a leaver was either "Outsourcing" (still on
 * every active list) or "Resigned" (gone from every outsourced one). The two
 * are separate facts now, and every list and download filters on both.
 */
class EmploymentTypeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Type Co', 'slug' => Str::slug('Type Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $abilities = ['hr.view', 'hr.employees.manage', 'hr.employment', 'hr.attendance'];
        foreach ($abilities as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo($abilities);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // The four people every test sorts.
        $this->employee('AGENCY LEFT', 'foreign_outsourcing', 'resigned', '2026-08-15');
        $this->employee('AGENCY ACTIVE', 'foreign_outsourcing', 'confirmed', '2025-06-01');
        $this->employee('LOCAL LEFT', 'local', 'resigned', '2026-08-15');
        $this->employee('FOREIGN DIRECT', 'foreign_direct', 'confirmed', '2025-06-01');
    }

    private function employee(string $name, ?string $type, ?string $status, ?string $date = null): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2025-01-01',
            'employment_type' => $type, 'employment_status' => $status, 'employment_status_date' => $date,
        ]);
    }

    /** @return array<int, string> */
    private function names(iterable $employees): array
    {
        return collect($employees)->pluck('name')->sort()->values()->all();
    }

    /** @return array<int, string> */
    private function filtered(?string $status, ?string $type): array
    {
        $query = Employee::query();
        Employee::applyEmploymentFilters($query, $status, $type);

        return $this->names($query->get());
    }

    // ── The reported case ─────────────────────────────────────────────────

    public function test_a_resigned_outsourced_worker_can_be_found(): void
    {
        $this->assertSame(['AGENCY LEFT'], $this->filtered('resigned', 'foreign_outsourcing'));
    }

    public function test_the_type_filters(): void
    {
        $this->assertSame(['AGENCY ACTIVE', 'AGENCY LEFT'], $this->filtered(null, 'foreign_outsourcing'));
        $this->assertSame(['LOCAL LEFT'], $this->filtered(null, 'local'));
        $this->assertSame(['FOREIGN DIRECT'], $this->filtered(null, 'foreign_direct'));
    }

    public function test_exclude_outsourcing_keeps_staff_with_no_type(): void
    {
        $this->employee('UNRECORDED', null, null);

        $this->assertSame(['FOREIGN DIRECT', 'LOCAL LEFT', 'UNRECORDED'], $this->filtered(null, 'exclude_outsourcing'));
        $this->assertSame(['UNRECORDED'], $this->filtered(null, 'none'));
    }

    /** Old bookmarks and saved payroll runs still say "outsourcing" as a status. */
    public function test_the_old_status_values_still_filter(): void
    {
        $this->assertSame(['AGENCY ACTIVE', 'AGENCY LEFT'], $this->filtered('outsourcing', null));
        $this->assertSame(['FOREIGN DIRECT', 'LOCAL LEFT'], $this->filtered('exclude_outsourcing', null));
    }

    public function test_writing_outsourcing_as_a_status_moves_it_to_the_type(): void
    {
        $e = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'OLD IMPORT', 'is_active' => true, 'employment_status' => 'outsourcing',
        ]);

        $this->assertNull($e->fresh()->employment_status);
        $this->assertSame('foreign_outsourcing', $e->fresh()->employment_type);
        $this->assertTrue($e->fresh()->isOutsourced());
    }

    // ── Every screen and download ─────────────────────────────────────────

    public function test_the_employees_list_filters_on_both(): void
    {
        $c = Livewire::actingAs($this->user)->test(Employees::class, ['outlet' => 'all', 'status' => 'all'])
            ->set('employmentStatusFilter', 'resigned')
            ->set('employmentTypeFilter', 'foreign_outsourcing');

        $this->assertSame(['AGENCY LEFT'], $this->names($c->viewData('employees')->getCollection()));
    }

    public function test_an_old_employees_link_lands_on_the_type_filter(): void
    {
        $c = Livewire::actingAs($this->user)->test(Employees::class, [
            'outlet' => 'all', 'status' => 'all', 'employment' => 'outsourcing',
        ]);

        $this->assertSame('', $c->get('employmentStatusFilter'));
        $this->assertSame('foreign_outsourcing', $c->get('employmentTypeFilter'));
    }

    public function test_the_attendance_grid_filters_on_both(): void
    {
        $c = Livewire::actingAs($this->user)->test(AttendanceRecords::class)
            ->set('outletFilter', (string) $this->outlet->id)
            ->set('employmentStatusFilter', 'resigned')
            ->set('employmentTypeFilter', 'foreign_outsourcing');

        $this->assertSame(['AGENCY LEFT'], $this->names($c->viewData('employees')));
    }

    public function test_the_employee_exports_filter_on_both(): void
    {
        $captured = null;
        Event::listen('composing: pdf.employees', function ($v) use (&$captured) {
            $captured = $v->getData();
        });

        $this->actingAs($this->user)->get(route('hr.employees.export-pdf', [
            'employment_status' => 'resigned', 'employment_type' => 'foreign_outsourcing',
        ]))->assertOk();

        $this->assertSame(['AGENCY LEFT'], $this->names($captured['employees']));
        $this->assertContains('Type: Outsourcing Foreign Worker', $captured['filters']);

        $this->actingAs($this->user)->get(route('hr.employees.export-excel', [
            'employment_status' => 'resigned', 'employment_type' => 'foreign_outsourcing',
        ]))->assertOk();
    }

    public function test_the_attendance_export_filters_on_both(): void
    {
        $captured = null;
        Event::listen('composing: pdf.attendance', function ($v) use (&$captured) {
            $captured = $v->getData();
        });

        $this->actingAs($this->user)->get(route('hr.attendance.export-pdf', [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'outlet' => $this->outlet->id,
            'employment_status' => 'resigned', 'employment_type' => 'foreign_outsourcing',
        ]))->assertOk();

        $this->assertSame(['AGENCY LEFT'], $this->names($captured['employees']));
        $this->assertSame('Resigned · Outsourcing Foreign Worker', $captured['employmentLabel']);
    }

    public function test_payroll_segments_read_the_type(): void
    {
        $outsourced = Employee::query();
        PayrollRun::applyEmploymentStatus($outsourced, 'outsourcing');
        $this->assertSame(['AGENCY ACTIVE', 'AGENCY LEFT'], $this->names($outsourced->get()));

        $own = Employee::query();
        PayrollRun::applyEmploymentStatus($own, PayrollRun::SEGMENT_EXCLUDE_OUTSOURCING);
        $this->assertSame(['FOREIGN DIRECT', 'LOCAL LEFT'], $this->names($own->get()));
    }

    // ── The form ──────────────────────────────────────────────────────────

    public function test_passport_and_permit_follow_the_type(): void
    {
        $html = $this->actingAs($this->user)
            ->get(route('hr.employees.edit', ['id' => Employee::where('name', 'LOCAL LEFT')->value('id')]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("\$wire.f_employment_type !== 'local'", $html);
    }
}
