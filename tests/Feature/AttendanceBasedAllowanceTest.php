<?php

namespace Tests\Feature;

use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\ClockEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeePayComponent;
use App\Models\Outlet;
use App\Models\PayComponent;
use App\Scopes\CompanyScope;
use App\Services\Hr\CompensationSummary;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Allowances that read the attendance record.
 *
 *   MEAL ALLOWANCE — RM7.50 for every day marked with a code that counts as
 *   a working day (or with hours worked).
 *
 *   ATTENDANCE ALLOWANCE — a fixed amount, lost for the whole payroll to any
 *   MC, any Absent mark or any late clock-in.
 */
class AttendanceBasedAllowanceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Carbon $july;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Allowance Co', 'slug' => Str::slug('Allowance Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        AttendanceCode::seedDefaults($this->company->id);

        $this->july = Carbon::parse('2026-07-01');
    }

    private function employee(string $type = 'monthly', float $salary = 2000, string $join = '2025-01-01'): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Staff ' . uniqid(), 'is_active' => true,
            'join_date' => $join, 'basic_salary' => $salary, 'pay_type' => $type,
            'employment_status' => 'confirmed', 'employment_status_date' => '2025-06-01',
        ]);
    }

    private function assign(Employee $e, string $calculation, float $amount, string $name): void
    {
        $component = PayComponent::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $this->company->id, 'name' => $name,
            'kind' => 'allowance', 'calculation' => $calculation,
        ]);

        EmployeePayComponent::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $this->company->id, 'employee_id' => $e->id,
            'pay_component_id' => $component->id, 'amount' => $amount,
            'effective_from' => '2025-01-01',
        ]);
    }

    private function mark(Employee $e, string $code, int ...$days): void
    {
        $codeId = AttendanceCode::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->company->id)->where('code', $code)->value('id');

        foreach ($days as $d) {
            AttendanceRecord::create([
                'company_id' => $this->company->id, 'employee_id' => $e->id,
                'outlet_id' => $this->outlet->id,
                'work_date' => sprintf('2026-07-%02d', $d), 'attendance_code_id' => $codeId,
            ]);
        }
    }

    private function clockIn(Employee $e, int $day, int $lateMinutes, bool $waived = false, string $time = '09:00:00'): ClockEvent
    {
        $event = new ClockEvent();
        $event->company_id              = $this->company->id;
        $event->employee_id             = $e->id;
        $event->outlet_id               = $this->outlet->id;
        $event->type                    = ClockEvent::TYPE_IN;
        $event->work_date               = sprintf('2026-07-%02d', $day);
        $event->happened_at             = sprintf('2026-07-%02d %s', $day, $time);
        $event->minutes_late            = $lateMinutes;
        $event->chargeable_late_minutes = $lateMinutes;
        $event->lateness_waived_at      = $waived ? now() : null;
        $event->save();

        return $event;
    }

    private function line(Employee $e, string $name): array
    {
        $row = app(CompensationSummary::class)->forMonth(
            Employee::query()->whereKey($e->id), $this->company->id, $this->july,
        )['rows']->first();

        return $row['components']->firstWhere('name', $name);
    }

    // ── Meal allowance ────────────────────────────────────────────────────

    public function test_meal_allowance_pays_per_present_day(): void
    {
        $e = $this->employee();
        $this->assign($e, 'per_working_day', 7.50, 'Meal');
        $this->mark($e, '✓', ...range(1, 22));
        $this->mark($e, 'X', 23, 24, 25, 26);

        $line = $this->line($e, 'Meal');

        $this->assertEqualsWithDelta(165.00, $line['amount'], 0.001);
        $this->assertSame('22 days × 7.50', $line['note']);
    }

    public function test_leave_does_not_earn_meal_allowance_but_out_station_does(): void
    {
        $e = $this->employee();
        $this->assign($e, 'per_working_day', 7.50, 'Meal');
        $this->mark($e, '✓', 1, 2, 3);
        $this->mark($e, 'OS', 4);
        $this->mark($e, 'AL', 5, 6);
        $this->mark($e, 'SL', 7);
        $this->mark($e, 'ABS', 8);

        $this->assertEqualsWithDelta(30.00, $this->line($e, 'Meal')['amount'], 0.001);
    }

    public function test_a_code_ticked_as_working_day_counts(): void
    {
        $e = $this->employee();
        $this->assign($e, 'per_working_day', 7.50, 'Meal');
        AttendanceCode::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->company->id)->where('code', 'HD')
            ->update(['counts_as_working_day' => true]);
        $this->mark($e, 'HD', 1, 2);

        $this->assertEqualsWithDelta(15.00, $this->line($e, 'Meal')['amount'], 0.001);
    }

    public function test_hourly_staff_earn_it_on_days_with_hours(): void
    {
        $e = $this->employee('hourly', 10);
        $this->assign($e, 'per_working_day', 7.50, 'Meal');
        foreach ([1, 2, 3] as $d) {
            AttendanceRecord::create([
                'company_id' => $this->company->id, 'employee_id' => $e->id,
                'outlet_id' => $this->outlet->id,
                'work_date' => sprintf('2026-07-%02d', $d), 'hours' => $d === 3 ? 0 : 8,
            ]);
        }

        $this->assertEqualsWithDelta(15.00, $this->line($e, 'Meal')['amount'], 0.001);
    }

    public function test_meal_allowance_is_not_prorated_again_for_a_joiner(): void
    {
        $e = $this->employee('monthly', 3100, '2026-07-21');
        $this->assign($e, 'per_working_day', 7.50, 'Meal');
        $this->mark($e, '✓', ...range(21, 30));

        $line = $this->line($e, 'Meal');

        $this->assertEqualsWithDelta(75.00, $line['amount'], 0.001);
        $this->assertFalse($line['prorated']);
    }

    // ── Attendance allowance ──────────────────────────────────────────────

    public function test_attendance_allowance_is_paid_for_a_clean_month(): void
    {
        $e = $this->employee();
        $this->assign($e, 'attendance_bonus', 100, 'Attendance');
        $this->mark($e, '✓', ...range(1, 26));
        $this->mark($e, 'AL', 27);
        $this->clockIn($e, 2, 0);

        $line = $this->line($e, 'Attendance');

        $this->assertEqualsWithDelta(100.00, $line['amount'], 0.001);
        $this->assertNull($line['note']);
    }

    public function test_an_mc_forfeits_it(): void
    {
        $e = $this->employee();
        $this->assign($e, 'attendance_bonus', 100, 'Attendance');
        $this->mark($e, 'SL', 3);

        $line = $this->line($e, 'Attendance');

        $this->assertSame(0.0, (float) $line['amount']);
        $this->assertSame('forfeited: 1 MC', $line['note']);
    }

    public function test_a_code_named_mc_forfeits_it_too(): void
    {
        $e = $this->employee();
        $this->assign($e, 'attendance_bonus', 100, 'Attendance');
        AttendanceCode::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $this->company->id, 'code' => 'MC', 'label' => 'Medical Cert', 'color' => 'yellow',
        ]);
        $this->mark($e, 'MC', 3);

        $this->assertSame(0.0, (float) $this->line($e, 'Attendance')['amount']);
    }

    public function test_an_absence_forfeits_it(): void
    {
        $e = $this->employee();
        $this->assign($e, 'attendance_bonus', 100, 'Attendance');
        $this->mark($e, 'ABS', 9);

        $this->assertSame('forfeited: 1 absent', $this->line($e, 'Attendance')['note']);
    }

    public function test_a_late_clock_in_forfeits_it(): void
    {
        $e = $this->employee();
        $this->assign($e, 'attendance_bonus', 100, 'Attendance');
        $this->clockIn($e, 4, 12);
        $this->clockIn($e, 5, 3);

        $line = $this->line($e, 'Attendance');

        $this->assertSame(0.0, (float) $line['amount']);
        $this->assertSame('forfeited: 2 late', $line['note']);
    }

    public function test_waived_rejected_or_repeat_punches_do_not_forfeit_it(): void
    {
        $e = $this->employee();
        $this->assign($e, 'attendance_bonus', 100, 'Attendance');

        $this->clockIn($e, 4, 20, waived: true);

        $rejected = $this->clockIn($e, 5, 20);
        $rejected->status = ClockEvent::STATUS_REJECTED;
        $rejected->save();

        // On time, then a stray second tap later in the shift.
        $this->clockIn($e, 6, 0, time: '09:00:00');
        $this->clockIn($e, 6, 90, time: '10:30:00');

        $this->assertEqualsWithDelta(100.00, $this->line($e, 'Attendance')['amount'], 0.001);
    }

    public function test_it_is_prorated_for_a_joiner_when_kept(): void
    {
        $e = $this->employee('monthly', 3100, '2026-07-17');
        $this->assign($e, 'attendance_bonus', 310, 'Attendance');

        $line = $this->line($e, 'Attendance');

        $this->assertEqualsWithDelta(150.00, $line['amount'], 0.001);
        $this->assertTrue($line['prorated']);
    }

    public function test_a_forfeited_allowance_stays_out_of_gross(): void
    {
        $e = $this->employee();
        $this->assign($e, 'attendance_bonus', 100, 'Attendance');
        $this->mark($e, 'ABS', 9);

        $row = app(CompensationSummary::class)->forMonth(
            Employee::query()->whereKey($e->id), $this->company->id, $this->july,
        )['rows']->first();

        $this->assertEqualsWithDelta(0.0, $row['allowances'], 0.001);
        $this->assertEqualsWithDelta(2000.0, $row['gross'], 0.001);
    }

    public function test_attendance_calculations_cannot_be_deductions(): void
    {
        $user = \App\Models\User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($user);

        \Livewire\Livewire::test(\App\Livewire\Settings\PayComponents::class)
            ->set('name', 'Meal')
            ->set('kind', 'deduction')
            ->set('calculation', 'per_working_day')
            ->call('save')
            ->assertHasErrors(['kind']);
    }
}
