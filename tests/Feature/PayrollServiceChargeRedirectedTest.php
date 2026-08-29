<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\PayrollRunLine;
use App\Models\ServiceChargePeriod;
use App\Services\Payroll\PayrollRunBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A redirected employee's pool share has to reach the run that PAYS them.
 *
 * Somebody posted to HQ and paid from KLCC's pool is on the HQ payroll run —
 * runs are scoped by home outlet_id, which is right, because that is where
 * their salary is administered. But their service charge lives in KLCC's
 * distribution.
 *
 * That leaves a gap between two per-outlet runs. The KLCC run holds their pool
 * row and correctly drops it (they are not on that run); the HQ run holds them
 * and asks HQ's pool, which by definition does not pay them. Neither run pays
 * the share, and unlike the missing payout slip this is money on a payslip
 * rather than a page nobody printed.
 *
 * A company-wide run is unaffected: forRun() merges every pool, so the row and
 * the person meet.
 */
class PayrollServiceChargeRedirectedTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $klcc;
    private Outlet $hq;
    private Employee $atKlcc;
    private Employee $redirected;
    private Carbon $from;
    private Carbon $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Redirect Payroll Co', 'slug' => Str::slug('Redirect Payroll Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->klcc = $this->outlet('KLCC', 'KLCC');
        $this->hq   = $this->outlet('HQ', 'HQ');

        $this->from = Carbon::parse('2026-07-01');
        $this->to   = Carbon::parse('2026-07-25');

        $this->atKlcc     = $this->employee('AISYAH', $this->klcc, null);
        $this->redirected = $this->employee('MOHD AFFANDY', $this->hq, $this->klcc);

        // One pool, at KLCC, shared by the two of them: 2 points, RM2,000.
        ServiceChargePeriod::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->klcc->id,
            'period_from' => $this->from->toDateString(),
            'period_to'   => $this->to->toDateString(),
            'amount' => 2000, 'retention_percent' => 0,
        ]);
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    private function employee(string $name, Outlet $posted, ?Outlet $paidFrom): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $posted->id,
            'service_charge_outlet_id' => $paidFrom?->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2025-01-01',
            'basic_salary' => 2000, 'service_points_entitlement' => 1.0,
        ]);
    }

    private function scOnRun(?Outlet $outlet): array
    {
        $run = app(PayrollRunBuilder::class)->generate(
            $this->company->id,
            [$this->klcc->id, $this->hq->id],
            Carbon::parse('2026-07-01'),
            $outlet?->id,
            null,
            $this->from,
            $this->to,
        );

        return PayrollRunLine::withoutGlobalScopes()
            ->where('payroll_run_id', $run->id)
            ->get()
            ->mapWithKeys(fn ($l) => [(int) $l->employee_id => (float) $l->service_charge])
            ->all();
    }

    public function test_the_run_that_pays_them_carries_their_pool_share(): void
    {
        // The whole point: they are on HQ's run, and HQ's run must still find
        // the KLCC share. RM2,000 over 2 points is RM1,000 each.
        $hq = $this->scOnRun($this->hq);

        $this->assertArrayHasKey($this->redirected->id, $hq, 'They must be on the run that administers their salary.');
        $this->assertSame(1000.0, $hq[$this->redirected->id]);
    }

    public function test_the_pool_outlets_own_run_pays_its_own_staff(): void
    {
        $klcc = $this->scOnRun($this->klcc);

        $this->assertSame(1000.0, $klcc[$this->atKlcc->id]);
    }

    public function test_the_share_is_paid_once_across_the_two_runs(): void
    {
        /*
         * The failure mode on the other side of this fix. Reaching for the
         * KLCC pool from the HQ run must not also leave the row on the KLCC
         * run, or one share is paid twice — which is worse than not paying it,
         * because nothing downstream reconciles a payslip.
         */
        $klcc = $this->scOnRun($this->klcc);
        $hq   = $this->scOnRun($this->hq);

        $paid = ($klcc[$this->redirected->id] ?? 0.0) + ($hq[$this->redirected->id] ?? 0.0);

        $this->assertSame(1000.0, $paid);
    }

    /**
     * THEY ARE NOT ON THE OTHER BRANCH'S RUN AT ALL.
     *
     * The share-paid-once test above would still pass if their line existed on
     * the KLCC run carrying RM0 of service charge — and it would carry their
     * BASIC SALARY, so somebody posted to HQ would appear on KLCC's payroll
     * and KLCC's payslips because of where their service charge comes from.
     * Runs are scoped by home outlet, and this is the assertion that says the
     * pool must never drag a person across.
     */
    public function test_a_redirected_employee_has_no_line_on_the_pool_outlets_run(): void
    {
        $klcc = $this->scOnRun($this->klcc);
        $hq   = $this->scOnRun($this->hq);

        $this->assertArrayNotHasKey($this->redirected->id, $klcc,
            'Paid from KLCC, posted to HQ — KLCC pays their share, HQ pays them.');
        $this->assertArrayHasKey($this->redirected->id, $hq);

        // And the reverse, so this is a statement about home outlet rather
        // than about one employee: KLCC's own staff are not on the HQ run.
        $this->assertArrayHasKey($this->atKlcc->id, $klcc);
        $this->assertArrayNotHasKey($this->atKlcc->id, $hq);
    }

    public function test_a_company_wide_run_still_pays_everyone_once(): void
    {
        $all = $this->scOnRun(null);

        $this->assertSame(1000.0, $all[$this->redirected->id]);
        $this->assertSame(1000.0, $all[$this->atKlcc->id]);
    }

    public function test_the_rate_is_not_moved_by_where_the_run_is_scoped(): void
    {
        // RM/point is the pool over EVERYBODY's points. If a per-outlet run
        // recomputed it over the people on that run alone, the redirected
        // person leaving KLCC's divisor would reprice AISYAH.
        $klcc = $this->scOnRun($this->klcc);
        $hq   = $this->scOnRun($this->hq);

        $this->assertSame(1000.0, $klcc[$this->atKlcc->id]);
        $this->assertSame(1000.0, $hq[$this->redirected->id]);
    }
}
