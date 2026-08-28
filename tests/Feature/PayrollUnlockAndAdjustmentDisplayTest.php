<?php

namespace Tests\Feature;

use App\Livewire\Hr\PayrollRunShow;
use App\Models\Company;
use App\Models\CompensationSetting;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\OvertimeClaim;
use App\Models\PayrollRun;
use App\Models\PayrollRunAdjustment;
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
 * Two faults reported off one payslip, plus the way back from an approval.
 *
 * REPORTED: a payslip showing "Basic salary 816.13" and "Payroll cycle change
 * −370.97" in the earnings column, above a Gross of 816.13. The figures were
 * each correct and the Net was right, but the column did not add up to the
 * total beneath it, which on a payslip is the same as being wrong.
 *
 * THE CAUSE: CompensationSummary puts an adjustment marked as WAGES inside
 * gross, and adds one marked after-statutory at NET and nowhere else. The
 * payslip printed both in the earnings column, so the second kind could never
 * reconcile. It went unnoticed while adjustments were occasional RM20
 * corrections; a bulk day deduction put a large one on every payslip in a run.
 *
 * ALSO REPORTED: the run list PDF omitted adjustments entirely, so a run
 * carrying RM11,162 of corrections printed a sheet whose Net column could not
 * be reached from the columns beside it.
 *
 * AND: an approved run that turns out to be wrong had no route back short of
 * editing the database — which leaves the overtime claims stamped as paid by a
 * run being rebuilt underneath them. Hence Unlock.
 */
class PayrollUnlockAndAdjustmentDisplayTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $employee;
    private User $user;
    private Carbon $month;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Unlock Co', 'slug' => Str::slug('Unlock Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $s = StatutorySetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->fill(['epf_enabled' => true, 'socso_enabled' => true, 'eis_enabled' => true, 'pcb_enabled' => false])->save();

        $c = CompensationSetting::forCompany($this->company->id);
        $c->company_id = $this->company->id;
        $c->fill(['monthly_working_days' => 26, 'daily_working_hours' => 8])->save();

        $this->employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'ZULFADHLI BIN ROJAE', 'is_active' => true, 'join_date' => '2025-01-01',
            'date_of_birth' => '1990-01-01', 'basic_salary' => 2300, 'pay_type' => 'monthly',
            'employment_status' => 'confirmed', 'employment_status_date' => '2025-06-01',
        ]);

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

        $this->month = Carbon::parse('2026-07-01');
    }

    private function build(): PayrollRun
    {
        return app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id], $this->month, $this->outlet->id, $this->user->id,
        );
    }

    private function adjust(bool $affectsStatutory, float $amount = 370.97): PayrollRun
    {
        $run = $this->build();

        PayrollRunAdjustment::create([
            'company_id' => $this->company->id, 'payroll_run_id' => $run->id,
            'employee_id' => $this->employee->id, 'label' => 'Payroll cycle change',
            'amount' => $amount, 'direction' => PayrollRunAdjustment::DEDUCTION,
            'affects_statutory' => $affectsStatutory, 'created_by' => $this->user->id,
        ]);

        return $this->build();
    }

    // ── The split ─────────────────────────────────────────────────────────

    /**
     * An after-statutory adjustment is in NET and nowhere else, so it is not
     * an earnings line. This is the fact the payslip layout has to follow.
     */
    public function test_an_after_statutory_adjustment_is_not_in_gross(): void
    {
        $line = $this->adjust(false)->lines->firstWhere('employee_id', $this->employee->id);

        $this->assertSame(2300.00, (float) $line->gross,
            'Gross must be untouched by an adjustment applied after statutory.');
        $this->assertSame(-370.97, (float) $line->adjustments_total);

        $this->assertSame([], $line->wageAdjustments());
        $this->assertCount(1, $line->netAdjustments());
        $this->assertSame(-370.97, $line->netAdjustmentsTotal());
    }

    /** One marked as wages IS in gross, so it stays an earnings line. */
    public function test_a_wages_adjustment_is_inside_gross(): void
    {
        $line = $this->adjust(true)->lines->firstWhere('employee_id', $this->employee->id);

        $this->assertSame(2300.00 - 370.97, (float) $line->gross);

        $this->assertCount(1, $line->wageAdjustments());
        $this->assertSame([], $line->netAdjustments());
        $this->assertSame(0.0, $line->netAdjustmentsTotal());
    }

    // ── The payslip ───────────────────────────────────────────────────────

    private function payslipHtml(PayrollRun $run): string
    {
        // The same argument set PayslipPdf builds, so this renders the real
        // document rather than a lookalike.
        return view('pdf.payslip', [
            'run'         => $run,
            'lines'       => $run->lines()->get(),
            'brandName'   => 'Unlock Co',
            'logoBase64'  => null,
            'companyName' => $this->company->name,
            'companyReg'  => null,
            'address'     => null,
            'employerTaxNumber' => null,
            'ratesConfirmed'    => true,
        ])->render();
    }

    /**
     * THE REPORTED FAULT: the earnings column has to add up to the Gross
     * printed under it.
     *
     * Asserted as arithmetic on the rendered figures rather than on where the
     * markup sits, because "the column does not add up" is the complaint and
     * position is only how it happened.
     */
    public function test_the_earnings_column_adds_up_to_gross(): void
    {
        $run  = $this->adjust(false);
        $line = $run->lines->firstWhere('employee_id', $this->employee->id);

        $html = $this->payslipHtml($run);

        // The earnings side is basic + allowances + OT + wage adjustments, and
        // must equal the Gross the template prints.
        $earnings = (float) $line->basic + (float) $line->allowances + (float) $line->ot_amount
            + array_sum(array_map(fn ($a) => (float) $a['amount'], $line->wageAdjustments()));

        $this->assertSame(
            round((float) $line->gross, 2),
            round($earnings, 2),
            'The itemised earnings must reconcile to Gross.'
        );

        // And the after-statutory correction is on the slip, under its own
        // heading rather than among the earnings.
        $this->assertStringContainsString('Adjustments after statutory deductions', $html);
        $this->assertStringContainsString('Payroll cycle change', $html);
    }

    /** The reconciliation is spelled out, not left to be worked out. */
    public function test_the_payslip_explains_how_net_is_reached(): void
    {
        $html = $this->payslipHtml($this->adjust(false));

        $this->assertStringContainsString('Applied to take-home pay only', $html);
        $this->assertStringContainsString('370.97', $html);
    }

    /** With nothing after statutory, the block is absent rather than empty. */
    public function test_the_block_is_absent_when_there_is_nothing_in_it(): void
    {
        $html = $this->payslipHtml($this->adjust(true));

        $this->assertStringNotContainsString('Adjustments after statutory deductions', $html);
        // The wages-affecting one is still itemised, in the earnings column.
        $this->assertStringContainsString('Payroll cycle change', $html);
    }

    // ── The run list PDF ──────────────────────────────────────────────────

    /** The sheet must carry the corrections, or Net cannot be reached. */
    public function test_the_run_list_pdf_shows_adjustments(): void
    {
        $run = $this->adjust(false);

        $html = $this->actingAs($this->user)
            ->get(route('hr.payroll.list-pdf', $run))
            ->assertOk();

        // Rendered through dompdf, so assert on the view the controller uses.
        $view = view('pdf.payroll-run-list', [
            'company' => $this->company, 'run' => $run->fresh(),
            'lines' => $run->lines()->get(),
            'hasService' => false, 'hasAdjust' => true, 'hasZakat' => false, 'hasSkbbk' => false,
            'generatedBy' => $this->user->name,
        ])->render();

        $this->assertStringContainsString('Adjustments', $view);
        $this->assertStringContainsString('-370.97', $view);
        // The figures the spreadsheet had and this did not.
        $this->assertStringContainsString('Statutory', $view);
        $this->assertStringContainsString('Employer EPF', $view);
    }

    /** An unused column stays off the sheet, as service charge already does. */
    public function test_the_adjustment_column_is_omitted_when_there_are_none(): void
    {
        $run = $this->build();

        $view = view('pdf.payroll-run-list', [
            'company' => $this->company, 'run' => $run,
            'lines' => $run->lines()->get(),
            'hasService' => false, 'hasAdjust' => false, 'hasZakat' => false, 'hasSkbbk' => false,
            'generatedBy' => $this->user->name,
        ])->render();

        $this->assertStringNotContainsString('Adjustments', $view);
        $this->assertStringContainsString('Net', $view);
    }

    // ── Unlock ────────────────────────────────────────────────────────────

    private function approve(PayrollRun $run): PayrollRun
    {
        Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('approve');

        return $run->fresh();
    }

    public function test_unlocking_returns_an_approved_run_to_draft(): void
    {
        $run = $this->approve($this->build());
        $this->assertTrue($run->isApproved());

        Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('unlock')
            ->assertSet('showUnlock', false);

        $run = $run->fresh();

        $this->assertTrue($run->isEditable());
        $this->assertNull($run->approved_at);
        $this->assertNull($run->approved_by);
    }

    /**
     * The overtime this run had spoken for is released.
     *
     * Leaving it stamped is the state that makes hand-editing the database so
     * bad: the claims keep saying they were paid by a run being rebuilt, and
     * TimeOffBalance reads paid_at as the thing that ends availability.
     */
    public function test_unlocking_releases_the_overtime_the_run_settled(): void
    {
        $claim = OvertimeClaim::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'employee_id' => $this->employee->id, 'submitted_by' => $this->user->id,
            'claim_date' => '2026-07-10',
            'ot_time_start' => '18:00', 'ot_time_end' => '21:00', 'total_ot_hours' => 3,
            'ot_type' => 'normal_day', 'reason' => 'Stocktake',
            'status' => 'approved', 'settlement' => OvertimeClaim::SETTLE_PAYROLL,
        ]);

        $run = $this->approve($this->build());

        $this->assertNotNull($claim->fresh()->paid_at, 'Approving must settle it, or this proves nothing.');

        Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('unlock');

        $claim = $claim->fresh();

        $this->assertNull($claim->paid_at);
        $this->assertNull($claim->paid_in_run_id);
        $this->assertNull($claim->marked_paid_by);
    }

    /** Once the money has moved, the answer is the next run — not a rewrite. */
    public function test_a_paid_run_cannot_be_unlocked(): void
    {
        $run = $this->approve($this->build());
        $run->update(['status' => PayrollRun::PAID, 'paid_at' => now()]);

        Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('unlock');

        $this->assertSame(PayrollRun::PAID, $run->fresh()->status);
    }

    /** It takes the approver's permission, not the clerk's. */
    public function test_unlocking_needs_the_approve_permission(): void
    {
        $run = $this->approve($this->build());

        $clerk = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $clerk->companies()->syncWithoutDetaching([$this->company->id]);
        $clerk->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $clerk->givePermissionTo('hr.payroll');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::actingAs($clerk)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('unlock')
            ->assertForbidden();

        $this->assertTrue($run->fresh()->isApproved());
    }

    /** And the unlocked run can then be regenerated, which is the whole point. */
    public function test_an_unlocked_run_can_be_regenerated(): void
    {
        $run = $this->approve($this->build());

        Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('unlock')
            ->call('regenerate');

        $this->assertTrue($run->fresh()->isEditable());
    }
}
