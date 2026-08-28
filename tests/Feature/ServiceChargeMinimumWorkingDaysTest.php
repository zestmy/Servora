<?php

namespace Tests\Feature;

use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\ServiceChargePeriod;
use App\Models\User;
use App\Services\Hr\ServiceChargeDistribution;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Livewire\Hr\AttendanceRecords;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A pool can require a minimum number of WORKING DAYS before it pays anybody.
 *
 * Service points are an entitlement somebody carries whether they worked the
 * period or not, so without this a joiner who started on the 27th and a leaver
 * who went on the 3rd each take a full share of a month they were barely in —
 * out of the pockets of everyone who worked the other 28 days.
 *
 * The days either side of an employment read UNR (Unrecorded) on the
 * attendance grid, which is the grid's way of saying the person was not here
 * at all. Those days are not worked days, and neither are days off or
 * absences; leave is.
 *
 * The property that matters most is the DIVISOR: somebody who takes no share
 * must not leave their points in the RM/point base, or the pool allocates less
 * than it holds and every remaining share is quietly short.
 */
class ServiceChargeMinimumWorkingDaysTest extends TestCase
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
            'name' => 'MinDays Co', 'slug' => Str::slug('MinDays Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);

        AttendanceCode::seedDefaults($this->company->id);

        $this->from = Carbon::parse('2026-07-01');
        $this->to   = Carbon::parse('2026-07-31');

        foreach (['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge', 'hr.compensation'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /** The attendance screen, pointed at this pool's period and outlet. */
    private function panel()
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.attendance', 'hr.attendance.record',
            'hr.attendance.service_charge', 'hr.compensation']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return Livewire::actingAs($user)
            ->test(AttendanceRecords::class)
            ->set('outletFilter', (string) $this->outlet->id)
            ->set('periodMode', 'range')
            ->set('rangeFrom', $this->from->toDateString())
            ->set('rangeTo', $this->to->toDateString())
            ->set('showServiceCharge', true);
    }

    private function staff(string $name, float $points = 1, string $join = '2025-01-01'): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => $join,
            'service_points_entitlement' => $points,
            'basic_salary' => 2000, 'pay_type' => 'monthly',
        ]);
    }

    private function pool(float $amount, int $minWorkingDays = 0): ServiceChargePeriod
    {
        return ServiceChargePeriod::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_from' => $this->from->toDateString(), 'period_to' => $this->to->toDateString(),
            'amount' => $amount, 'retention_percent' => 0, 'mc_percent' => 0, 'abs_percent' => 0,
            'min_working_days' => $minWorkingDays,
        ]);
    }

    /** Mark $days consecutive days from the 1st with the given code. */
    private function mark(Employee $emp, string $code, int $days, int $startDay = 1): void
    {
        $codeId = AttendanceCode::where('company_id', $this->company->id)
            ->where('code', $code)->value('id');

        for ($i = 0; $i < $days; $i++) {
            AttendanceRecord::create([
                'company_id' => $this->company->id,
                'outlet_id'  => $this->outlet->id,
                'employee_id' => $emp->id,
                'work_date'  => $this->from->copy()->addDays($startDay - 1 + $i)->toDateString(),
                'attendance_code_id' => $codeId,
            ]);
        }
    }

    /** @return array<string, mixed>|null */
    private function read(): ?array
    {
        return app(ServiceChargeDistribution::class)->forPeriod(
            $this->company->id, [$this->outlet->id], $this->from, $this->to, $this->outlet->id,
        );
    }

    private function rowFor(array $result, string $name): array
    {
        return collect($result['rows'])->first(fn ($r) => $r['employee']->name === $name);
    }

    // ── The rule ──────────────────────────────────────────────────────────

    public function test_someone_short_of_the_minimum_takes_no_share(): void
    {
        $full  = $this->staff('Full Month');
        $joiner = $this->staff('Late Joiner');

        $this->mark($full, '✓', 26);
        $this->mark($joiner, '✓', 4, startDay: 28);

        $this->pool(4000, minWorkingDays: 15);

        $result = $this->read();

        $this->assertEquals(0.0, $this->rowFor($result, 'Late Joiner')['net'],
            'Four working days must not draw a share of a pool with a 15-day minimum.');
        $this->assertTrue($this->rowFor($result, 'Late Joiner')['belowMinDays']);
        $this->assertFalse($this->rowFor($result, 'Full Month')['belowMinDays']);
    }

    public function test_their_points_leave_the_divisor_with_them(): void
    {
        foreach (['A', 'B', 'C'] as $name) {
            $this->mark($this->staff($name), '✓', 26);
        }
        $this->mark($this->staff('Late Joiner'), '✓', 4, startDay: 28);

        $this->pool(3000, minWorkingDays: 15);

        $result = $this->read();

        // 3000 over the three who qualify, NOT over all four. Leaving the
        // joiner's point in the base would price this at 750 and hand out
        // 2,250 of a 3,000 pool.
        $this->assertEquals(3.0, $result['totalPoints']);
        $this->assertEquals(1000, $result['perPoint']);
        $this->assertEquals(3000.0, round($result['totals']['net'], 2));
    }

    public function test_no_minimum_leaves_every_share_as_it_was(): void
    {
        foreach (['A', 'B', 'C'] as $name) {
            $this->mark($this->staff($name), '✓', 26);
        }
        $this->mark($this->staff('Late Joiner'), '✓', 4, startDay: 28);

        $this->pool(4000, minWorkingDays: 0);

        $result = $this->read();

        $this->assertEquals(4.0, $result['totalPoints']);
        $this->assertEquals(1000, $result['perPoint']);
        $this->assertEquals(1000.0, $this->rowFor($result, 'Late Joiner')['net'],
            'With no minimum set, the rule is off and nothing about the old split moves.');
    }

    // ── What counts as a working day ──────────────────────────────────────

    public function test_unrecorded_days_off_days_and_absences_do_not_count(): void
    {
        $unr    = $this->staff('All Unrecorded');
        $off    = $this->staff('All Off');
        $absent = $this->staff('All Absent');
        $worked = $this->staff('Worked');

        $this->mark($unr, 'UNR', 20);
        $this->mark($off, 'X', 20);
        $this->mark($absent, 'ABS', 20);
        $this->mark($worked, '✓', 20);

        $this->pool(1000, minWorkingDays: 10);

        $result = $this->read();

        foreach (['All Unrecorded', 'All Off', 'All Absent'] as $name) {
            $this->assertEquals(0, $this->rowFor($result, $name)['workDays'], $name);
            $this->assertTrue($this->rowFor($result, $name)['belowMinDays'], $name);
        }

        $this->assertEquals(20, $this->rowFor($result, 'Worked')['workDays']);
        $this->assertFalse($this->rowFor($result, 'Worked')['belowMinDays']);
    }

    /**
     * The line between the two kinds of leave, which is the whole distinction
     * the rule turns on: the company was paying for one and not the other.
     *
     * Counted when this shipped, because the rule read "any mark except UNR,
     * day off and absent" — and UPL is a mark. A month spent on unpaid leave
     * is not a month worked, and letting it clear the minimum paid a full
     * share out of days nobody was paid for.
     */
    public function test_unpaid_leave_is_not_a_working_day(): void
    {
        $unpaid = $this->staff('Unpaid Month');
        $paid   = $this->staff('Paid Leave');

        $this->mark($unpaid, '✓', 5);
        $this->mark($unpaid, 'UPL', 15, startDay: 6);

        $this->mark($paid, '✓', 5);
        $this->mark($paid, 'AL', 15, startDay: 6);

        $this->pool(1000, minWorkingDays: 10);

        $result = $this->read();

        $this->assertEquals(5, $this->rowFor($result, 'Unpaid Month')['workDays']);
        $this->assertTrue($this->rowFor($result, 'Unpaid Month')['belowMinDays']);
        $this->assertEquals(0.0, round($this->rowFor($result, 'Unpaid Month')['net'], 2));

        $this->assertEquals(20, $this->rowFor($result, 'Paid Leave')['workDays'],
            'Paid leave still counts — the rule tests engagement, not attendance.');
        $this->assertFalse($this->rowFor($result, 'Paid Leave')['belowMinDays']);
    }

    public function test_leave_days_count_as_worked(): void
    {
        $onLeave = $this->staff('On Leave');

        // Ten present days and eight of PAID leave: engaged for the period,
        // which is what the minimum tests. MC already carries its own
        // deduction, and losing the whole share on top of it would charge the
        // same day twice. Unpaid leave is the exception, above.
        $this->mark($onLeave, '✓', 10);
        $this->mark($onLeave, 'AL', 4, startDay: 11);
        $this->mark($onLeave, 'SL', 4, startDay: 15);

        $this->pool(1000, minWorkingDays: 15);

        $result = $this->read();

        $this->assertEquals(18, $this->rowFor($result, 'On Leave')['workDays']);
        $this->assertFalse($this->rowFor($result, 'On Leave')['belowMinDays']);
        $this->assertEquals(1000.0, round($this->rowFor($result, 'On Leave')['net'], 2));
    }

    public function test_an_empty_cell_is_not_a_working_day(): void
    {
        $blank = $this->staff('Never Marked');
        $this->mark($this->staff('Worked'), '✓', 26);

        $this->pool(1000, minWorkingDays: 1);

        $result = $this->read();

        $this->assertEquals(0, $this->rowFor($result, 'Never Marked')['workDays']);
        $this->assertTrue($this->rowFor($result, 'Never Marked')['belowMinDays']);
    }

    // ── It survives being calculated ──────────────────────────────────────

    public function test_a_calculated_period_keeps_the_days_it_was_judged_on(): void
    {
        $joiner = $this->staff('Late Joiner');
        $this->mark($this->staff('Full Month'), '✓', 26);
        $this->mark($joiner, '✓', 4, startDay: 28);

        $this->pool(2000, minWorkingDays: 15);

        app(ServiceChargeDistribution::class)->freeze(
            $this->company->id, [$this->outlet->id], $this->from, $this->to,
            $this->outlet->id, $this->user->id,
        );

        // The grid is re-marked afterwards: the frozen period must not move.
        $this->mark($joiner, '✓', 20, startDay: 5);

        $result = $this->read();

        $this->assertTrue($result['frozen']);
        $this->assertEquals(15, $result['minDays']);
        $this->assertEquals(4, $this->rowFor($result, 'Late Joiner')['workDays'],
            'A closed period must read back the day count it was actually judged on.');
        $this->assertTrue($this->rowFor($result, 'Late Joiner')['belowMinDays']);
        $this->assertEquals(2000.0, round($result['totals']['net'], 2));
    }

    // ── The panel it is set from ──────────────────────────────────────────

    public function test_the_attendance_panel_saves_the_minimum_and_applies_it(): void
    {
        $this->mark($this->staff('A'), '✓', 26);
        $this->mark($this->staff('B'), '✓', 26);
        $this->mark($this->staff('Late Joiner'), '✓', 4, startDay: 28);

        $this->panel()
            ->set('scAmount', '2000')
            ->set('scMinWorkingDays', '15')
            ->call('saveServiceCharge')
            ->assertHasNoErrors();

        $this->assertEquals(15, ServiceChargePeriod::withoutGlobalScopes()
            ->where('outlet_id', $this->outlet->id)->value('min_working_days'));

        // 2000 over the two who qualify, not over all three.
        $result = $this->read();
        $this->assertEquals(1000, $result['perPoint']);
        $this->assertEquals(0.0, $this->rowFor($result, 'Late Joiner')['net']);
        $this->assertEquals(2000.0, round($result['totals']['net'], 2));
    }

    public function test_the_panel_divisor_matches_the_rows_it_is_showing(): void
    {
        $this->mark($this->staff('A'), '✓', 26);
        $this->mark($this->staff('B'), '✓', 26);
        $this->mark($this->staff('Late Joiner'), '✓', 4, startDay: 28);

        // Saved but never calculated, so the panel works the split out live —
        // the path where the divisor comes from a database sum that knows
        // nothing about the attendance grid, and has to be told.
        $this->pool(2000, minWorkingDays: 15);

        $panel = $this->panel();
        $sc    = $panel->viewData('serviceCharge');

        $this->assertEquals(2.0, $sc['totalPoints'],
            'A non-qualifying share left in the divisor under-allocates the pool for everybody.');
        $this->assertEquals(1000, $sc['perPoint']);
        $this->assertEquals(15, $sc['minDays']);

        // And the screen says so: the count a zero row was judged on, and the
        // minimum it was judged against, both on the page.
        $html = $panel->html();
        $this->assertStringContainsString('4 of 15 working days', $html);
        $this->assertStringContainsString('min 15', $html);
    }

    /**
     * The document, not just the screen. The distribution PDF grows a Days
     * column when a minimum applies, and its total row spans a hand-counted
     * number of columns — this renders the whole thing so a miscount fails
     * here rather than on somebody's printout.
     */
    public function test_the_distribution_pdf_renders_with_the_days_column(): void
    {
        $this->mark($this->staff('A'), '✓', 26);
        $this->mark($this->staff('Late Joiner'), '✓', 4, startDay: 28);

        $this->pool(2000, minWorkingDays: 15);

        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.attendance', 'hr.attendance.service_charge']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user)
            ->get(route('hr.attendance.distribution-pdf', [
                'from' => $this->from->toDateString(),
                'to'   => $this->to->toDateString(),
                'outlet' => $this->outlet->id,
            ]))
            ->assertOk();
    }

    public function test_the_panel_rejects_a_minimum_longer_than_the_period_it_can_show(): void
    {
        $this->staff('A');

        $this->panel()
            ->set('scAmount', '1000')
            ->set('scMinWorkingDays', '99')
            ->call('saveServiceCharge')
            ->assertHasErrors('scMinWorkingDays');
    }
}
