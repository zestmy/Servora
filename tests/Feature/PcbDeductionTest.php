<?php

namespace Tests\Feature;

use App\Livewire\Hr\PayrollRunShow;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeStatutoryProfile;
use App\Models\Outlet;
use App\Models\StatutorySetting;
use App\Models\User;
use App\Services\Payroll\PayrollRunBuilder;
use App\Services\Payroll\StatutoryCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * REPORTED AS: PCB switched on for the company, a salary well inside the tax
 * bands, and no PCB on the payslip.
 *
 * With no approved payroll run earlier in the year, the MTD formula was fed
 * the months already worked as UNPAID — so in September it taxed four months
 * of pay, and after reliefs and the rebate that was nothing for most staff.
 * It now estimates those months at this month's pay. And a payroll run says
 * when PCB is switched off, rather than every payslip just lacking the row.
 *
 * EPF is switched off throughout so the figures can be checked by hand:
 * relief is the RM9,000 individual relief alone.
 */
class PcbDeductionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'PCB Co', 'slug' => Str::slug('PCB Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->pcb(true);

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

    private function pcb(bool $on): void
    {
        $s = StatutorySetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->fill([
            'epf_enabled' => false, 'socso_enabled' => false, 'eis_enabled' => false,
            'skbbk_enabled' => false, 'hrdf_enabled' => false, 'pcb_enabled' => $on,
        ])->save();
    }

    private function employee(float $salary, string $joined = '2025-01-01', bool $pcbOnProfile = true): Employee
    {
        $e = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'MOHD AFFANDY ' . Str::random(4), 'is_active' => true,
            'join_date' => $joined, 'date_of_birth' => '1990-01-01',
            'basic_salary' => $salary, 'pay_type' => 'monthly',
        ]);

        EmployeeStatutoryProfile::create([
            'company_id' => $this->company->id, 'employee_id' => $e->id,
            'is_malaysian' => true, 'pcb_enabled' => $pcbOnProfile,
        ]);

        return $e->fresh();
    }

    /** @return array<string, mixed> */
    private function september(Employee $e, float $pay, ?array $ytd = null): array
    {
        return StatutoryCalculator::forCompany($this->company->id)
            ->for($e, $pay, $pay, $pay, Carbon::parse('2026-09-30'), null, $ytd);
    }

    // ── The missed deduction ──────────────────────────────────────────────

    public function test_mid_year_with_no_payroll_history_deducts_the_steady_monthly_pcb(): void
    {
        $r = $this->september($this->employee(6000), 6000);

        // RM72,000 a year − RM9,000 relief = RM63,000 chargeable.
        // Tax = RM1,500 on the first RM50,000 + 11% of RM13,000 = RM2,930; ÷ 12.
        $this->assertEqualsWithDelta(244.17, $r['pcb'], 0.01,
            'Treating January–August as unpaid deducted RM0 here — the reported bug.');
        $this->assertStringContainsString('estimated as if paid the same for the 8 earlier month(s)', implode(' ', $r['notes']));
    }

    public function test_someone_who_joined_this_year_is_estimated_from_their_join_date(): void
    {
        $r = $this->september($this->employee(10000, '2026-07-01'), 10000);

        // July–December: RM60,000 − RM9,000 = RM51,000. Tax = RM1,500 + 11% of
        // RM1,000 = RM1,610, over the 6 months employed.
        $this->assertEqualsWithDelta(268.33, $r['pcb'], 0.01);
        $this->assertStringContainsString('the 2 earlier month(s)', implode(' ', $r['notes']));
    }

    public function test_a_joiner_in_their_first_month_needs_no_estimate(): void
    {
        $r = $this->september($this->employee(10000, '2026-09-10'), 10000);

        // September–December: RM40,000 − RM9,000 = RM31,000, in the 3% band:
        // RM150 + 3% of RM11,000 − RM400 rebate = RM80, over 4 months.
        $this->assertEqualsWithDelta(20.00, $r['pcb'], 0.01);
        $this->assertStringNotContainsString('estimated', implode(' ', $r['notes']));
    }

    public function test_real_payroll_history_is_used_as_it_is(): void
    {
        $ytd = ['gross' => 48000.0, 'epf' => 0.0, 'pcb' => 0.0, 'zakat' => 0.0, 'months' => 8];

        $r = $this->september($this->employee(6000), 6000, $ytd);

        // Same RM2,930 year, none of it deducted yet, so the last four months
        // carry all of it.
        $this->assertEqualsWithDelta(732.50, $r['pcb'], 0.01);
        $this->assertStringNotContainsString('estimated', implode(' ', $r['notes']));
    }

    /**
     * Payroll started here in August: one real month, seven with no record.
     * Only the gap is estimated — August stays as it really was.
     */
    public function test_history_that_only_covers_part_of_the_year_has_the_gap_estimated(): void
    {
        // August was approved with PCB still switched off: paid, nothing deducted.
        $ytd = ['gross' => 6000.0, 'epf' => 0.0, 'pcb' => 0.0, 'zakat' => 0.0, 'months' => 1];

        $r = $this->september($this->employee(6000), 6000, $ytd);

        // January–July estimated at RM2,930 ÷ 12 each = RM1,709.17 already
        // deducted. September: (2,930 − 1,709.17) ÷ 4 = RM305.21 — the steady
        // RM244.17 plus a quarter of August's missed deduction catching up.
        $this->assertEqualsWithDelta(305.21, $r['pcb'], 0.01,
            'With one real month the gap was ignored and PCB understated again.');
        $this->assertStringContainsString('the 7 earlier month(s)', implode(' ', $r['notes']));
    }

    // ── Saying why there is none ──────────────────────────────────────────

    public function test_pcb_off_on_the_profile_is_noted(): void
    {
        $r = $this->september($this->employee(6000, pcbOnProfile: false), 6000);

        $this->assertSame(0.0, $r['pcb']);
        $this->assertContains(StatutoryCalculator::PCB_PROFILE_OFF_NOTE, $r['notes']);
    }

    public function test_a_salary_below_the_threshold_says_why_pcb_is_zero(): void
    {
        $r = $this->september($this->employee(1500), 1500);

        $this->assertSame(0.0, $r['pcb']);
        $this->assertStringContainsString('No PCB this month', implode(' ', $r['notes']));
    }

    // ── The run screen warning ────────────────────────────────────────────

    /** @return array<int, string> */
    private function runWarnings(): array
    {
        $run = app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id], Carbon::parse('2026-09-01'),
            $this->outlet->id, $this->user->id,
        );

        return Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->viewData('warnings');
    }

    public function test_the_run_warns_when_pcb_is_switched_off_for_the_company(): void
    {
        $this->pcb(false);
        $this->employee(6000);

        $this->assertStringContainsString('PCB (income tax) is switched off in Settings', implode(' ', $this->runWarnings()));
    }

    public function test_the_run_says_to_regenerate_when_pcb_was_switched_on_after_it(): void
    {
        $this->pcb(false);
        $this->employee(6000);

        $run = app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id], Carbon::parse('2026-09-01'),
            $this->outlet->id, $this->user->id,
        );

        $this->pcb(true);

        $warnings = Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->viewData('warnings');

        $this->assertStringContainsString('regenerate the run to deduct it', implode(' ', $warnings));
    }

    public function test_the_run_names_employees_with_pcb_off_on_their_profile(): void
    {
        $off = $this->employee(6000, pcbOnProfile: false);
        $this->employee(6000);

        $joined = implode(' ', $this->runWarnings());

        $this->assertStringContainsString('1 employee(s) have PCB switched off on their own statutory profile', $joined);
        $this->assertStringContainsString($off->name, $joined);
        $this->assertStringNotContainsString('switched off in Settings', $joined);
    }
}
