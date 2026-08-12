<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeStatutoryProfile;
use App\Models\Outlet;
use App\Models\StatutorySetting;
use App\Services\Payroll\StatutoryCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SKBBK — Skim Kemalangan Bukan Bencana Kerja, "LINDUNG 24 Jam".
 *
 * PERKESO cover for accidents outside work, from 1 June 2026. Three things
 * make it unlike every scheme beside it, and each has a test here:
 *
 *  1. THE EMPLOYEE PAYS ALL OF IT. No employer share, so nothing may reach
 *     employer_total or cost-to-company. It is HRD Corp in reverse.
 *  2. THE RATE IS PHASED — 0.75%, then 1.0%, then 1.25% — so it is a stored
 *     setting rather than a hard-coded calendar.
 *  3. WHO CONTRIBUTES IS PER PERSON. Mandatory for foreign workers; voluntary
 *     for locals since a Cabinet decision on 10 July 2026 let them opt out
 *     from 14 July by filing a liability release.
 */
class SkbbkContributionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'SKBBK Co', 'slug' => Str::slug('SKBBK Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $s = StatutorySetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->fill([
            'skbbk_enabled' => true, 'skbbk_employee_rate' => 0.75, 'skbbk_ceiling' => 6000,
            'epf_enabled' => false, 'socso_enabled' => false, 'eis_enabled' => false, 'pcb_enabled' => false,
        ])->save();
    }

    private function employee(bool $malaysian, ?bool $skbbk): Employee
    {
        $e = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'STAFF ' . Str::random(4), 'is_active' => true,
            'join_date' => '2025-01-01', 'date_of_birth' => '1995-01-01',
            'basic_salary' => 3000, 'pay_type' => 'monthly',
        ]);

        EmployeeStatutoryProfile::create([
            'company_id' => $this->company->id, 'employee_id' => $e->id,
            'is_malaysian' => $malaysian, 'skbbk_enabled' => $skbbk,
        ]);

        return $e->fresh();
    }

    /** @return array<string, mixed> */
    private function compute(Employee $e, float $wages = 3000): array
    {
        return (new StatutoryCalculator(StatutorySetting::forCompany($this->company->id)))
            ->for($e, $wages, $wages, $wages);
    }

    // ── Who contributes ───────────────────────────────────────────────────

    public function test_a_foreign_worker_contributes_by_default(): void
    {
        $r = $this->compute($this->employee(malaysian: false, skbbk: null));

        $this->assertEqualsWithDelta(22.50, $r['skbbk'], 0.01, '0.75% of 3,000.');
        $this->assertStringContainsString('mandatory for foreign', implode(' ', $r['notes']));
    }

    /** Voluntary since 14 July 2026, so nothing is taken without a decision. */
    public function test_a_local_does_not_contribute_by_default(): void
    {
        $r = $this->compute($this->employee(malaysian: true, skbbk: null));

        $this->assertSame(0.0, $r['skbbk']);
        $this->assertStringContainsString('voluntary for local', implode(' ', $r['notes']),
            'A silent zero looks the same as an opt-out; only one has a release behind it.');
    }

    public function test_a_local_who_opted_in_contributes(): void
    {
        $this->assertEqualsWithDelta(22.50,
            $this->compute($this->employee(malaysian: true, skbbk: true))['skbbk'], 0.01);
    }

    public function test_an_explicit_opt_out_is_honoured_for_either_nationality(): void
    {
        $this->assertSame(0.0, $this->compute($this->employee(malaysian: true, skbbk: false))['skbbk']);
        $this->assertSame(0.0, $this->compute($this->employee(malaysian: false, skbbk: false))['skbbk']);
    }

    public function test_nothing_is_deducted_when_the_scheme_is_switched_off(): void
    {
        $s = StatutorySetting::forCompany($this->company->id);
        $s->fill(['skbbk_enabled' => false])->save();

        $this->assertSame(0.0, $this->compute($this->employee(malaysian: false, skbbk: true))['skbbk']);
    }

    // ── The arithmetic ────────────────────────────────────────────────────

    public function test_it_is_a_percentage_of_wages_capped_at_the_ceiling(): void
    {
        $e = $this->employee(malaysian: false, skbbk: null);

        $this->assertEqualsWithDelta(15.00, $this->compute($e, 2000)['skbbk'], 0.01);
        $this->assertEqualsWithDelta(45.00, $this->compute($e, 6000)['skbbk'], 0.01);
        $this->assertEqualsWithDelta(45.00, $this->compute($e, 9000)['skbbk'], 0.01,
            'Wages above the ceiling do not attract more.');
    }

    /** The rate is phased, so changing it must change the deduction. */
    public function test_the_rate_is_configurable_for_the_next_phase(): void
    {
        $e = $this->employee(malaysian: false, skbbk: null);

        StatutorySetting::forCompany($this->company->id)
            ->fill(['skbbk_employee_rate' => 1.25])->save();

        $this->assertEqualsWithDelta(37.50, $this->compute($e, 3000)['skbbk'], 0.01);
    }

    // ── Which side of the payslip it lands on ─────────────────────────────

    public function test_it_is_deducted_from_the_employee_and_costs_the_employer_nothing(): void
    {
        $r = $this->compute($this->employee(malaysian: false, skbbk: null));

        $this->assertEqualsWithDelta(22.50, $r['employee_total'], 0.01,
            'Every other scheme is off in this test, so the whole employee total is SKBBK.');
        $this->assertSame(0.0, $r['employer_total'],
            'The employer contributes nothing towards SKBBK.');
    }

    /** Outsourced and intern exemptions must cover it too. */
    public function test_the_existing_statutory_exemptions_still_apply(): void
    {
        foreach (['outsourcing', 'internship'] as $status) {
            $e = $this->employee(malaysian: false, skbbk: true);
            $e->update([
                'employment_status' => $status,
                'employment_status_date' => $status === 'internship' ? '2026-12-31' : null,
                'outsourcing_company' => $status === 'outsourcing' ? 'Experiva' : null,
            ]);

            $this->assertSame(0.0, $this->compute($e->fresh())['skbbk'],
                "A $status employee contributes to nothing, SKBBK included.");
        }
    }

    // ── The three-state flag ──────────────────────────────────────────────

    public function test_the_profile_keeps_not_asked_separate_from_opted_out(): void
    {
        $notAsked = EmployeeStatutoryProfile::forEmployee($this->employee(malaysian: true, skbbk: null));
        $optedOut = EmployeeStatutoryProfile::forEmployee($this->employee(malaysian: true, skbbk: false));

        $this->assertNull($notAsked->skbbk_enabled);
        $this->assertFalse($optedOut->skbbk_enabled);

        // Both deduct nothing, but they are not the same fact.
        $this->assertFalse($notAsked->contributesToSkbbk());
        $this->assertFalse($optedOut->contributesToSkbbk());
    }
}
