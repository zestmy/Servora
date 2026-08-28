<?php

namespace Tests\Feature;

use App\Livewire\Hr\Compensation;
use App\Livewire\Hr\PayrollRunShow;
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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Two ways a payroll run could quietly get the money wrong, and the notices
 * that make each of them visible.
 *
 * FOUND WHILE AUDITING the request to give attendance, overtime and service
 * charge their own periods. Both faults are reachable on today's code, before
 * any of that is built:
 *
 *   1. OVERTIME PAID TWICE. settleOvertime() stamps a claim on approval and
 *      skips claims another run already stamped — but CompensationSummary,
 *      which decides what the run PAYS, never looked at paid_at. Two runs
 *      whose periods overlap each paid the same hours in full. Verified: a
 *      claim stamped by an approved run came back at RM86.54 on the next.
 *
 *   2. SERVICE CHARGE SILENTLY NOT PAID. A pool is matched on both its exact
 *      dates. A company distributing by calendar month while running payroll
 *      26th–25th matches nothing, forRun() returns null, and the run pays
 *      RM0.00 with no warning anywhere. Verified: a RM6,000 pool sat unpaid.
 *
 * The guard belongs in the same release as the periods feature rather than
 * after it: today an overlap needs a deliberate period override, and the
 * moment overtime has a window of its own it becomes an ordinary action.
 */
class PayrollOvertimeAndPoolGuardsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Guard Co', 'slug' => Str::slug('Guard Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $s = StatutorySetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->fill(['epf_enabled' => false, 'socso_enabled' => false,
            'eis_enabled' => false, 'pcb_enabled' => false])->save();

        $this->employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'NUR AISYAH', 'is_active' => true, 'join_date' => '2025-01-01',
            'basic_salary' => 3000, 'pay_type' => 'monthly',
            'service_points_entitlement' => 1,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['hr.payroll', 'hr.payroll.approve', 'hr.compensation'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(['hr.payroll', 'hr.payroll.approve', 'hr.compensation']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function cycleStartsOn(int $day): void
    {
        $c = CompensationSetting::forCompany($this->company->id);
        $c->company_id = $this->company->id;
        $c->payroll_cycle_start_day = $day;
        $c->save();
    }

    private function claim(string $date, float $hours = 4): OvertimeClaim
    {
        return OvertimeClaim::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'employee_id' => $this->employee->id, 'submitted_by' => $this->user->id,
            'claim_date' => $date,
            'ot_time_start' => '18:00', 'ot_time_end' => '22:00', 'total_ot_hours' => $hours,
            'hours_taken_off' => 0, 'ot_type' => 'normal_day', 'reason' => 'Stocktake',
            'status' => 'approved', 'settlement' => OvertimeClaim::SETTLE_PAYROLL,
        ]);
    }

    private function build(string $month, ?string $from = null, ?string $to = null): PayrollRun
    {
        return app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id], Carbon::parse($month),
            $this->outlet->id, $this->user->id,
            $from ? Carbon::parse($from)->startOfDay() : null,
            $to ? Carbon::parse($to)->endOfDay() : null,
        );
    }

    private function approve(PayrollRun $run): PayrollRun
    {
        $run->update(['status' => PayrollRun::APPROVED]);
        $run->settleOvertime($this->user->id);

        return $run->fresh();
    }

    // ── The double pay ────────────────────────────────────────────────────

    public function test_a_second_run_does_not_pay_overtime_the_first_already_paid(): void
    {
        $this->claim('2026-07-10');

        $first = $this->approve($this->build('2026-07-01'));
        $this->assertEquals(4.0, (float) $first->lines->first()->ot_hours,
            'The run that actually pays the claim must still pay it.');

        // A later run whose period covers the same day — which is what an
        // independent overtime window makes an ordinary thing to do.
        $second = $this->build('2026-08-01', '2026-07-01', '2026-07-31');

        $this->assertEquals(0.0, (float) $second->lines->first()->ot_hours,
            'The same hours must never be paid by two runs.');
        $this->assertEquals(0.0, (float) $second->lines->first()->ot_amount);
    }

    public function test_a_run_still_sees_the_overtime_it_settled_itself(): void
    {
        $this->claim('2026-07-10');

        $run = $this->approve($this->build('2026-07-01'));

        // Back to draft WITHOUT releasing — the belt-and-braces case. A plain
        // regenerate must not drop the overtime this run already committed to.
        $run->update(['status' => PayrollRun::DRAFT]);

        $rebuilt = $this->build('2026-07-01');

        $this->assertEquals(4.0, (float) $rebuilt->lines->first()->ot_hours,
            'A run regenerating must keep the hours it settled itself.');
    }

    public function test_unlocking_releases_the_claim_so_the_next_run_can_pay_it(): void
    {
        $this->claim('2026-07-10');

        $run = $this->approve($this->build('2026-07-01'));
        $run->releaseOvertime();
        $run->update(['status' => PayrollRun::DRAFT]);

        $next = $this->build('2026-08-01', '2026-07-01', '2026-07-31');

        $this->assertEquals(4.0, (float) $next->lines->first()->ot_hours,
            'Released hours are unpaid again and must be payable by another run.');
    }

    /** The live screens are a view of the month, not a run, and must not filter. */
    public function test_the_compensation_screen_still_shows_overtime_after_the_run_is_approved(): void
    {
        $this->claim('2026-07-10');
        $this->approve($this->build('2026-07-01'));

        $summary = Livewire::actingAs($this->user)
            ->test(Compensation::class)
            ->set('month', '2026-07')
            ->viewData('summary');

        $this->assertEquals(4.0, (float) $summary['rows']->first()['ot_hours'],
            'Filtering the live screen would blank the overtime on the very month that paid it.');
    }

    public function test_the_run_screen_names_the_claims_another_run_paid(): void
    {
        $this->claim('2026-07-10');

        $first  = $this->approve($this->build('2026-07-01'));
        $second = $this->build('2026-08-01', '2026-07-01', '2026-07-31');

        $warnings = Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $second->uuid])
            ->viewData('warnings');

        $joined = implode(' ', $warnings);

        $this->assertStringContainsString('already paid them', $joined);
        $this->assertStringContainsString('NUR AISYAH', $joined);
        $this->assertStringContainsString($first->reference, $joined,
            'The earlier run has to be named — "an earlier run" is not something anybody can go and look at.');
    }

    // ── The unpaid pool ───────────────────────────────────────────────────

    public function test_a_pool_that_does_not_fit_the_run_is_reported(): void
    {
        // Payroll on 26th–25th; the pool saved for the calendar month.
        $this->cycleStartsOn(26);

        ServiceChargePeriod::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_from' => '2026-08-01', 'period_to' => '2026-08-31',
            'amount' => 6000, 'retention_percent' => 0, 'mc_percent' => 0, 'abs_percent' => 0,
        ]);

        $run = $this->build('2026-08-01');

        $this->assertEquals(0.0, (float) $run->total_service_charge,
            'Precondition: the exact-date match finds nothing, which is the fault being reported.');

        $warnings = Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->viewData('warnings');

        $joined = implode(' ', $warnings);

        $this->assertStringContainsString('No service charge was paid by this run', $joined);
        $this->assertStringContainsString('1 Aug – 31 Aug 2026', $joined,
            'The dates that DO have a pool are the actionable part.');
    }

    public function test_a_matching_pool_says_nothing(): void
    {
        ServiceChargePeriod::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_from' => '2026-08-01', 'period_to' => '2026-08-31',
            'amount' => 6000, 'retention_percent' => 0, 'mc_percent' => 0, 'abs_percent' => 0,
        ]);

        $run = $this->build('2026-08-01');

        $this->assertGreaterThan(0, (float) $run->total_service_charge);

        $warnings = Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->viewData('warnings');

        $this->assertStringNotContainsString('No service charge was paid', implode(' ', $warnings));
    }

    /**
     * The rule that keeps this from becoming a warning nobody can clear: most
     * companies do not levy a service charge at all, and every run of theirs
     * would otherwise carry it forever.
     */
    public function test_a_company_with_no_pools_is_never_warned(): void
    {
        $run = $this->build('2026-08-01');

        $warnings = Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->viewData('warnings');

        $this->assertStringNotContainsString('service charge', implode(' ', $warnings));
    }
}
