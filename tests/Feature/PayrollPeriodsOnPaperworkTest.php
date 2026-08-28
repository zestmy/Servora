<?php

namespace Tests\Feature;

use App\Livewire\Hr\PayrollRunShow;
use App\Models\CompensationSetting;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\OvertimeClaim;
use App\Models\PayrollRun;
use App\Models\ServiceChargePeriod;
use App\Models\StatutorySetting;
use App\Models\User;
use App\Services\Payroll\PayrollRunBuilder;
use App\Services\Payroll\RunPeriods;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 5: a run that counted four different windows can say so.
 *
 * The figures were already right after Phase 3 and settable after Phase 4.
 * What was missing is the paperwork admitting it — and on a payslip that is
 * not a nicety. "Why is my August payslip paying July overtime" is a question
 * asked at a counter by somebody holding the document, and the document is
 * where it has to be answered.
 *
 * NAMED ONLY WHERE AN INPUT DIFFERS from the run's own period. On an ordinary
 * run all three are the range already in the masthead, and repeating it three
 * times against every line is noise that teaches people to stop reading.
 */
class PayrollPeriodsOnPaperworkTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private Employee $monthly;
    private Employee $hourly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Paper Co', 'slug' => Str::slug('Paper Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $s = StatutorySetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->fill(['epf_enabled' => false, 'socso_enabled' => false,
            'eis_enabled' => false, 'pcb_enabled' => false])->save();

        $c = CompensationSetting::forCompany($this->company->id);
        $c->company_id = $this->company->id;
        $c->payroll_cycle_start_day = 26;
        $c->save();

        $this->monthly = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'RAJESH A/L MANI', 'is_active' => true, 'join_date' => '2025-01-01',
            'basic_salary' => 3000, 'pay_type' => 'monthly',
            'service_points_entitlement' => 1,
        ]);

        $this->hourly = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'HOURLY STAFF', 'is_active' => true, 'join_date' => '2025-01-01',
            'basic_salary' => 10, 'pay_type' => 'hourly',
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
    }

    private function claim(string $date): void
    {
        OvertimeClaim::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'employee_id' => $this->monthly->id, 'submitted_by' => $this->user->id,
            'claim_date' => $date,
            'ot_time_start' => '18:00', 'ot_time_end' => '22:00', 'total_ot_hours' => 4,
            'hours_taken_off' => 0, 'ot_type' => 'normal_day', 'reason' => 'Stocktake',
            'status' => 'approved', 'settlement' => OvertimeClaim::SETTLE_PAYROLL,
        ]);
    }

    /** @param array<string, array{0: Carbon, 1: Carbon}>|null $periods */
    private function build(?array $periods = null): PayrollRun
    {
        return app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id], Carbon::parse('2026-08-01'),
            $this->outlet->id, $this->user->id, null, null, null, null, $periods,
        );
    }

    /** A run whose overtime and service charge came from elsewhere. */
    private function mixedRun(): PayrollRun
    {
        $this->claim('2026-06-15');

        ServiceChargePeriod::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_from' => '2026-08-01', 'period_to' => '2026-08-31',
            'amount' => 6000, 'retention_percent' => 0, 'mc_percent' => 0, 'abs_percent' => 0,
        ]);

        return $this->build([
            RunPeriods::OVERTIME       => [Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')],
            RunPeriods::SERVICE_CHARGE => [Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')],
        ]);
    }

    /**
     * A document's HTML, rendered from the data its own controller passed.
     *
     * Captured through the composing event rather than assembled by hand: a
     * hand-built data array is a guess at the controller's, and a test that
     * renders a view the product never renders proves nothing about the
     * product. Hitting the route first also proves the real thing still
     * builds; dompdf compresses its streams, so the text is read from the
     * same view rather than out of the binary.
     */
    private function documentHtml(string $view, string $routeName, PayrollRun $run, $extra = null): string
    {
        $captured = [];

        Event::listen("composing: {$view}", function ($v) use (&$captured) {
            $captured = $v->getData();
        });

        $this->actingAs($this->user)
            ->get(route($routeName, $extra ? [$run, $extra] : [$run]))
            ->assertOk();

        return view($view, $captured)->render();
    }

    private function screen(PayrollRun $run): string
    {
        return Livewire::actingAs($this->user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->html();
    }

    // ── The run screen ────────────────────────────────────────────────────

    public function test_the_run_screen_names_the_periods_that_differ(): void
    {
        $html = $this->screen($this->mixedRun());

        $this->assertStringContainsString('26 Jul – 25 Aug 2026', $html, 'the run’s own period');
        $this->assertStringContainsString('Overtime', $html);
        $this->assertStringContainsString('1 Jun – 30 Jun 2026', $html);
        $this->assertStringContainsString('Service charge', $html);
        $this->assertStringContainsString('1 Aug – 31 Aug 2026', $html);
    }

    public function test_an_ordinary_run_says_nothing_extra(): void
    {
        $this->claim('2026-07-30');

        $html = $this->screen($this->build());

        // The period is stated once, in the header, and not repeated per input.
        $this->assertSame(1, substr_count($html, '26 Jul – 25 Aug 2026'),
            'Repeating the run’s own dates three times is noise that teaches people to stop reading.');
    }

    // ── The payslip ───────────────────────────────────────────────────────

    public function test_the_payslip_names_the_overtime_and_pool_periods(): void
    {
        $run  = $this->mixedRun();
        $line = $run->lines->firstWhere('employee_id', $this->monthly->id);

        $this->assertEquals(4.0, (float) $line->ot_hours, 'precondition: the June claim is on this run');
        $this->assertGreaterThan(0, (float) $line->service_charge);

        $html = $this->documentHtml('pdf.payslip', 'hr.payroll.payslip', $run, $line);

        $this->assertStringContainsString('1 Jun – 30 Jun 2026', $html,
            'A payslip paying June’s overtime in an August run has to say so on its face.');
        $this->assertStringContainsString('pool for 1 Aug – 31 Aug 2026', $html);
    }

    public function test_an_ordinary_payslip_carries_no_extra_dates(): void
    {
        $this->claim('2026-07-30');

        $run  = $this->build();
        $line = $run->lines->firstWhere('employee_id', $this->monthly->id);

        $html = $this->documentHtml('pdf.payslip', 'hr.payroll.payslip', $run, $line);

        $this->assertStringContainsString('4.00 hours', $html);
        $this->assertStringNotContainsString('pool for', $html);
    }

    // ── The exports ───────────────────────────────────────────────────────

    public function test_the_run_list_pdf_carries_the_periods(): void
    {
        $run = $this->mixedRun();

        $html = $this->documentHtml('pdf.payroll-run-list', 'hr.payroll.list-pdf', $run);

        $this->assertStringContainsString('Overtime:', $html);
        $this->assertStringContainsString('1 Jun – 30 Jun 2026', $html);
        $this->assertStringContainsString('Service charge:', $html);
    }

    public function test_the_excel_export_still_builds(): void
    {
        $run = $this->mixedRun();

        $this->actingAs($this->user)
            ->get(route('hr.payroll.run-excel', $run))
            ->assertOk();
    }

    // ── The whole chain ───────────────────────────────────────────────────

    /**
     * The point of all five phases in one assertion: a run counting three
     * different windows pays the right figures AND explains them.
     */
    public function test_a_run_of_four_windows_pays_and_explains_itself(): void
    {
        $run = $this->mixedRun();

        $line = $run->lines->firstWhere('employee_id', $this->monthly->id);

        $this->assertSame('2026-07-26', $run->period_start->toDateString());
        $this->assertSame('2026-06-01', $run->overtime_from->toDateString());
        $this->assertSame('2026-08-01', $run->service_charge_from->toDateString());
        $this->assertNull($run->attendance_from, 'untouched inputs stay null');

        $this->assertSame(3000.0, (float) $line->basic, 'the master period still prices a monthly salary');
        $this->assertEquals(4.0, (float) $line->ot_hours);
        $this->assertGreaterThan(0, (float) $line->service_charge);

        $html = $this->screen($run);
        $this->assertStringContainsString('1 Jun – 30 Jun 2026', $html);
        $this->assertStringContainsString('1 Aug – 31 Aug 2026', $html);
    }
}
