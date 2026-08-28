<?php

namespace Tests\Feature;

use App\Livewire\Hr\PayrollRunShow;
use App\Models\Company;
use App\Models\CompensationSetting;
use App\Models\Employee;
use App\Models\EmployeePayComponent;
use App\Models\Outlet;
use App\Models\PayComponent;
use App\Models\PayrollRun;
use App\Models\PayrollRunAdjustment;
use App\Models\StatutorySetting;
use App\Models\User;
use App\Services\Payroll\BulkDayAdjustment;
use App\Services\Payroll\PayrollRunBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Adding or deducting a number of days' salary across a whole run at once.
 *
 * ASKED FOR: a company shutdown or a festive day is one decision and forty
 * different amounts, because a day of somebody's salary is their own salary.
 *
 * THE TWO DECISIONS THAT COST MONEY, both settled deliberately and both
 * pinned here:
 *
 *  1. WHAT A DAY DIVIDES BY. 26 (the Employment Act's ordinary rate of pay,
 *     and the divisor hourlyRate already uses) or the calendar length of the
 *     run's own period. They differ by about a fifth on a 31-day month, so it
 *     is a choice on the form rather than a constant, and the one used is
 *     written onto every row it creates.
 *
 *  2. DAILY AND HOURLY STAFF ARE NOT OFFERED FOR A DEDUCTION. Their basic is
 *     already the rate times what the grid says they worked, so deducting a
 *     day on top takes the same absence off twice — the same reason
 *     CompensationSummary does not pro-rate them. Additions are unaffected: a
 *     day's bonus means exactly what it says.
 *
 * It creates ORDINARY adjustment rows, one per employee, which is what makes
 * them survive a rebuild, itemise on the payslip and stay individually
 * editable. That is asserted rather than assumed.
 */
class PayrollBulkDayAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $monthly;
    private User $user;
    private Carbon $month;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Bulk Co', 'slug' => Str::slug('Bulk Co') . '-' . uniqid(),
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

        $this->monthly = $this->employee('AAA MONTHLY', 'monthly', 3000);

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

        // July 2026 — 31 calendar days, so 26 and the calendar length differ.
        $this->month = Carbon::parse('2026-07-01');
    }

    private function employee(string $name, ?string $payType, ?float $salary): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2025-01-01',
            'date_of_birth' => '1990-01-01', 'basic_salary' => $salary, 'pay_type' => $payType,
            'employment_status' => 'confirmed', 'employment_status_date' => '2025-06-01',
        ]);
    }

    private function build(): PayrollRun
    {
        return app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id], $this->month, $this->outlet->id, $this->user->id,
        );
    }

    private function service(): BulkDayAdjustment
    {
        return app(BulkDayAdjustment::class);
    }

    // ── What a day is worth ───────────────────────────────────────────────

    /**
     * RM3,000 over a 26-day working month is RM115.3846, and two days of it is
     * RM230.77 — not RM230.76. The rounding happens ONCE, on the employee's
     * total, rather than on the day rate which is then multiplied: rounding
     * first and multiplying loses a cent per day, every month, per person.
     */
    public function test_a_day_is_the_salary_over_the_working_days_setting(): void
    {
        $run = $this->build();

        $preview = $this->service()->preview(
            $run, [$this->monthly->id], 2.0,
            PayrollRunAdjustment::DEDUCTION, BulkDayAdjustment::BASIS_WORKING, false,
        );

        $this->assertSame(230.77, $preview['rows']->first()['amount']);
        $this->assertSame(115.38, $preview['rows']->first()['day_rate']);
    }

    /** The calendar basis divides by the run's own period instead. */
    public function test_the_calendar_basis_divides_by_the_period(): void
    {
        $run = $this->build();

        $preview = $this->service()->preview(
            $run, [$this->monthly->id], 2.0,
            PayrollRunAdjustment::DEDUCTION, BulkDayAdjustment::BASIS_CALENDAR, false,
        );

        // July is 31 days: 3000 / 31 = 96.7742, and two of them is 193.55.
        $this->assertSame(193.55, $preview['rows']->first()['amount']);

        [$divisor, $label] = $this->service()->divisor($run, BulkDayAdjustment::BASIS_CALENDAR);
        $this->assertSame(31, $divisor);
    }

    /**
     * The two bases differ by about a fifth, which is the whole reason the
     * choice is on the form rather than decided here.
     */
    public function test_the_two_bases_give_materially_different_answers(): void
    {
        $run = $this->build();

        $working = $this->service()->preview($run, [$this->monthly->id], 1.0,
            PayrollRunAdjustment::DEDUCTION, BulkDayAdjustment::BASIS_WORKING, false)['total'];

        $calendar = $this->service()->preview($run, [$this->monthly->id], 1.0,
            PayrollRunAdjustment::DEDUCTION, BulkDayAdjustment::BASIS_CALENDAR, false)['total'];

        $this->assertGreaterThan($calendar, $working);
        $this->assertGreaterThan(0.15, ($working - $calendar) / $calendar);
    }

    /** Half a day is a real shutdown, and the arithmetic follows. */
    public function test_half_days_are_priced(): void
    {
        $preview = $this->service()->preview(
            $this->build(), [$this->monthly->id], 0.5,
            PayrollRunAdjustment::DEDUCTION, BulkDayAdjustment::BASIS_WORKING, false,
        );

        $this->assertSame(57.69, $preview['rows']->first()['amount']);
    }

    // ── Allowances ────────────────────────────────────────────────────────

    /**
     * With allowances included, a day is basic AND allowances over the same
     * divisor — cutting the two halves of a day's pay differently would give a
     * figure nobody can reconcile.
     */
    public function test_allowances_are_included_over_the_same_divisor(): void
    {
        $this->giveAllowance($this->monthly, 'Travelling', 260);

        $run = $this->build();

        $without = $this->service()->preview($run, [$this->monthly->id], 1.0,
            PayrollRunAdjustment::DEDUCTION, BulkDayAdjustment::BASIS_WORKING, false);

        $with = $this->service()->preview($run, [$this->monthly->id], 1.0,
            PayrollRunAdjustment::DEDUCTION, BulkDayAdjustment::BASIS_WORKING, true);

        $this->assertSame(115.38, $without['rows']->first()['amount']);
        // 3000/26 + 260/26 = 115.3846 + 10.00
        $this->assertSame(125.38, $with['rows']->first()['amount']);
    }

    /** The note says which it was, so the figure stays explainable. */
    public function test_the_note_records_the_working(): void
    {
        $preview = $this->service()->preview(
            $this->build(), [$this->monthly->id], 2.0,
            PayrollRunAdjustment::DEDUCTION, BulkDayAdjustment::BASIS_WORKING, false,
        );

        $note = $preview['rows']->first()['note'];

        $this->assertStringContainsString('2 days', $note);
        $this->assertStringContainsString('115.38', $note);
        $this->assertStringContainsString('basic only', $note);
        $this->assertStringContainsString('26-day working month', $note);
    }

    // ── Who it may touch ──────────────────────────────────────────────────

    /**
     * Daily and hourly staff are already paid for what they worked, so a day
     * deducted here would deduct the same absence twice.
     */
    public function test_daily_and_hourly_staff_are_not_offered_for_a_deduction(): void
    {
        $daily  = $this->employee('BBB DAILY', 'daily', 100);
        $hourly = $this->employee('CCC HOURLY', 'hourly', 12);

        $run = $this->build();

        $names = $this->service()->candidates($run, PayrollRunAdjustment::DEDUCTION)
            ->pluck('employee_name');

        $this->assertContains('AAA MONTHLY', $names);
        $this->assertNotContains('BBB DAILY', $names);
        $this->assertNotContains('CCC HOURLY', $names);
    }

    /** They are offered for an addition, where a day's bonus means what it says. */
    public function test_daily_and_hourly_staff_are_offered_for_an_addition(): void
    {
        $this->employee('BBB DAILY', 'daily', 100);
        $this->employee('CCC HOURLY', 'hourly', 12);

        $names = $this->service()
            ->candidates($this->build(), PayrollRunAdjustment::ADDITION)
            ->pluck('employee_name');

        $this->assertContains('BBB DAILY', $names);
        $this->assertContains('CCC HOURLY', $names);
    }

    /**
     * The service refuses them too, not merely the list.
     *
     * A screen that does not offer somebody is a convenience; a service that
     * accepts them anyway is the bug it was meant to prevent, reachable by a
     * stale selection or by any future caller.
     */
    public function test_a_deduction_cannot_reach_a_daily_employee_even_if_asked(): void
    {
        $daily = $this->employee('BBB DAILY', 'daily', 100);

        $result = $this->service()->apply(
            $this->build(), [$daily->id], 2.0,
            PayrollRunAdjustment::DEDUCTION, BulkDayAdjustment::BASIS_WORKING, false,
            'Shutdown', false, $this->user->id,
        );

        $this->assertTrue($result['rows']->isEmpty());
        $this->assertSame(0, PayrollRunAdjustment::where('employee_id', $daily->id)->count());
    }

    /** A day for a daily employee is their daily rate, divisor untouched. */
    public function test_a_daily_employee_is_priced_at_their_own_rate(): void
    {
        $daily = $this->employee('BBB DAILY', 'daily', 100);

        $preview = $this->service()->preview(
            $this->build(), [$daily->id], 2.0,
            PayrollRunAdjustment::ADDITION, BulkDayAdjustment::BASIS_WORKING, false,
        );

        $this->assertSame(200.00, $preview['rows']->first()['amount']);
    }

    /** An hourly employee's day is their contracted hours at their rate. */
    public function test_an_hourly_employee_is_priced_at_a_day_of_hours(): void
    {
        $hourly = $this->employee('CCC HOURLY', 'hourly', 12);

        $preview = $this->service()->preview(
            $this->build(), [$hourly->id], 1.0,
            PayrollRunAdjustment::ADDITION, BulkDayAdjustment::BASIS_WORKING, false,
        );

        // 12/hour x 8 contracted hours.
        $this->assertSame(96.00, $preview['rows']->first()['amount']);
    }

    // ── What it refuses to guess ──────────────────────────────────────────

    /**
     * Somebody with no salary is SKIPPED AND NAMED, never priced at zero.
     *
     * A bulk action that reports reaching everybody when it reached all but
     * one is how a person goes un-deducted for a month without anyone knowing.
     */
    public function test_an_employee_without_a_salary_is_skipped_and_named(): void
    {
        $noSalary = $this->employee('DDD NO SALARY', 'monthly', null);

        $result = $this->service()->preview(
            $this->build(), [$this->monthly->id, $noSalary->id], 1.0,
            PayrollRunAdjustment::DEDUCTION, BulkDayAdjustment::BASIS_WORKING, false,
        );

        $this->assertSame(1, $result['rows']->count());
        $this->assertSame(1, $result['skipped']->count());
        $this->assertSame('DDD NO SALARY', $result['skipped']->first()['name']);
        $this->assertStringContainsString('no salary', $result['skipped']->first()['reason']);
    }

    // ── What it writes ────────────────────────────────────────────────────

    /** One ordinary adjustment per employee, and nothing else. */
    public function test_it_writes_one_ordinary_adjustment_per_employee(): void
    {
        $second = $this->employee('BBB MONTHLY', 'monthly', 2600);

        $run = $this->build();

        $this->service()->apply(
            $run, [$this->monthly->id, $second->id], 2.0,
            PayrollRunAdjustment::DEDUCTION, BulkDayAdjustment::BASIS_WORKING, false,
            'Company shutdown', false, $this->user->id,
        );

        $rows = PayrollRunAdjustment::where('payroll_run_id', $run->id)->get();

        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing(
            [$this->monthly->id, $second->id],
            $rows->pluck('employee_id')->all()
        );

        // Different people, different amounts — the point of pricing per head.
        $this->assertSame(230.77, (float) $rows->firstWhere('employee_id', $this->monthly->id)->amount);
        $this->assertSame(200.00, (float) $rows->firstWhere('employee_id', $second->id)->amount);

        foreach ($rows as $r) {
            $this->assertSame('Company shutdown', $r->label);
            $this->assertSame(PayrollRunAdjustment::DEDUCTION, $r->direction);
            $this->assertSame($this->user->id, $r->created_by);
            $this->assertStringContainsString('26-day working month', $r->notes);
        }
    }

    /**
     * They are re-applied on rebuild and land on the line, like any other
     * adjustment — which is the entire reason for going through that table.
     */
    public function test_the_deduction_survives_a_regenerate_and_reaches_the_line(): void
    {
        $run = $this->build();

        $this->service()->apply(
            $run, [$this->monthly->id], 2.0,
            PayrollRunAdjustment::DEDUCTION, BulkDayAdjustment::BASIS_WORKING, false,
            'Company shutdown', false, $this->user->id,
        );

        $rebuilt = $this->build();
        $line = $rebuilt->lines->firstWhere('employee_id', $this->monthly->id);

        $this->assertSame(-230.77, (float) $line->adjustments_total);
        $this->assertNotEmpty($line->adjustments);
        $this->assertSame('Company shutdown', $line->adjustments[0]['label']);
    }

    /** A user's own note is kept in front of the working, not instead of it. */
    public function test_a_users_note_is_kept_alongside_the_working(): void
    {
        $run = $this->build();

        $this->service()->apply(
            $run, [$this->monthly->id], 1.0,
            PayrollRunAdjustment::DEDUCTION, BulkDayAdjustment::BASIS_WORKING, false,
            'Shutdown', false, $this->user->id, 'Approved by GM',
        );

        $notes = PayrollRunAdjustment::where('payroll_run_id', $run->id)->first()->notes;

        $this->assertStringContainsString('Approved by GM', $notes);
        $this->assertStringContainsString('115.38', $notes);
    }

    // ── Through the screen ────────────────────────────────────────────────

    public function test_the_panel_opens_with_everybody_ticked(): void
    {
        $this->employee('BBB MONTHLY', 'monthly', 2600);

        $run = $this->build();

        Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('openBulk')
            ->assertSet('showBulk', true)
            ->assertCount('bulk_selected', 2);
    }

    /**
     * Switching to a deduction drops daily and hourly staff from a selection
     * made under an addition — otherwise the count on the button promises
     * more than the apply can deliver.
     */
    public function test_switching_to_a_deduction_drops_the_staff_it_cannot_touch(): void
    {
        $this->employee('BBB DAILY', 'daily', 100);

        $run = $this->build();

        Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->set('bulk_direction', PayrollRunAdjustment::ADDITION)
            ->call('selectAllBulk')
            ->assertCount('bulk_selected', 2)
            ->set('bulk_direction', PayrollRunAdjustment::DEDUCTION)
            ->assertCount('bulk_selected', 1);
    }

    public function test_it_applies_from_the_screen_and_recalculates(): void
    {
        $run = $this->build();

        Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('openBulk')
            ->set('bulk_days', '2')
            ->set('bulk_label', 'Company shutdown')
            ->call('saveBulkAdjustment')
            ->assertHasNoErrors()
            ->assertSet('showBulk', false);

        $this->assertSame(1, PayrollRunAdjustment::where('payroll_run_id', $run->id)->count());

        // Recalculated immediately — leaving the run stale until somebody
        // remembered to press Regenerate is how a correction gets approved
        // without ever having been applied.
        $line = $run->fresh()->lines->firstWhere('employee_id', $this->monthly->id);
        $this->assertSame(-230.77, (float) $line->adjustments_total);
    }

    /**
     * The panel says what it is about to do, in the words that matter.
     *
     * The divisor and the running total are the two things somebody checks
     * before pressing a button that writes forty rows, so they have to be
     * rendered rather than merely computed.
     */
    public function test_the_panel_shows_the_divisor_and_the_total(): void
    {
        $run = $this->build();

        Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('openBulk')
            ->set('bulk_days', '2')
            ->assertSee('26-day working month')
            // 1 employee at 2 x RM115.3846.
            ->assertSee('230.77')
            ->assertSee('Include fixed allowances');
    }

    /** And says why a daily employee is missing, rather than just omitting them. */
    public function test_the_panel_explains_the_missing_daily_staff(): void
    {
        $this->employee('BBB DAILY', 'daily', 100);

        $run = $this->build();

        Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('openBulk')
            ->assertSee('daily or hourly employee(s) are not');

        // Not asserted by absence of the name: the run's own employee table
        // lists everybody on the run, daily staff included, and rightly so.
        // Who the PICKER offers is asserted directly in the candidates tests.
        $this->assertNotContains(
            'BBB DAILY',
            app(BulkDayAdjustment::class)
                ->candidates($run, PayrollRunAdjustment::DEDUCTION)
                ->pluck('employee_name')
                ->all()
        );
    }

    /**
     * A browser posts checkbox values as STRINGS.
     *
     * Every test above sets the selection from pluck(), which hands back
     * integers — so the whole suite could pass while the actual screen
     * selected nobody. This is the path a person takes.
     */
    public function test_a_selection_of_string_ids_still_applies(): void
    {
        $run = $this->build();

        Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('openBulk')
            ->set('bulk_selected', [(string) $this->monthly->id])
            ->set('bulk_days', '2')
            ->set('bulk_label', 'Company shutdown')
            ->call('saveBulkAdjustment')
            ->assertHasNoErrors();

        $row = PayrollRunAdjustment::where('payroll_run_id', $run->id)->first();

        $this->assertNotNull($row, 'A string employee id must select the same person an integer does.');
        $this->assertSame($this->monthly->id, $row->employee_id);
        $this->assertSame(230.77, (float) $row->amount);
    }

    public function test_a_description_is_required(): void
    {
        $run = $this->build();

        Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('openBulk')
            ->set('bulk_label', '')
            ->call('saveBulkAdjustment')
            ->assertHasErrors('bulk_label');

        $this->assertSame(0, PayrollRunAdjustment::where('payroll_run_id', $run->id)->count());
    }

    public function test_an_empty_selection_is_refused(): void
    {
        $run = $this->build();

        Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('openBulk')
            ->set('bulk_label', 'Shutdown')
            ->call('selectNoneBulk')
            ->call('saveBulkAdjustment')
            ->assertHasErrors('bulk_selected');

        $this->assertSame(0, PayrollRunAdjustment::where('payroll_run_id', $run->id)->count());
    }

    /** An approved run is what the company committed to paying. */
    public function test_an_approved_run_cannot_be_bulk_adjusted(): void
    {
        $run = $this->build();
        $run->update([
            'status' => PayrollRun::APPROVED,
            'approved_by' => $this->user->id, 'approved_at' => now(),
        ]);

        Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->call('openBulk')
            ->assertForbidden();
    }

    private function giveAllowance(Employee $employee, string $name, float $amount): void
    {
        $component = PayComponent::create([
            'company_id' => $this->company->id,
            'name' => $name, 'kind' => 'allowance', 'calculation' => 'fixed',
            'default_amount' => $amount, 'is_taxable' => true,
            'epf_applicable' => false, 'socso_applicable' => false, 'is_active' => true,
        ]);

        EmployeePayComponent::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'pay_component_id' => $component->id,
            'amount' => $amount,
            'effective_from' => '2025-01-01',
        ]);
    }
}
