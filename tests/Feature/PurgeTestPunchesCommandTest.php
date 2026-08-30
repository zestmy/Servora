<?php

namespace Tests\Feature;

use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\ClockEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `clock:purge-test-punches` — the one path in the product that destroys a
 * clock event rather than soft-deleting it.
 *
 * Almost every test here is about it NOT deleting: the wrong person, the
 * wrong day, somebody else's attendance, or anything at all without the flag
 * and the typed confirmation. A command that over-deletes attendance history
 * fails in a way nobody notices until payroll, so the guards are the feature.
 */
class PurgeTestPunchesCommandTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $tester;
    private Employee $colleague;
    private AttendanceCode $present;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name'      => 'Purge Co',
            'slug'      => Str::slug('Purge Co') . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Bangsar', 'code' => 'BSR', 'is_active' => true,
        ]);

        $this->present = AttendanceCode::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'code'       => 'P',
            'label'      => 'Present',
            'system_key' => 'present',
        ]);

        $this->tester = $this->employee('Muhammad Taufiq Bin Abdul Latif', 'T001');
        $this->colleague = $this->employee('Siti Aminah Binti Osman', 'T002');
    }

    private function employee(string $name, string $staffId): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => $name,
            'staff_id'   => $staffId,
            'email'      => Str::slug($staffId) . uniqid() . '@example.test',
            'is_active'  => true,
        ]);
    }

    private function punch(Employee $employee, string $date, string $type = 'in'): ClockEvent
    {
        return ClockEvent::withoutGlobalScopes()->create([
            'company_id'  => $this->company->id,
            'outlet_id'   => $this->outlet->id,
            'employee_id' => $employee->id,
            'type'        => $type,
            'source'      => ClockEvent::SOURCE_BYOD,
            'work_date'   => $date,
            'happened_at' => $date . ' 08:00:00',
            'status'      => 'approved',
        ]);
    }

    private function markPresent(Employee $employee, string $date): AttendanceRecord
    {
        return AttendanceRecord::withoutGlobalScopes()->create([
            'company_id'         => $this->company->id,
            'outlet_id'          => $this->outlet->id,
            'employee_id'        => $employee->id,
            'work_date'          => $date,
            'attendance_code_id' => $this->present->id,
        ]);
    }

    /** Portable across SQLite and MySQL, which store a date column differently. */
    private function hasDay(Employee $employee, string $date): bool
    {
        return AttendanceRecord::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $date)
            ->exists();
    }

    private function punchCount(Employee $employee): int
    {
        return ClockEvent::withoutGlobalScopes()->withTrashed()
            ->where('employee_id', $employee->id)->count();
    }

    public function test_without_execute_it_reports_and_deletes_nothing(): void
    {
        $this->punch($this->tester, '2026-08-20');
        $this->punch($this->tester, '2026-08-21');

        $this->artisan('clock:purge-test-punches', ['employee' => ['T001']])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(2, $this->punchCount($this->tester));
    }

    public function test_the_confirmation_is_the_count_typed_back(): void
    {
        $this->punch($this->tester, '2026-08-20');
        $this->punch($this->tester, '2026-08-21');

        // A plausible wrong answer, which is what a y/n prompt would have
        // accepted without reading anything.
        $this->artisan('clock:purge-test-punches', ['employee' => ['T001'], '--execute' => true])
            ->expectsQuestion('Type the number of punches to delete (2) to confirm', 'yes')
            ->assertFailed();

        $this->assertSame(2, $this->punchCount($this->tester));
    }

    public function test_it_deletes_only_the_named_employees_punches(): void
    {
        $this->punch($this->tester, '2026-08-20');
        $this->punch($this->tester, '2026-08-20', 'out');
        $this->punch($this->colleague, '2026-08-20');

        $this->artisan('clock:purge-test-punches', ['employee' => ['T001'], '--execute' => true])
            ->expectsQuestion('Type the number of punches to delete (2) to confirm', '2')
            ->assertSuccessful();

        $this->assertSame(0, $this->punchCount($this->tester));
        $this->assertSame(1, $this->punchCount($this->colleague), 'A colleague must not be caught by this.');
    }

    public function test_punches_already_soft_deleted_are_destroyed_too(): void
    {
        $live = $this->punch($this->tester, '2026-08-20');
        $gone = $this->punch($this->tester, '2026-08-21');
        $gone->delete();

        $this->assertSame(2, $this->punchCount($this->tester));

        $this->artisan('clock:purge-test-punches', ['employee' => ['T001'], '--execute' => true])
            ->expectsQuestion('Type the number of punches to delete (2) to confirm', '2')
            ->assertSuccessful();

        $this->assertSame(0, $this->punchCount($this->tester));
        $this->assertDatabaseMissing('clock_events', ['id' => $live->id]);
        $this->assertDatabaseMissing('clock_events', ['id' => $gone->id]);
    }

    public function test_a_date_window_is_respected(): void
    {
        $this->punch($this->tester, '2026-08-01');
        $this->punch($this->tester, '2026-08-20');
        $this->punch($this->tester, '2026-08-21');

        $this->artisan('clock:purge-test-punches', [
            'employee'  => ['T001'],
            '--from'    => '2026-08-15',
            '--execute' => true,
        ])
            ->expectsQuestion('Type the number of punches to delete (2) to confirm', '2')
            ->assertSuccessful();

        $this->assertSame(1, $this->punchCount($this->tester), 'The punch before the window survives.');
    }

    public function test_an_ambiguous_name_is_refused_rather_than_guessed(): void
    {
        // The reason this matters here: "Muhammad" is a prefix, not a name.
        $this->employee('Muhammad Firdaus Bin Yusof', 'T003');
        $this->punch($this->tester, '2026-08-20');

        $this->artisan('clock:purge-test-punches', ['employee' => ['Muhammad'], '--execute' => true])
            ->expectsOutputToContain('matches 2 employees')
            ->assertFailed();

        $this->assertSame(1, $this->punchCount($this->tester));
    }

    public function test_attendance_days_are_left_alone_by_default(): void
    {
        $this->punch($this->tester, '2026-08-20');
        $this->markPresent($this->tester, '2026-08-20');

        $this->artisan('clock:purge-test-punches', ['employee' => ['T001'], '--execute' => true])
            ->expectsQuestion('Type the number of punches to delete (1) to confirm', '1')
            ->assertSuccessful();

        $this->assertSame(0, $this->punchCount($this->tester));
        $this->assertDatabaseCount('attendance_records', 1);
    }

    public function test_with_attendance_removes_the_days_those_punches_marked(): void
    {
        $this->punch($this->tester, '2026-08-20');
        $this->markPresent($this->tester, '2026-08-20');

        // A day with no punch behind it — typed on the grid by a manager, and
        // none of this command's business.
        $this->markPresent($this->tester, '2026-08-25');

        // And a colleague's day, on the same date as a purged punch.
        $this->markPresent($this->colleague, '2026-08-20');

        $this->artisan('clock:purge-test-punches', [
            'employee'          => ['T001'],
            '--with-attendance' => true,
            '--execute'         => true,
        ])
            ->expectsQuestion('Type the number of punches to delete (1) to confirm', '1')
            ->assertSuccessful();

        $this->assertFalse($this->hasDay($this->tester, '2026-08-20'), 'The punched day goes.');
        $this->assertTrue($this->hasDay($this->tester, '2026-08-25'), 'A day typed on the grid stays.');
        $this->assertTrue($this->hasDay($this->colleague, '2026-08-20'), 'A colleague on the same date stays.');
    }

    public function test_an_hours_row_is_never_removed_even_on_a_purged_day(): void
    {
        $this->punch($this->tester, '2026-08-20');

        // Hours are entered by a person, never by a punch — see markPresent().
        AttendanceRecord::withoutGlobalScopes()->create([
            'company_id'  => $this->company->id,
            'outlet_id'   => $this->outlet->id,
            'employee_id' => $this->tester->id,
            'work_date'   => '2026-08-20',
            'hours'       => 7.5,
        ]);

        $this->artisan('clock:purge-test-punches', [
            'employee'          => ['T001'],
            '--with-attendance' => true,
            '--execute'         => true,
        ])
            ->expectsQuestion('Type the number of punches to delete (1) to confirm', '1')
            ->assertSuccessful();

        $this->assertTrue($this->hasDay($this->tester, '2026-08-20'));
    }

    public function test_an_unknown_name_fails_without_touching_anything(): void
    {
        $this->punch($this->tester, '2026-08-20');

        $this->artisan('clock:purge-test-punches', [
            'employee'  => ['T001', 'Nobody At All'],
            '--execute' => true,
        ])->assertFailed();

        $this->assertSame(1, $this->punchCount($this->tester));
    }
}
