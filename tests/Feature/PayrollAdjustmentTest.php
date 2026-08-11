<?php

namespace Tests\Feature;

use App\Livewire\Hr\PayrollRunShow;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
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
 * One-off corrections on a draft payroll run.
 *
 * ASKED FOR: a way to handle a salary adjustment — an overpayment from a
 * previous month being recovered, or a shortfall being settled — during
 * payroll processing, before the run is submitted.
 *
 * THE DESIGN CONSTRAINT that shapes all of this: the builder DELETES every
 * line and rebuilds it on each regenerate, and regenerating is a normal thing
 * to do rather than an exception. An adjustment written onto a line would
 * vanish the next time somebody pressed Regenerate and the run would quietly
 * revert to the uncorrected figures. They are held in their own table, keyed
 * on the run and the employee, and re-applied on every build.
 *
 * THE STATUTORY SPLIT is per adjustment because the two common cases need
 * opposite answers: contributions were already remitted on last month's
 * inflated figure, so a recovery must not reduce this month's — but arrears
 * paid now ARE wages now, and contributions are due on them.
 */
class PayrollAdjustmentTest extends TestCase
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
            'name' => 'Adjust Co', 'slug' => Str::slug('Adjust Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $s = StatutorySetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->fill(['epf_enabled' => true, 'socso_enabled' => true, 'eis_enabled' => true, 'pcb_enabled' => false])->save();

        $this->employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'AAA STAFF', 'is_active' => true, 'join_date' => '2025-01-01',
            'date_of_birth' => '1990-01-01', 'basic_salary' => 3000, 'pay_type' => 'monthly',
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

    private function adjust(PayrollRun $run, string $direction, float $amount, bool $affectsStatutory, string $label = 'Correction'): PayrollRunAdjustment
    {
        return PayrollRunAdjustment::create([
            'company_id' => $this->company->id, 'payroll_run_id' => $run->id,
            'employee_id' => $this->employee->id, 'label' => $label, 'amount' => $amount,
            'direction' => $direction, 'affects_statutory' => $affectsStatutory,
            'created_by' => $this->user->id,
        ]);
    }

    private function line(PayrollRun $run): \App\Models\PayrollRunLine
    {
        return $run->lines->firstWhere('employee_id', $this->employee->id);
    }

    // ── The two cases that were asked for ─────────────────────────────────

    public function test_recovering_an_overpayment_comes_off_net_and_leaves_statutory_alone(): void
    {
        $base = $this->line($this->build());

        $this->adjust($this->build(), PayrollRunAdjustment::DEDUCTION, 500, false, 'June overpayment recovery');
        $after = $this->line($this->build());

        $this->assertEqualsWithDelta((float) $base->statutory_employee, (float) $after->statutory_employee, 0.01,
            'Contributions were already remitted on last month\'s inflated figure.');
        $this->assertEqualsWithDelta((float) $base->gross, (float) $after->gross, 0.01);
        $this->assertEqualsWithDelta((float) $base->net - 500, (float) $after->net, 0.01);
        $this->assertEqualsWithDelta(-500, (float) $after->adjustments_total, 0.01);
    }

    public function test_paying_arrears_counts_as_wages_so_contributions_follow(): void
    {
        $base = $this->line($this->build());

        $this->adjust($this->build(), PayrollRunAdjustment::ADDITION, 500, true, 'May arrears');
        $after = $this->line($this->build());

        $this->assertEqualsWithDelta((float) $base->gross + 500, (float) $after->gross, 0.01);
        $this->assertGreaterThan((float) $base->statutory_employee, (float) $after->statutory_employee,
            'Arrears paid now are wages now.');
    }

    /** The reason they are not a column on the line. */
    public function test_an_adjustment_survives_regenerating_the_run(): void
    {
        $this->adjust($this->build(), PayrollRunAdjustment::DEDUCTION, 500, false);

        $first = (float) $this->line($this->build())->net;

        // Regenerate again, nothing else changed.
        $second = $this->line($this->build());

        $this->assertEqualsWithDelta($first, (float) $second->net, 0.01,
            'Regenerating must not silently revert the run to uncorrected figures.');
        $this->assertCount(1, $second->adjustments);
    }

    public function test_both_treatments_can_apply_to_one_employee(): void
    {
        $base = $this->line($this->build());
        $run  = $this->build();

        $this->adjust($run, PayrollRunAdjustment::ADDITION, 500, true, 'Arrears');
        $this->adjust($run, PayrollRunAdjustment::DEDUCTION, 120, false, 'Uniform');

        $after = $this->line($this->build());

        $this->assertCount(2, $after->adjustments);
        $this->assertEqualsWithDelta(380, (float) $after->adjustments_total, 0.01);
        $this->assertEqualsWithDelta((float) $base->gross + 500, (float) $after->gross, 0.01,
            'Only the wages-affecting one moves gross.');
    }

    /** The contribution formulas are not defined on a negative wage. */
    public function test_a_correction_larger_than_the_month_does_not_produce_negative_statutory(): void
    {
        $this->adjust($this->build(), PayrollRunAdjustment::DEDUCTION, 99999, true, 'Typo');

        $line = $this->line($this->build());

        $this->assertGreaterThanOrEqual(0, (float) $line->statutory_employee);
        $this->assertGreaterThanOrEqual(0, (float) $line->statutory_employer);
    }

    public function test_a_negative_net_is_named_before_approval(): void
    {
        $this->adjust($this->build(), PayrollRunAdjustment::DEDUCTION, 99999, false, 'Typo');
        $run = $this->build();

        $warnings = Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->viewData('warnings');

        $this->assertTrue(
            collect($warnings)->contains(fn ($w) => str_contains($w, 'NEGATIVE net')),
            'A typo that demands money from an employee must not sail past approval.'
        );
    }

    // ── The screen ────────────────────────────────────────────────────────

    public function test_an_adjustment_can_be_added_from_the_run_screen(): void
    {
        $run = $this->build();

        Livewire::actingAs($this->user)->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('openAdjust')
            ->set('adj_employee_id', (string) $this->employee->id)
            ->set('adj_label', 'June overpayment recovery')
            ->set('adj_amount', '500')
            ->set('adj_direction', PayrollRunAdjustment::DEDUCTION)
            ->set('adj_affects_statutory', false)
            ->call('saveAdjustment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payroll_run_adjustments', [
            'payroll_run_id' => $run->id, 'label' => 'June overpayment recovery', 'amount' => 500.00,
        ]);

        // Saved AND applied — a correction that needed a separate Regenerate
        // press could be approved without ever having taken effect.
        $this->assertEqualsWithDelta(-500, (float) $this->line($run->fresh(['lines']))->adjustments_total, 0.01);
    }

    /** A row that silently does nothing on rebuild is worse than an error. */
    public function test_an_employee_who_is_not_on_the_run_is_refused(): void
    {
        $other = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'NOT ON RUN', 'is_active' => false, 'join_date' => '2025-01-01',
            'basic_salary' => 1000, 'pay_type' => 'monthly',
        ]);

        $run = $this->build();

        Livewire::actingAs($this->user)->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('openAdjust')
            ->set('adj_employee_id', (string) $other->id)
            ->set('adj_label', 'Nope')
            ->set('adj_amount', '50')
            ->call('saveAdjustment')
            ->assertHasErrors('adj_employee_id');
    }

    public function test_the_amount_must_be_positive_because_direction_carries_the_sign(): void
    {
        $run = $this->build();

        Livewire::actingAs($this->user)->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('openAdjust')
            ->set('adj_employee_id', (string) $this->employee->id)
            ->set('adj_label', 'Bad')
            ->set('adj_amount', '-50')
            ->call('saveAdjustment')
            ->assertHasErrors('adj_amount');
    }

    /** Direction re-suggests the treatment, because the defaults differ. */
    public function test_switching_direction_resuggests_the_statutory_treatment(): void
    {
        $run = $this->build();

        $c = Livewire::actingAs($this->user)->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('openAdjust');

        $c->set('adj_direction', PayrollRunAdjustment::ADDITION)
          ->assertSet('adj_affects_statutory', true);

        $c->set('adj_direction', PayrollRunAdjustment::DEDUCTION)
          ->assertSet('adj_affects_statutory', false);
    }

    /** An approved run is what the company committed to paying. */
    public function test_an_approved_run_cannot_be_adjusted(): void
    {
        $run = $this->build();
        $this->adjust($run, PayrollRunAdjustment::DEDUCTION, 100, false);
        $run = $this->build();

        $run->update(['status' => PayrollRun::APPROVED]);

        Livewire::actingAs($this->user)->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('openAdjust')
            ->assertForbidden();
    }

    /** Deleting a draft takes its corrections with it. */
    public function test_adjustments_are_removed_with_their_run(): void
    {
        $run = $this->build();
        $this->adjust($run, PayrollRunAdjustment::DEDUCTION, 100, false);

        $run->delete();

        $this->assertDatabaseCount('payroll_run_adjustments', 0);
    }

    public function test_the_signed_amount_comes_from_the_direction_not_the_column(): void
    {
        $deduction = new PayrollRunAdjustment(['amount' => 500, 'direction' => PayrollRunAdjustment::DEDUCTION]);
        $addition  = new PayrollRunAdjustment(['amount' => 500, 'direction' => PayrollRunAdjustment::ADDITION]);

        $this->assertSame(-500.0, $deduction->signedAmount());
        $this->assertSame(500.0, $addition->signedAmount());
    }
}
