<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\ServiceChargePeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Somebody paid from another outlet's pool stays out of their own outlet's.
 *
 * REPORTED AS: an employee posted to DOTTY'S - CK with "service charge paid
 * from DOTTY'S - SURIA KLCC" on their record showed on the CK screen as though
 * CK paid them.
 *
 * The record was right and the payout report was right. The bug was that
 * distribute() took the pool's identity from the SAVED ROW — $row?->outlet_id
 * — and a pool is keyed on outlet AND period, so an outlet nobody has typed a
 * figure into yet has no row at all. Null then means "the all-outlets pool,
 * which everybody is in by definition", and every redirected employee came
 * back into a pool that does not pay them.
 *
 * It was not only a label. The divisor on that same screen has always been
 * scoped by the outlet being viewed, so their points were already out of the
 * base while their row still drew a share: the pool over-allocates, and every
 * figure moves again the moment somebody saves and a row exists.
 */
class ServiceChargeRedirectionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $klcc;
    private Outlet $ck;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'SC Co', 'slug' => Str::slug('SC Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->klcc = $this->outlet('KLCC', 'KLCC');
        $this->ck   = $this->outlet('CK', 'CK');
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    private function employee(string $name, Outlet $posted, ?Outlet $paidFrom = null, float $points = 1.0): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $posted->id,
            'service_charge_outlet_id' => $paidFrom?->id,
            'name'       => $name,
            'service_points_entitlement' => $points,
            'is_active'  => true,
        ]);
    }

    /** @param array<int, Employee> $employees */
    private function split(?ServiceChargePeriod $row, ?int $poolOutletId, array $employees, ?float $totalPoints = null): array
    {
        return ServiceChargePeriod::distribute(
            $row,
            $poolOutletId,
            collect($employees),
            collect(),      // no attendance codes: MC and absence are not what this pins
            [],             // no cells
            5.0,
            10.0,
            $totalPoints,
        );
    }

    private function rowFor(Employee $employee, array $result): ?array
    {
        return collect($result['rows'])->first(fn ($r) => $r['employee']->id === $employee->id);
    }

    /**
     * The reported case: CK has no saved pool, and the person redirected to
     * KLCC must still be out of CK's.
     */
    public function test_a_redirected_employee_is_out_of_their_own_outlets_pool_before_it_is_saved(): void
    {
        $adam = $this->employee('ADAM', $this->ck, paidFrom: $this->klcc, points: 1.5);
        $siti = $this->employee('SITI', $this->ck, points: 1.0);

        $result = $this->split(null, $this->ck->id, [$adam, $siti]);

        $adamRow = $this->rowFor($adam, $result);

        $this->assertTrue($adamRow['elsewhere'], 'CK must not claim to pay somebody KLCC pays.');
        $this->assertTrue($adamRow['excluded']);
        $this->assertSame(0.0, $adamRow['points']);
    }

    /** The same, once a figure has been saved for CK. Both must agree. */
    public function test_the_answer_does_not_change_once_the_pool_is_saved(): void
    {
        $adam = $this->employee('ADAM', $this->ck, paidFrom: $this->klcc, points: 1.5);

        $before = $this->rowFor($adam, $this->split(null, $this->ck->id, [$adam]));

        $row = ServiceChargePeriod::create([
            'company_id'  => $this->company->id,
            'outlet_id'   => $this->ck->id,
            'period_from' => '2026-08-01',
            'period_to'   => '2026-08-31',
            'amount' => 5000,
        ]);

        $after = $this->rowFor($adam, $this->split($row, $this->ck->id, [$adam]));

        $this->assertSame($before['elsewhere'], $after['elsewhere']);
        $this->assertSame($before['points'], $after['points']);
    }

    /**
     * The pool they ARE paid from keeps them, and pays them normally — they are
     * not excluded everywhere, they are excluded here.
     */
    public function test_the_pool_they_are_paid_from_still_pays_them(): void
    {
        $adam = $this->employee('ADAM', $this->ck, paidFrom: $this->klcc, points: 1.5);

        $row = $this->rowFor($adam, $this->split(null, $this->klcc->id, [$adam]));

        $this->assertFalse($row['elsewhere']);
        $this->assertFalse($row['excluded']);
        $this->assertSame(1.5, $row['points']);
    }

    /**
     * Null still means the all-outlets pool, which everybody is in — the
     * distinction the old code could not make.
     */
    public function test_the_all_outlets_pool_includes_everybody(): void
    {
        $adam = $this->employee('ADAM', $this->ck, paidFrom: $this->klcc, points: 1.5);

        $row = $this->rowFor($adam, $this->split(null, null, [$adam]));

        $this->assertFalse($row['elsewhere']);
        $this->assertSame(1.5, $row['points']);
    }

    /**
     * The money consequence, stated as money.
     *
     * The divisor is CK's own — 1.0 point, because the screen has always scoped
     * it that way. If the redirected row still took a share, the pool would pay
     * out more than it holds.
     */
    public function test_the_pool_never_allocates_more_than_it_holds(): void
    {
        $adam = $this->employee('ADAM', $this->ck, paidFrom: $this->klcc, points: 1.5);
        $siti = $this->employee('SITI', $this->ck, points: 1.0);

        $row = ServiceChargePeriod::create([
            'company_id'  => $this->company->id,
            'outlet_id'   => $this->ck->id,
            'period_from' => '2026-08-01',
            'period_to'   => '2026-08-31',
            'amount' => 1000,
        ]);

        $result = $this->split($row, $this->ck->id, [$adam, $siti], totalPoints: 1.0);

        $allocated = collect($result['rows'])->sum('gross');

        $this->assertEqualsWithDelta(1000.0, $allocated, 0.01);
        $this->assertEqualsWithDelta(1000.0, $this->rowFor($siti, $result)['gross'], 0.01);
        $this->assertSame(0.0, $this->rowFor($adam, $result)['gross']);
    }
}
