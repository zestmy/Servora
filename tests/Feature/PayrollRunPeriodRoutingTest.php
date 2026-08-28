<?php

namespace Tests\Feature;

use App\Livewire\Hr\PayrollRunShow;
use App\Models\AttendanceRecord;
use App\Models\CompensationSetting;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\OvertimeClaim;
use App\Models\PayrollRun;
use App\Models\ServiceChargePeriod;
use App\Models\StatutorySetting;
use App\Models\User;
use App\Services\Payroll\PayrollRunBuilder;
use App\Services\Payroll\RunPeriods;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 3: each input is counted over its own dates, and moving one moves
 * nothing else.
 *
 * The regression risk here is not in the new paths — it is in the old ones
 * staying identical. A run that names no component period must produce what it
 * produced before any of this existed, and the four things the master period
 * decides must be unreachable from a component window. Those are the tests
 * that matter most; the ones proving the feature works are the easy half.
 *
 * The company here runs a 26th–25th cycle, because that is the shape every one
 * of these faults was reported against: an August run covering 26 Jul – 25
 * Aug, beside a service charge pool saved for 1–31 August.
 */
class PayrollRunPeriodRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private Employee $monthly;
    private Employee $hourly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Routing Co', 'slug' => Str::slug('Routing Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $s = StatutorySetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->fill(['epf_enabled' => false, 'socso_enabled' => false,
            'eis_enabled' => false, 'pcb_enabled' => false])->save();

        $c = CompensationSetting::forCompany($this->company->id);
        $c->company_id = $this->company->id;
        $c->payroll_cycle_start_day = 26;
        $c->save();

        $this->monthly = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'MONTHLY STAFF', 'is_active' => true, 'join_date' => '2025-01-01',
            'basic_salary' => 3000, 'pay_type' => 'monthly',
            'service_points_entitlement' => 1,
        ]);

        $this->hourly = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'HOURLY STAFF', 'is_active' => true, 'join_date' => '2025-01-01',
            'basic_salary' => 10, 'pay_type' => 'hourly',
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['hr.payroll', 'hr.payroll.approve'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(['hr.payroll', 'hr.payroll.approve']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @param array<string, array{0: Carbon, 1: Carbon}>|null $periods */
    private function build(?array $periods = null, string $month = '2026-08-01'): PayrollRun
    {
        return app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id], Carbon::parse($month),
            $this->outlet->id, $this->user->id, null, null, null, null, $periods,
        );
    }

    private function claim(string $date, float $hours = 4): OvertimeClaim
    {
        return OvertimeClaim::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'employee_id' => $this->monthly->id, 'submitted_by' => $this->user->id,
            'claim_date' => $date,
            'ot_time_start' => '18:00', 'ot_time_end' => '22:00', 'total_ot_hours' => $hours,
            'hours_taken_off' => 0, 'ot_type' => 'normal_day', 'reason' => 'Stocktake',
            'status' => 'approved', 'settlement' => OvertimeClaim::SETTLE_PAYROLL,
        ]);
    }

    private function worked(string $date, float $hours = 8): void
    {
        AttendanceRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'employee_id' => $this->hourly->id, 'work_date' => $date, 'hours' => $hours,
        ]);
    }

    private function pool(string $from, string $to, float $amount = 6000): ServiceChargePeriod
    {
        return ServiceChargePeriod::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_from' => $from, 'period_to' => $to,
            'amount' => $amount, 'retention_percent' => 0, 'mc_percent' => 0, 'abs_percent' => 0,
        ]);
    }

    private function line(PayrollRun $run, Employee $employee)
    {
        return $run->lines->firstWhere('employee_id', $employee->id);
    }

    // ── Nothing moves when nothing is set ─────────────────────────────────

    public function test_a_run_naming_no_component_period_is_unchanged(): void
    {
        $this->claim('2026-07-30');          // inside the cycle
        $this->claim('2026-08-28');          // next cycle's
        $this->worked('2026-07-30');
        $this->worked('2026-08-28');
        $this->pool('2026-07-26', '2026-08-25');

        $run = $this->build();

        $this->assertSame('2026-07-26', $run->period_start->toDateString());
        $this->assertSame('2026-08-25', $run->period_end->toDateString());

        // Everything counted over the run's own period, exactly as before.
        $this->assertEquals(4.0, (float) $this->line($run, $this->monthly)->ot_hours);
        $this->assertEquals(80.0, (float) $this->line($run, $this->hourly)->basic);
        $this->assertGreaterThan(0, (float) $run->total_service_charge);

        $this->assertFalse($run->hasComponentPeriods());
    }

    // ── Each period moves only its own figure ─────────────────────────────

    public function test_the_service_charge_period_reaches_a_pool_the_run_could_not(): void
    {
        // The reported shape: payroll 26th–25th, pool on the calendar month.
        $this->pool('2026-08-01', '2026-08-31');

        $this->assertEquals(0.0, (float) $this->build()->total_service_charge,
            'Precondition: the run’s own dates match no pool, which is the fault.');

        $run = $this->build([
            RunPeriods::SERVICE_CHARGE => [Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')],
        ]);

        $this->assertGreaterThan(0, (float) $run->total_service_charge,
            'Pointed at the pool’s own dates, the run pays it.');
        $this->assertSame('2026-08-01', $run->service_charge_from->toDateString());

        // And the run's own period is untouched by that.
        $this->assertSame('2026-07-26', $run->period_start->toDateString());
    }

    public function test_the_overtime_period_pays_last_months_claims(): void
    {
        $this->claim('2026-06-15');

        $this->assertEquals(0.0, (float) $this->line($this->build(), $this->monthly)->ot_hours);

        $run = $this->build([
            RunPeriods::OVERTIME => [Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')],
        ]);

        $this->assertEquals(4.0, (float) $this->line($run, $this->monthly)->ot_hours);
    }

    public function test_the_attendance_period_prices_hourly_staff_over_its_own_dates(): void
    {
        $this->worked('2026-08-27');   // outside the run, inside August
        $this->worked('2026-08-28');

        $this->assertEquals(0.0, (float) $this->line($this->build(), $this->hourly)->basic);

        $run = $this->build([
            RunPeriods::ATTENDANCE => [Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')],
        ]);

        $this->assertEquals(160.0, (float) $this->line($run, $this->hourly)->basic);
    }

    public function test_moving_one_period_leaves_the_others_where_they_were(): void
    {
        $this->claim('2026-07-30');       // in the run's own period
        $this->worked('2026-07-30');
        $this->pool('2026-07-26', '2026-08-25');

        $run = $this->build([
            RunPeriods::OVERTIME => [Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')],
        ]);

        // Overtime followed its window and found nothing in June...
        $this->assertEquals(0.0, (float) $this->line($run, $this->monthly)->ot_hours);
        // ...while attendance and service charge stayed on the run's own.
        $this->assertEquals(80.0, (float) $this->line($run, $this->hourly)->basic);
        $this->assertGreaterThan(0, (float) $run->total_service_charge);
    }

    // ── The invariants ────────────────────────────────────────────────────

    /**
     * The most expensive mistake available in this work: a short attendance
     * window must not re-price a monthly salary. Proration is employment dates
     * against the WAGE PERIOD, and no component window belongs in it.
     */
    public function test_no_component_period_can_reach_a_monthly_salary(): void
    {
        $full = (float) $this->line($this->build(), $this->monthly)->basic;

        $run = $this->build([
            RunPeriods::ATTENDANCE => [Carbon::parse('2026-08-01'), Carbon::parse('2026-08-03')],
            RunPeriods::OVERTIME   => [Carbon::parse('2026-08-01'), Carbon::parse('2026-08-03')],
        ]);

        $this->assertSame(3000.0, $full);
        $this->assertSame($full, (float) $this->line($run, $this->monthly)->basic);
        $this->assertNull($this->line($run, $this->monthly)->period_days,
            'A three-day attendance window must not make the month look incomplete.');
    }

    /** A leaver is on the run by employment dates, never by a timesheet date. */
    public function test_no_component_period_changes_who_is_on_the_run(): void
    {
        $leaver = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'LEAVER', 'is_active' => false, 'join_date' => '2025-01-01',
            'basic_salary' => 2000, 'pay_type' => 'monthly',
            'employment_status' => 'resigned', 'employment_status_date' => '2026-08-10',
        ]);

        $run = $this->build([
            RunPeriods::ATTENDANCE => [Carbon::parse('2026-08-20'), Carbon::parse('2026-08-25')],
        ]);

        $this->assertNotNull($this->line($run, $leaver),
            'They left on the 10th and are owed for the days they worked — an attendance '
            . 'window starting after that must not drop them off the payroll.');
    }

    // ── Settlement follows the money ──────────────────────────────────────

    public function test_approval_stamps_the_claims_the_run_actually_paid(): void
    {
        $june = $this->claim('2026-06-15');
        $july = $this->claim('2026-07-30');

        $run = $this->build([
            RunPeriods::OVERTIME => [Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')],
        ]);

        $this->assertEquals(4.0, (float) $this->line($run, $this->monthly)->ot_hours);

        $run->update(['status' => PayrollRun::APPROVED]);
        $run->settleOvertime($this->user->id);

        $this->assertNotNull($june->fresh()->paid_at,
            'The hours the run paid must be stamped, or the next run pays them again.');
        $this->assertNull($july->fresh()->paid_at,
            'Hours the run did not pay must not be stamped, or they are gone with nothing paid for them.');
    }

    /**
     * The boundary the two windows could disagree on. The paying query
     * compares date strings; settlement has to compare the same way, or a
     * claim on the last day is paid and never stamped — free for the next run
     * to pay all over again.
     */
    public function test_a_claim_on_the_last_day_of_the_window_is_paid_and_stamped(): void
    {
        $last = $this->claim('2026-06-30');

        $run = $this->build([
            RunPeriods::OVERTIME => [Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')],
        ]);

        $this->assertEquals(4.0, (float) $this->line($run, $this->monthly)->ot_hours);

        $run->update(['status' => PayrollRun::APPROVED]);
        $run->settleOvertime($this->user->id);

        $this->assertNotNull($last->fresh()->paid_at);
    }

    public function test_the_guard_still_holds_across_two_overtime_windows(): void
    {
        $this->claim('2026-06-15');

        $first = $this->build([
            RunPeriods::OVERTIME => [Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')],
        ]);
        $first->update(['status' => PayrollRun::APPROVED]);
        $first->settleOvertime($this->user->id);

        // A different month's run reaching back over the same window — which
        // is exactly what an independent overtime period makes easy.
        $second = $this->build([
            RunPeriods::OVERTIME => [Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')],
        ], month: '2026-09-01');

        $this->assertEquals(0.0, (float) $this->line($second, $this->monthly)->ot_hours,
            'The same hours must never be paid by two runs, however the windows are set.');
    }

    // ── Regenerating ──────────────────────────────────────────────────────

    public function test_a_regenerate_keeps_the_periods_and_the_figures(): void
    {
        $this->claim('2026-06-15');

        $run = $this->build([
            RunPeriods::OVERTIME => [Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')],
        ]);

        // No periods passed: the caller is not saying, so the draft's own stand.
        $rebuilt = $this->build();

        $this->assertSame('2026-06-01', $rebuilt->overtime_from->toDateString());
        $this->assertEquals(4.0, (float) $this->line($rebuilt, $this->monthly)->ot_hours,
            'A regenerate must not silently snap the run back to its own period.');
    }

    public function test_an_empty_array_clears_the_periods(): void
    {
        $this->claim('2026-06-15');

        $this->build([RunPeriods::OVERTIME => [Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')]]);

        $cleared = $this->build([]);

        $this->assertNull($cleared->overtime_from);
        $this->assertEquals(0.0, (float) $this->line($cleared, $this->monthly)->ot_hours);
    }

    // ── What the screen says ──────────────────────────────────────────────

    public function test_the_pool_warning_names_the_service_charge_dates(): void
    {
        $this->pool('2026-08-01', '2026-08-31');

        $run = $this->build([
            RunPeriods::SERVICE_CHARGE => [Carbon::parse('2026-08-02'), Carbon::parse('2026-08-30')],
        ]);

        $warnings = implode(' ', Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->viewData('warnings'));

        $this->assertStringContainsString('2 Aug – 30 Aug 2026', $warnings,
            'The dates that failed to match are the service charge window, not the run’s own.');
        $this->assertStringContainsString('1 Aug – 31 Aug 2026', $warnings);
    }
}
