<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\ServiceChargePeriod;
use App\Services\Hr\ServiceChargeDistribution;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A payroll run pays the service charge that was distributed.
 *
 * REPORTED AS: payroll did not include the calculated service charge. On
 * production, a company-wide run for 1–25 July sat beside two saved pools for
 * exactly that period — KLCC's RM 34,202.48 and IOI's RM 15,539.52 — and paid
 * out nothing at all.
 *
 * The builder asked forPeriod(), which matches ONE pool on outlet_id. A run
 * with no outlet looked for a pool with no outlet, and nobody saves one of
 * those: the attendance grid keys a pool on the outlet being viewed, so a
 * company with two branches has two pools and a company-wide run found
 * neither. It failed silently, which on an F&B payroll is the largest line
 * after basic going missing without a message.
 *
 * POOLS ARE NEVER ADDED TOGETHER. Each is distributed within itself and the
 * rows are merged — RM/point belongs to one pool, and a combined divisor would
 * move money between branches, paying KLCC's staff out of IOI's takings.
 */
class PayrollServiceChargeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $klcc;
    private Outlet $ioi;
    private Carbon $from;
    private Carbon $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Payroll SC Co', 'slug' => Str::slug('Payroll SC Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->klcc = $this->outlet('KLCC', 'KLCC');
        $this->ioi  = $this->outlet('IOI', 'IOI');

        $this->from = Carbon::parse('2026-07-01');
        $this->to   = Carbon::parse('2026-07-25');
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    private function employee(string $name, Outlet $outlet, float $points): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $outlet->id,
            'name' => $name, 'service_points_entitlement' => $points,
            'join_date' => '2025-01-01', 'is_active' => true,
        ]);
    }

    private function pool(?Outlet $outlet, float $amount): ServiceChargePeriod
    {
        return ServiceChargePeriod::create([
            'company_id'  => $this->company->id,
            'outlet_id'   => $outlet?->id,
            'period_from' => $this->from->toDateString(),
            'period_to'   => $this->to->toDateString(),
            'amount'      => $amount,
        ]);
    }

    private function forRun(?Outlet $outlet): ?array
    {
        return app(ServiceChargeDistribution::class)->forRun(
            $this->company->id,
            [$this->klcc->id, $this->ioi->id],
            $this->from,
            $this->to,
            $outlet?->id,
        );
    }

    /** The reported case. */
    public function test_a_company_wide_run_finds_every_outlets_pool(): void
    {
        $a = $this->employee('KLCC STAFF', $this->klcc, 2.0);
        $b = $this->employee('IOI STAFF', $this->ioi, 2.0);

        $this->pool($this->klcc, 10000);
        $this->pool($this->ioi, 4000);

        $result = $this->forRun(null);

        $this->assertNotNull($result, 'A company-wide run found no pool at all — the reported bug.');

        $paid = collect($result['rows'])->keyBy(fn ($r) => $r['employee']->id);

        $this->assertEqualsWithDelta(10000, $paid[$a->id]['gross'], 0.01);
        $this->assertEqualsWithDelta(4000, $paid[$b->id]['gross'], 0.01);
        $this->assertEqualsWithDelta(14000, $result['totals']['gross'], 0.01);
    }

    /**
     * The reason the pools are not simply added: each branch's takings stay
     * with that branch's staff.
     */
    public function test_each_pool_keeps_its_own_rate_per_point(): void
    {
        $rich = $this->employee('KLCC STAFF', $this->klcc, 2.0);
        $lean = $this->employee('IOI STAFF', $this->ioi, 2.0);

        $this->pool($this->klcc, 10000);
        $this->pool($this->ioi, 4000);

        $paid = collect($this->forRun(null)['rows'])->keyBy(fn ($r) => $r['employee']->id);

        // RM 5,000 a point against RM 2,000 a point — not RM 3,500 each, which
        // is what one combined divisor would have produced.
        $this->assertEqualsWithDelta(5000, $paid[$rich->id]['perPoint'], 0.01);
        $this->assertEqualsWithDelta(2000, $paid[$lean->id]['perPoint'], 0.01);
    }

    /** A run for one outlet is unchanged — it was never broken. */
    public function test_a_single_outlet_run_still_sees_its_own_pool(): void
    {
        $this->employee('KLCC STAFF', $this->klcc, 2.0);
        $this->employee('IOI STAFF', $this->ioi, 2.0);

        $this->pool($this->klcc, 10000);
        $this->pool($this->ioi, 4000);

        $result = $this->forRun($this->klcc);

        $this->assertEqualsWithDelta(10000, $result['totals']['gross'], 0.01);
        $this->assertCount(1, $result['rows'], 'A KLCC run must not pay IOI staff.');
    }

    /**
     * A company-wide pool, saved from the All outlets view, is the whole
     * answer — taking it AND the per-outlet pools would pay people twice.
     */
    public function test_a_company_wide_pool_wins_over_the_per_outlet_ones(): void
    {
        $a = $this->employee('KLCC STAFF', $this->klcc, 2.0);
        $this->employee('IOI STAFF', $this->ioi, 2.0);

        $this->pool(null, 8000);
        $this->pool($this->klcc, 10000);

        $result = $this->forRun(null);

        $this->assertEqualsWithDelta(8000, $result['totals']['gross'], 0.01);
        $this->assertCount(2, $result['rows']);
        $this->assertEqualsWithDelta(4000, collect($result['rows'])
            ->firstWhere(fn ($r) => $r['employee']->id === $a->id)['gross'], 0.01);
    }

    /** No pool for the period is still no service charge, not an error. */
    public function test_a_period_with_no_pool_returns_nothing(): void
    {
        $this->employee('KLCC STAFF', $this->klcc, 2.0);

        $this->assertNull($this->forRun(null));
    }

    /** A pool saved for a different window is not this run's. */
    public function test_a_pool_for_another_period_is_not_picked_up(): void
    {
        $this->employee('KLCC STAFF', $this->klcc, 2.0);

        ServiceChargePeriod::create([
            'company_id'  => $this->company->id,
            'outlet_id'   => $this->klcc->id,
            'period_from' => '2026-07-26',
            'period_to'   => '2026-08-25',
            'amount'      => 39635.56,
        ]);

        $this->assertNull($this->forRun(null));
    }
}
