<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\PayrollRun;
use App\Models\Section;
use App\Models\ServiceChargePeriod;
use App\Models\StatutorySetting;
use App\Services\Payroll\PayrollRunBuilder;
use App\Services\Payroll\StatutoryCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Outsourced staff, and running payroll by segment.
 *
 * An outsourced employee is the AGENT'S employee — normally a foreign worker on
 * the agent's permit. This company buys their labour at a contract rate and
 * settles the agent's invoice. Three consequences, and this covers all three:
 *
 *  1. NO STATUTORY CONTRIBUTIONS. EPF, SOCSO, EIS, PCB and the HRD Corp levy
 *     are obligations of an employer of record, which this company is not.
 *     Contributing would file this company under a scheme number that belongs
 *     to the agent.
 *  2. THE SALARY FIGURE IS THE CONTRACT RATE, not take-home pay.
 *  3. THE MONEY GOES TO THE AGENT, so blank bank details on the employee are
 *     the normal state of the record rather than something missing from it.
 *
 * And because they are settled on a different day against a different
 * document, payroll can be run for a SEGMENT — a section, an employment
 * status, or both — instead of always the whole outlet.
 */
class OutsourcedPayrollTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Section $kitchen;
    private Carbon $month;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Outsourcing Co', 'slug' => Str::slug('Outsourcing Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->kitchen = Section::create([
            'company_id' => $this->company->id, 'name' => 'Kitchen', 'is_active' => true,
        ]);

        $this->month = Carbon::parse('2026-07-01');

        // Contributions switched on company-wide, so a zero on an outsourced
        // line is the exemption doing its job rather than nothing being
        // enabled in the first place.
        $s = StatutorySetting::forCompany($this->company->id);
        $s->fill([
            'epf_enabled' => true, 'socso_enabled' => true,
            'eis_enabled' => true, 'pcb_enabled' => true,
        ])->save();
    }

    private function employee(string $name, ?string $status, float $salary, ?Section $section = null, array $extra = []): Employee
    {
        return Employee::create(array_merge([
            'company_id'   => $this->company->id,
            'outlet_id'    => $this->outlet->id,
            'section_id'   => $section?->id,
            'name'         => $name,
            'is_active'    => true,
            'join_date'    => '2025-01-01',
            'date_of_birth' => '1990-01-01',
            'employment_status'   => $status,
            'outsourcing_company' => $status === 'outsourcing' ? 'Experiva' : null,
            'basic_salary' => $salary,
            'pay_type'     => 'monthly',
        ], $extra));
    }

    private function generateRun(?int $sectionId = null, ?string $segment = null): PayrollRun
    {
        return app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id], $this->month,
            $this->outlet->id, null, null, null, $sectionId, $segment,
        );
    }

    // ── The exemption ─────────────────────────────────────────────────────

    public function test_an_outsourced_employee_contributes_nothing(): void
    {
        $own    = $this->employee('OWN STAFF', 'confirmed', 3000);
        $agency = $this->employee('AGENCY HEAD', 'outsourcing', 3000);

        $calc = new StatutoryCalculator(StatutorySetting::forCompany($this->company->id));

        $ownResult = $calc->for($own, 3000, 3000, 3000);
        $this->assertGreaterThan(0, $ownResult['employee_total'],
            'Own staff must still contribute — otherwise this test proves nothing.');

        $agencyResult = $calc->for($agency, 3000, 3000, 3000);

        $this->assertSame(0.0, $agencyResult['employee_total']);
        $this->assertSame(0.0, $agencyResult['employer_total']);
        $this->assertSame(0.0, $agencyResult['epf_employee']);
        $this->assertSame(0.0, $agencyResult['socso_employee']);
        $this->assertSame(0.0, $agencyResult['eis_employee']);
        $this->assertSame(0.0, $agencyResult['pcb']);
        $this->assertSame(0.0, $agencyResult['hrdf_employer']);
    }

    /**
     * The exemption is the STATUS, not the per-employee switches. Somebody who
     * ticked EPF before the person was moved onto an agency must not still be
     * contributing for them.
     */
    public function test_the_status_overrides_the_employees_own_switches(): void
    {
        $agency = $this->employee('AGENCY HEAD', 'outsourcing', 3000);

        \App\Models\EmployeeStatutoryProfile::create([
            'company_id' => $this->company->id, 'employee_id' => $agency->id,
            'epf_enabled' => true, 'socso_enabled' => true, 'eis_enabled' => true,
            'pcb_enabled' => true, 'epf_number' => 'EPF-123',
        ]);

        $calc   = new StatutoryCalculator(StatutorySetting::forCompany($this->company->id));
        $result = $calc->for($agency->fresh(), 3000, 3000, 3000);

        $this->assertSame(0.0, $result['employee_total']);
        $this->assertSame(0.0, $result['employer_total']);
    }

    /** The zeros carry their reason, so a payslip explains itself. */
    public function test_the_run_records_why_the_contributions_are_zero(): void
    {
        $this->employee('AGENCY HEAD', 'outsourcing', 2200);

        $line = $this->generateRun()->lines->firstWhere('employee_name', 'AGENCY HEAD');

        $this->assertContains(StatutoryCalculator::OUTSOURCED_NOTE, $line->statutory_notes);
        $this->assertTrue($line->isOutsourced());
        $this->assertEquals(0, (float) $line->statutory_employee);
        $this->assertEquals((float) $line->gross, (float) $line->net,
            'Nothing is deducted, so net must equal gross.');
    }

    /** Blank bank details are the normal state, not an incomplete record. */
    public function test_an_outsourced_line_is_not_missing_bank_details(): void
    {
        $this->employee('AGENCY HEAD', 'outsourcing', 2200);
        $this->employee('OWN STAFF', 'confirmed', 3000);

        $lines = $this->generateRun()->lines->keyBy('employee_name');

        $this->assertSame([], $lines['AGENCY HEAD']->missingForPayment(),
            'Payment goes to the agent, so there is nothing missing here.');
        $this->assertSame(['bank', 'account number'], $lines['OWN STAFF']->missingForPayment(),
            'Own staff still need an account to be paid into.');
    }

    // ── The label ─────────────────────────────────────────────────────────

    public function test_the_salary_field_is_named_for_who_receives_it(): void
    {
        $this->assertSame('Basic Salary',
            $this->employee('OWN STAFF', 'confirmed', 3000)->salaryFieldLabel());
        $this->assertSame('Agent Contract Rate',
            $this->employee('AGENCY HEAD', 'outsourcing', 2200)->salaryFieldLabel());
    }

    // ── Segmented runs ────────────────────────────────────────────────────

    public function test_payroll_can_be_run_for_one_employment_segment(): void
    {
        $this->employee('OWN A', 'confirmed', 3000);
        $this->employee('OWN B', 'probation', 2500);
        $this->employee('AGENCY A', 'outsourcing', 2200);
        $this->employee('AGENCY B', 'outsourcing', 2200);

        $agency = $this->generateRun(null, 'outsourcing');
        $own    = $this->generateRun(null, PayrollRun::SEGMENT_EXCLUDE_OUTSOURCING);

        $this->assertSame(2, $agency->employee_count);
        $this->assertSame(2, $own->employee_count);
        $this->assertNotSame($agency->id, $own->id,
            'Two segments of the same month must be two runs, not one overwriting the other.');

        $this->assertEqualsWithDelta(4400, (float) $agency->total_gross, 0.01);
        $this->assertEqualsWithDelta(0, (float) $agency->total_statutory_employee, 0.01);
        $this->assertGreaterThan(0, (float) $own->total_statutory_employee);
    }

    /** Somebody with no status recorded is the company's own until stated. */
    public function test_the_own_staff_segment_includes_staff_with_no_status(): void
    {
        $this->employee('NO STATUS', null, 3000);
        $this->employee('AGENCY', 'outsourcing', 2200);

        $this->assertSame(1, $this->generateRun(null, PayrollRun::SEGMENT_EXCLUDE_OUTSOURCING)->employee_count);
        $this->assertSame(1, $this->generateRun(null, 'outsourcing')->employee_count);
    }

    /** The two halves of the split must cover everybody exactly once. */
    public function test_the_segments_add_up_to_the_whole(): void
    {
        $this->employee('OWN A', 'confirmed', 3000);
        $this->employee('OWN B', null, 2500);
        $this->employee('AGENCY', 'outsourcing', 2200);

        $whole  = $this->generateRun();
        $agency = $this->generateRun(null, 'outsourcing');
        $own    = $this->generateRun(null, PayrollRun::SEGMENT_EXCLUDE_OUTSOURCING);

        $this->assertSame($whole->employee_count, $agency->employee_count + $own->employee_count);
        $this->assertEqualsWithDelta(
            (float) $whole->total_gross,
            (float) $agency->total_gross + (float) $own->total_gross,
            0.01
        );
    }

    public function test_payroll_can_be_run_for_one_section(): void
    {
        $this->employee('KITCHEN AGENCY', 'outsourcing', 2200, $this->kitchen);
        $this->employee('FLOOR AGENCY', 'outsourcing', 2200);

        $run = $this->generateRun($this->kitchen->id, 'outsourcing');

        $this->assertSame(1, $run->employee_count);
        $this->assertSame($this->kitchen->id, $run->section_id);
        $this->assertStringContainsString('Kitchen', $run->scopeLabel());
        $this->assertStringContainsString('Outsourcing', $run->scopeLabel());
    }

    /** Regenerating a segment rebuilds it, rather than forking a second run. */
    public function test_regenerating_a_segment_rebuilds_it_in_place(): void
    {
        $this->employee('AGENCY', 'outsourcing', 2200);

        $first  = $this->generateRun(null, 'outsourcing');
        $second = $this->generateRun(null, 'outsourcing');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PayrollRun::withoutGlobalScopes()
            ->where('company_id', $this->company->id)->count());
    }

    public function test_an_unsegmented_run_says_nothing_about_segments(): void
    {
        $this->employee('OWN', 'confirmed', 3000);

        $run = $this->generateRun();

        $this->assertFalse($run->isSegmented());
        $this->assertSame('KLCC', $run->scopeLabel());
        $this->assertNull($run->employment_status);
        $this->assertNull($run->section_id);
    }

    /**
     * A segmented run totals ITS OWN service charge, not the whole outlet's.
     *
     * The rate per point must still be priced over everybody — narrowing the
     * pool would misprice it — so the narrowing happens after the rate is
     * fixed and before anything is totalled. Without that, a run of one agency
     * head carried the service charge of the entire branch.
     */
    public function test_a_segmented_run_only_totals_its_own_service_charge(): void
    {
        foreach (range(1, 4) as $i) {
            $this->employee("OWN $i", 'confirmed', 1000, null, ['service_points_entitlement' => 1]);
        }
        $this->employee('AGENCY', 'outsourcing', 1000, null, ['service_points_entitlement' => 1]);

        ServiceChargePeriod::create([
            'company_id'  => $this->company->id,
            'outlet_id'   => $this->outlet->id,
            'period_from' => $this->month->copy()->startOfMonth()->toDateString(),
            'period_to'   => $this->month->copy()->endOfMonth()->toDateString(),
            'amount'      => 5000,
            'retention_percent' => 0, 'mc_percent' => 0, 'abs_percent' => 0,
        ]);

        $whole  = $this->generateRun();
        $agency = $this->generateRun(null, 'outsourcing');
        $own    = $this->generateRun(null, PayrollRun::SEGMENT_EXCLUDE_OUTSOURCING);

        $this->assertEqualsWithDelta(5000, (float) $whole->total_service_charge, 0.01);

        // One of five points, so RM 1,000 — priced over the whole outlet.
        $this->assertEqualsWithDelta(1000, (float) $agency->total_service_charge, 0.01);
        $this->assertEqualsWithDelta(4000, (float) $own->total_service_charge, 0.01);

        $this->assertEqualsWithDelta(
            (float) $agency->total_service_charge,
            (float) $agency->lines->sum('service_charge'),
            0.01,
            'The run total must equal the lines it is made of.'
        );
    }
}
