<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Somebody who had not started yet is not "employed during" a period.
 *
 * REPORTED AS: a 1–25 July service charge pool re-priced itself from RM556 to
 * RM574 a point after it had been calculated. The pool had not changed; the
 * DIVISOR had, because RM/point is the pool over everybody's points.
 *
 * Found while investigating: `employedDuring()` asked only "have they left",
 * so anybody currently on the books counted in any period however far back.
 * Six August hires were sitting inside that July pool. None had points yet so
 * nothing was mispaid — but the day one is given points they take a share of a
 * period they did not work, and dilute everybody else's.
 *
 * A NULL join date is deliberately included. It means the date was never
 * recorded, not that the person started late, and dropping somebody off a
 * payroll over a blank field is the worse of the two failures.
 */
class EmployedDuringJoinDateTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = '2026-07-01';
    private const TO   = '2026-07-25';

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Joiner Co', 'slug' => Str::slug('Joiner Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);
    }

    private function employee(string $name, ?string $join, array $extra = []): Employee
    {
        return Employee::create(array_merge([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => $join,
            'service_points_entitlement' => 1,
        ], $extra));
    }

    /** @return array<int, string> */
    private function during(): array
    {
        return Employee::query()
            ->employedDuring(self::FROM, self::TO)
            ->pluck('name')->sort()->values()->all();
    }

    public function test_somebody_who_joined_after_the_period_is_excluded(): void
    {
        $this->employee('AUGUST HIRE', '2026-08-08');

        $this->assertSame([], $this->during(),
            'An August hire has no business in a July period.');
    }

    public function test_somebody_who_joined_during_the_period_is_included(): void
    {
        $this->employee('MID JOINER', '2026-07-10');

        $this->assertSame(['MID JOINER'], $this->during());
    }

    public function test_somebody_who_joined_before_the_period_is_included(): void
    {
        $this->employee('OLD HAND', '2025-01-01');

        $this->assertSame(['OLD HAND'], $this->during());
    }

    /** Joining on the last day still counts — the range is inclusive. */
    public function test_joining_on_the_final_day_counts(): void
    {
        $this->employee('LAST DAY', self::TO);

        $this->assertSame(['LAST DAY'], $this->during());
    }

    /** A blank date must not drop somebody off a payroll. */
    public function test_a_missing_join_date_is_included(): void
    {
        $this->employee('NO DATE', null);

        $this->assertSame(['NO DATE'], $this->during());
    }

    // ── The leaver rules are unchanged ────────────────────────────────────

    public function test_somebody_who_left_during_the_period_is_still_included(): void
    {
        $this->employee('LEAVER', '2025-01-01', [
            'is_active' => false,
            'employment_status' => 'resigned',
            'employment_status_date' => '2026-07-15',
        ]);

        $this->assertSame(['LEAVER'], $this->during(),
            'Their final days still have to be paid.');
    }

    public function test_somebody_who_left_long_before_is_excluded(): void
    {
        $this->employee('LONG GONE', '2024-01-01', [
            'is_active' => false,
            'employment_status' => 'resigned',
            'employment_status_date' => '2026-05-01',
        ]);

        $this->assertSame([], $this->during());
    }

    /**
     * Called with only a start date, nothing changes. Some callers genuinely
     * mean "anybody on the books", and the end date is opt-in for the ones
     * that are scoped to a period.
     */
    public function test_without_an_end_date_the_old_behaviour_stands(): void
    {
        $this->employee('AUGUST HIRE', '2026-08-08');

        $names = Employee::query()->employedDuring(self::FROM)->pluck('name')->all();

        $this->assertSame(['AUGUST HIRE'], $names);
    }

    /** The whole point: the divisor a pool is priced on. */
    public function test_a_late_joiner_no_longer_dilutes_a_period_they_did_not_work(): void
    {
        $this->employee('WORKED IT', '2025-01-01');
        $this->employee('AUGUST HIRE', '2026-08-08');

        $points = Employee::query()
            ->employedDuring(self::FROM, self::TO)
            ->sum('service_points_entitlement');

        $this->assertEquals(1, $points,
            'Two points here would price every share as if the August hire had worked July.');
    }
}
