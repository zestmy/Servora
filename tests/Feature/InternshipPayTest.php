<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeePayComponent;
use App\Models\Outlet;
use App\Models\PayComponent;
use App\Models\PayrollRun;
use App\Models\StatutorySetting;
use App\Services\Hr\CompensationSummary;
use App\Services\Payroll\StatutoryCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Internship: basic, plus overtime, plus any allowance. Nothing taken off.
 *
 * The rule as given: an intern is paid their stipend and whatever overtime and
 * allowances they have earned, with NO statutory contributions and NO company
 * deductions. It is not the same exemption as outsourcing even though both end
 * outside statutory — an intern is paid DIRECTLY, so their bank details matter
 * and their salary field keeps its ordinary name, whereas an agency head's
 * money goes to the agent.
 *
 * The statutory exemption is a POLICY SWITCH, not a reading of the law.
 * Malaysian schemes differ and PERKESO in particular does reach many interns,
 * so it lives in one method — Employee::hasNoStatutoryContribution() — and
 * turning it off for internships is a one-line change there.
 */
class InternshipPayTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private PayComponent $allowance;
    private PayComponent $deduction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Intern Co', 'slug' => Str::slug('Intern Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        // Contributions on company-wide, so a zero on an intern is the
        // exemption working rather than nothing being switched on.
        $s = StatutorySetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->fill(['epf_enabled' => true, 'socso_enabled' => true, 'eis_enabled' => true])->save();

        $this->allowance = PayComponent::create([
            'company_id' => $this->company->id, 'name' => 'Meal',
            'kind' => 'allowance', 'calculation' => 'fixed', 'is_active' => true,
        ]);
        $this->deduction = PayComponent::create([
            'company_id' => $this->company->id, 'name' => 'Uniform',
            'kind' => 'deduction', 'calculation' => 'fixed', 'is_active' => true,
        ]);
    }

    private function employee(string $name, ?string $status, bool $withComponents = true): Employee
    {
        $employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2025-01-01',
            'date_of_birth' => '2003-01-01',
            'employment_status' => $status,
            'employment_status_date' => array_key_exists((string) $status, Employee::EMPLOYMENT_STATUS_DATE_LABELS)
                ? '2026-12-31' : null,
            'basic_salary' => 1500, 'pay_type' => 'monthly',
        ]);

        if ($withComponents) {
            foreach ([[$this->allowance, 200], [$this->deduction, 50]] as [$component, $amount]) {
                EmployeePayComponent::create([
                    'company_id' => $this->company->id, 'employee_id' => $employee->id,
                    'pay_component_id' => $component->id, 'amount' => $amount,
                    'effective_from' => '2025-01-01',
                ]);
            }
        }

        return $employee;
    }

    /** @return array<string, mixed> */
    private function row(Employee $e): array
    {
        return app(CompensationSummary::class)->forMonth(
            Employee::query()->whereKey($e->id), $this->company->id, Carbon::parse('2026-07-01'),
        )['rows']->first();
    }

    // ── The rule ──────────────────────────────────────────────────────────

    public function test_an_intern_is_paid_basic_plus_allowances_and_nothing_is_taken_off(): void
    {
        $row = $this->row($this->employee('INTERN', 'internship'));

        $this->assertEqualsWithDelta(1500, $row['basic'], 0.01);
        $this->assertEqualsWithDelta(200, $row['allowances'], 0.01);
        $this->assertEqualsWithDelta(0, $row['deductions'], 0.01);
        $this->assertEqualsWithDelta(0, $row['statutory']['employee_total'], 0.01);
        $this->assertEqualsWithDelta(0, $row['statutory']['employer_total'], 0.01);

        // Nothing off means net is simply what was earned.
        $this->assertEqualsWithDelta(1700, $row['net'], 0.01);
        $this->assertEqualsWithDelta($row['gross'], $row['net'], 0.01);
    }

    /**
     * The deduction is DROPPED, not zeroed. A line reading "Uniform 0.00"
     * invites the question of whether it was meant to charge and failed.
     */
    public function test_a_deduction_never_reaches_an_interns_payslip(): void
    {
        $row = $this->row($this->employee('INTERN', 'internship'));

        $kinds = collect($row['components'])->pluck('kind');

        $this->assertContains('allowance', $kinds->all());
        $this->assertNotContains('deduction', $kinds->all());
    }

    /** Every scheme, including the employer-only levy. */
    public function test_no_statutory_scheme_applies_to_an_intern(): void
    {
        $intern = $this->employee('INTERN', 'internship', withComponents: false);

        $calc   = new StatutoryCalculator(StatutorySetting::forCompany($this->company->id));
        $result = $calc->for($intern, 1500, 1500, 1500);

        foreach (['epf_employee', 'epf_employer', 'socso_employee', 'socso_employer',
                  'eis_employee', 'eis_employer', 'pcb', 'hrdf_employer'] as $key) {
            $this->assertSame(0.0, $result[$key], "$key must be zero for an intern.");
        }

        $this->assertContains(StatutoryCalculator::INTERN_NOTE, $result['notes']);
    }

    /** Overtime is earned and paid — it is only deductions that stop. */
    public function test_overtime_still_prices_for_an_intern(): void
    {
        $intern = $this->employee('INTERN', 'internship', withComponents: false);

        $rate = \App\Models\CompensationSetting::forCompany($this->company->id)
            ->hourlyRate((float) $intern->basic_salary, $intern->pay_type);

        $this->assertNotNull($rate, 'An intern with a stipend has an overtime rate like anyone else.');
        $this->assertGreaterThan(0, $rate);
    }

    // ── It must not leak onto anybody else ────────────────────────────────

    public function test_ordinary_staff_are_untouched(): void
    {
        $row = $this->row($this->employee('CONFIRMED', 'confirmed'));

        $this->assertEqualsWithDelta(50, $row['deductions'], 0.01,
            'Only interns stop being deducted.');
        $this->assertGreaterThan(0, $row['statutory']['employee_total']);
    }

    /** Outsourced staff are exempt too, but for a different reason and label. */
    public function test_an_intern_is_not_treated_as_outsourced(): void
    {
        $intern = $this->employee('INTERN', 'internship', withComponents: false);

        $this->assertTrue($intern->isIntern());
        $this->assertFalse($intern->isOutsourced());
        $this->assertTrue($intern->hasNoStatutoryContribution());

        // Paid directly, so the field keeps its ordinary name.
        $this->assertSame('Basic Salary', $intern->salaryFieldLabel());
    }

    public function test_an_outsourced_head_still_takes_deductions(): void
    {
        $agency = $this->employee('AGENCY', 'outsourcing');

        $this->assertEqualsWithDelta(50, $this->row($agency)['deductions'], 0.01,
            'The no-deduction rule was asked for internships only.');
    }

    // ── The status itself ─────────────────────────────────────────────────

    public function test_internship_is_a_selectable_status_everywhere(): void
    {
        $this->assertArrayHasKey('internship', Employee::EMPLOYMENT_STATUSES);
        $this->assertSame('Internship', Employee::EMPLOYMENT_STATUSES['internship']);

        // Fixed term, so the form asks when it ends.
        $this->assertArrayHasKey('internship', Employee::EMPLOYMENT_STATUS_DATE_LABELS);

        // And payroll can be run for them as their own segment.
        $this->assertArrayHasKey('internship', PayrollRun::employmentSegments());
    }

    /** Interns are the company's own staff, so the own-staff segment has them. */
    public function test_interns_are_inside_the_own_staff_segment(): void
    {
        $this->employee('INTERN', 'internship', withComponents: false);
        $this->employee('AGENCY', 'outsourcing', withComponents: false);

        $query = Employee::query()->where('company_id', $this->company->id);
        PayrollRun::applyEmploymentStatus($query, PayrollRun::SEGMENT_EXCLUDE_OUTSOURCING);

        $this->assertSame(['INTERN'], $query->pluck('name')->all());
    }

    public function test_the_status_date_is_required_for_an_internship(): void
    {
        $this->assertTrue(
            array_key_exists('internship', Employee::EMPLOYMENT_STATUS_DATE_LABELS),
            'An internship is a fixed term; the end date is what somebody acts on.'
        );
        $this->assertStringContainsString('Until', Employee::EMPLOYMENT_STATUS_DATE_LABELS['internship']);
    }
}
