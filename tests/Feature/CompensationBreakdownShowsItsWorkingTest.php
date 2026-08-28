<?php

namespace Tests\Feature;

use App\Livewire\Hr\Compensation;
use App\Livewire\Hr\EmployeeCompensation;
use App\Livewire\Hr\PayrollRunShow;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeStatutoryProfile;
use App\Models\Outlet;
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
 * The compensation breakdown accounts for every ringgit between the salary on
 * the record and the net at the bottom.
 *
 * REPORTED AS: "basic is RM2,000, why does the breakdown say 1,483.87 — and
 * why is net below gross when every statutory line reads 0.00?" Both figures
 * were right and neither was explained:
 *
 *   1,483.87 = 2,000 ÷ 31 × 23. A part month, priced by Employment Act s.60I.
 *              The payslip has said "23 of 31 days employed — joined …" for a
 *              while; this screen, which is the one people check BEFORE a run,
 *              showed the total with none of the arithmetic.
 *
 *   11.13    = SKBBK at 0.75% of wages. It goes into employee_total and so
 *              into net, but the statutory list showed EPF, SOCSO, EIS and PCB
 *              only — so the money left the page without a line to leave by.
 *
 * Neither figure moves. What changes is that the screen now shows its working.
 */
class CompensationBreakdownShowsItsWorkingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        // The reported month, so the arithmetic in the assertions is the
        // arithmetic that was queried: August 2026 is a 31-day wage period.
        Carbon::setTestNow(Carbon::parse('2026-08-28 12:00:00'));

        $this->company = Company::create([
            'name' => 'Breakdown Co', 'slug' => Str::slug('Breakdown Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        foreach (['hr.compensation', 'hr.employees.manage', 'hr.payroll', 'hr.payroll.approve'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A foreign employee who joined on the 9th — SKBBK is mandatory for them. */
    private function employee(): Employee
    {
        $employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'LAL RO MUANA (OWEN)', 'is_active' => true,
            'join_date' => '2026-08-09', 'basic_salary' => 2000, 'pay_type' => 'monthly',
        ]);

        EmployeeStatutoryProfile::create([
            'company_id' => $this->company->id, 'employee_id' => $employee->id,
            'is_malaysian' => false,
            // Off, exactly as the reported row had them, so SKBBK is the only
            // thing standing between gross and net.
            'epf_enabled' => false, 'socso_enabled' => false,
            'eis_enabled' => false, 'pcb_enabled' => false,
        ]);

        return $employee;
    }

    private function skbbkOn(): void
    {
        $s = StatutorySetting::forCompany($this->company->id);
        $s->company_id    = $this->company->id;
        $s->skbbk_enabled = true;
        $s->save();
    }

    private function user(): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.compensation']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    // ── The two unexplained figures ───────────────────────────────────────

    public function test_a_part_month_says_how_many_days_and_why(): void
    {
        $employee = $this->employee();

        $html = Livewire::actingAs($this->user())
            ->test(EmployeeCompensation::class, ['id' => $employee->id])
            ->html();

        // 2,000 ÷ 31 × 23 — the figure that was queried.
        $this->assertStringContainsString('1,483.87', $html);
        $this->assertStringContainsString('23 of 31 days employed', $html);
        $this->assertStringContainsString('joined 9 Aug 2026', $html);
    }

    public function test_skbbk_is_listed_where_it_is_deducted(): void
    {
        $employee = $this->employee();
        $this->skbbkOn();

        $html = Livewire::actingAs($this->user())
            ->test(EmployeeCompensation::class, ['id' => $employee->id])
            ->html();

        // 0.75% of 1,483.87. Net was 11.13 below gross with nothing on the
        // page to account for it.
        $this->assertStringContainsString('SKBBK', $html);
        $this->assertStringContainsString('11.13', $html);
        $this->assertStringContainsString('1,472.74', $html);
    }

    /**
     * The arithmetic behind the assertion above: the lines shown on the
     * employee side must add up to the gap between gross and net, or the page
     * is telling somebody their money went somewhere it does not name.
     */
    public function test_the_employee_side_accounts_for_the_whole_gap(): void
    {
        $employee = $this->employee();
        $this->skbbkOn();

        $row = Livewire::actingAs($this->user())
            ->test(EmployeeCompensation::class, ['id' => $employee->id])
            ->viewData('thisMonth');

        $st = $row['statutory'];

        $shown = $st['epf_employee'] + $st['socso_employee'] + $st['eis_employee']
            + $st['pcb'] + $st['skbbk'];

        $this->assertEqualsWithDelta($row['gross'] - $row['net'], $shown, 0.01);
        $this->assertEqualsWithDelta(11.13, $st['skbbk'], 0.01);
    }

    public function test_a_full_month_says_nothing_extra(): void
    {
        $employee = $this->employee();
        $employee->update(['join_date' => '2024-01-01']);

        $html = Livewire::actingAs($this->user())
            ->test(EmployeeCompensation::class, ['id' => $employee->id])
            ->html();

        $this->assertStringContainsString('2,000.00', $html);
        $this->assertStringNotContainsString('days employed', $html);
    }

    // ── The list has the same column ──────────────────────────────────────

    public function test_the_list_carries_a_skbbk_column_where_the_scheme_is_on(): void
    {
        $this->employee();
        $this->skbbkOn();

        $html = Livewire::actingAs($this->user())->test(Compensation::class)->html();

        $this->assertStringContainsString('SKBBK', $html);
        $this->assertStringContainsString('11.13', $html);
    }

    public function test_the_list_leaves_the_column_out_where_it_is_not(): void
    {
        $this->employee();

        $html = Livewire::actingAs($this->user())->test(Compensation::class)->html();

        $this->assertStringNotContainsString('SKBBK', $html);
    }

    // ── And the run those figures become ──────────────────────────────────

    /**
     * The payroll run screen had the same two gaps, and it is the screen the
     * money is approved from: no SKBBK column under a Net that includes it,
     * and a part-month basic with no working beside it (the hourly case was
     * the only one it explained).
     */
    public function test_the_payroll_run_screen_shows_skbbk_and_the_part_month(): void
    {
        $employee = $this->employee();
        $this->skbbkOn();

        $user = $this->user();
        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.payroll', 'hr.payroll.approve']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $run = app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id],
            Carbon::parse('2026-08-01'), $this->outlet->id, $user->id,
        );

        $line = $run->lines->firstWhere('employee_id', $employee->id);
        $this->assertEqualsWithDelta(11.13, (float) $line->skbbk, 0.01);

        $html = Livewire::actingAs($user)->test(PayrollRunShow::class, ['run' => $run->uuid])->html();

        $this->assertStringContainsString('SKBBK', $html);
        $this->assertStringContainsString('11.13', $html);
        $this->assertStringContainsString('23 of 31 days', $html);
    }
}
