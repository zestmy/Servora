<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\PayrollRun;
use App\Models\ServiceChargePeriod;
use App\Models\User;
use App\Services\Hr\ServiceChargeDistribution;
use App\Services\Payroll\PayrollRunBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A calculated service charge period keeps the split it was calculated with.
 *
 * REPORTED AS: a 1–25 July pool calculated at RM556 a point read RM574 the
 * next day, with nobody having touched the pool. Only the AMOUNT was ever
 * stored — the split was recomputed from current staff every time the screen
 * was opened, so any edit to an employee re-priced a period that had already
 * been closed. One service point left the divisor and 17,101.24 ÷ 30.75
 * became ÷ 29.75.
 *
 * "Save & Calculate" now saves the calculation, and everything that reads a
 * pool reads those figures back. Moving them takes an explicit Recalculate.
 */
class ServiceChargeFreezeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private Carbon $from;
    private Carbon $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Freeze Co', 'slug' => Str::slug('Freeze Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);

        // A calendar month, so a payroll run's own cycle lines up with the pool
        // — a pool is matched on its EXACT dates, and a mismatch would make the
        // payroll assertions below silently compare zero with zero.
        $this->from = Carbon::parse('2026-07-01');
        $this->to   = Carbon::parse('2026-07-31');
    }

    private function staff(string $name, float $points, string $join = '2025-01-01'): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => $join,
            'service_points_entitlement' => $points,
            'basic_salary' => 2000, 'pay_type' => 'monthly',
        ]);
    }

    private function pool(float $amount = 4000): ServiceChargePeriod
    {
        return ServiceChargePeriod::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_from' => $this->from->toDateString(), 'period_to' => $this->to->toDateString(),
            'amount' => $amount, 'retention_percent' => 0, 'mc_percent' => 0, 'abs_percent' => 0,
        ]);
    }

    private function svc(): ServiceChargeDistribution
    {
        return app(ServiceChargeDistribution::class);
    }

    /** @return array<string, mixed>|null */
    private function read(): ?array
    {
        return $this->svc()->forPeriod(
            $this->company->id, [$this->outlet->id], $this->from, $this->to, $this->outlet->id,
        );
    }

    private function freeze(): void
    {
        $this->svc()->freeze(
            $this->company->id, [$this->outlet->id], $this->from, $this->to,
            $this->outlet->id, $this->user->id,
        );
    }

    // ── The reported bug ──────────────────────────────────────────────────

    public function test_a_calculated_period_holds_its_rate_when_staff_change(): void
    {
        foreach (range(1, 4) as $i) {
            $this->staff("S$i", 1);
        }
        $this->pool(4000);

        $this->freeze();
        $this->assertEquals(1000, $this->read()['perPoint']);

        // Exactly what moved 556 to 574: a point leaves the divisor.
        Employee::where('name', 'S4')->update(['is_active' => false, 'service_points_entitlement' => 0]);

        $this->assertEquals(1000, $this->read()['perPoint'],
            'A closed period must not re-price itself because somebody edited an employee.');
    }

    public function test_an_uncalculated_period_still_computes_live(): void
    {
        foreach (range(1, 4) as $i) {
            $this->staff("S$i", 1);
        }
        $this->pool(4000);

        $read = $this->read();

        $this->assertEquals(1000, $read['perPoint']);
        $this->assertFalse($read['frozen'] ?? false);
    }

    public function test_freezing_records_when_and_by_whom(): void
    {
        $this->staff('S1', 1);
        $this->pool(1000);

        $this->freeze();

        $read = $this->read();
        $this->assertTrue($read['frozen']);
        $this->assertSame($this->user->name, $read['calculatedBy']);
        $this->assertNotNull($read['calculatedAt']);
    }

    // ── Somebody who was not in the calculation ───────────────────────────

    public function test_a_newcomer_is_listed_but_takes_no_share(): void
    {
        foreach (range(1, 4) as $i) {
            $this->staff("S$i", 1);
        }
        $this->pool(4000);
        $this->freeze();

        $this->staff('NEWCOMER', 1, '2026-07-05');

        $rows = collect($this->read()['rows']);
        $new  = $rows->firstWhere(fn ($r) => $r['employee']->name === 'NEWCOMER');

        $this->assertNotNull($new, 'A name that simply vanished would look like a bug.');
        $this->assertTrue($new['notInPool']);
        $this->assertEquals(0, $new['net'],
            'The pool was divided without them; giving them a share would over-allocate it.');

        // And the pool total is still what was actually paid out.
        $this->assertEquals(4000, $this->read()['totals']['net']);
    }

    // ── Moving it on purpose ──────────────────────────────────────────────

    public function test_recalculating_picks_up_the_change(): void
    {
        foreach (range(1, 4) as $i) {
            $this->staff("S$i", 1);
        }
        $this->pool(4000);
        $this->freeze();

        $this->staff('NEWCOMER', 1, '2026-07-05');

        $this->assertEquals(1000, $this->read()['perPoint'], 'Still held before recalculating.');

        $this->freeze();

        $this->assertEquals(800, $this->read()['perPoint'], '4,000 over five points.');
    }

    // ── What a recalculation must never reach ─────────────────────────────

    /**
     * An approved run copied each share onto its own line and cannot be
     * regenerated, so a later recalculation cannot move money the company has
     * already committed to.
     */
    public function test_an_approved_payroll_run_is_untouched_by_a_recalculation(): void
    {
        foreach (range(1, 4) as $i) {
            $this->staff("S$i", 1);
        }
        $this->pool(4000);
        $this->freeze();

        $run = app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id], $this->from, $this->outlet->id,
        );

        $paid = (float) $run->total_service_charge;

        // The assertion only means something if the run actually paid a pool.
        $this->assertEqualsWithDelta(4000, $paid, 0.01,
            'The run must have picked the pool up, or this test proves nothing.');

        $run->update(['status' => PayrollRun::APPROVED]);

        // Move the pool underneath it.
        Employee::where('name', 'S1')->update(['service_points_entitlement' => 5]);
        $this->freeze();

        $run->refresh();

        $this->assertEqualsWithDelta($paid, (float) $run->total_service_charge, 0.01,
            'An approved run must not move when a pool is recalculated.');
        $this->assertFalse($run->isEditable(),
            'And it cannot be regenerated into the new figures either.');
    }

    /** A draft run is meant to follow, which is what being a draft is for. */
    public function test_a_draft_run_picks_up_a_recalculation_when_regenerated(): void
    {
        foreach (range(1, 4) as $i) {
            $this->staff("S$i", 1);
        }
        $this->pool(4000);
        $this->freeze();

        $run = app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id], $this->from, $this->outlet->id,
        );
        $this->assertEqualsWithDelta(4000, (float) $run->total_service_charge, 0.01);

        // One person excluded, then recalculated: the pool still pays out in
        // full, but across fewer points.
        Employee::where('name', 'S1')->update(['is_active' => false, 'service_points_entitlement' => 0]);
        $this->freeze();

        $rebuilt = app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id], $this->from, $this->outlet->id,
        );

        // 3,999 and not 4,000: RM/point is floored to a whole ringgit, so
        // 4,000 over three points is 1,333 each and one ringgit stays
        // undistributed. That is the pool's documented rounding, not a loss.
        $this->assertEquals(1333, $this->read()['perPoint'], '4,000 over three points, floored.');
        $this->assertEqualsWithDelta(3999, (float) $rebuilt->total_service_charge, 0.01);
    }
}
