<?php

namespace Tests\Feature;

use App\Livewire\Hr\Payroll;
use App\Models\CompensationSetting;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\PayrollRun;
use App\Models\ServiceChargePeriod;
use App\Models\StatutorySetting;
use App\Models\User;
use App\Services\Payroll\RunPeriods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 4: setting a period per input, from the screen that generates a run.
 *
 * The panel defaults to all three following the run, so the ordinary month is
 * still one field and a button — the first test is the one that keeps it that
 * way. The service charge row is a PICKER rather than two date boxes, because
 * a pool is matched on both its exact dates: a range one day out finds nothing
 * and the run pays no service charge at all, silently. Typing dates cannot fix
 * that; seeing whether they hit a pool can.
 */
class PayrollPeriodFormTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Form Co', 'slug' => Str::slug('Form Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $s = StatutorySetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->fill(['epf_enabled' => false, 'socso_enabled' => false,
            'eis_enabled' => false, 'pcb_enabled' => false])->save();

        // The reported shape: payroll 26th–25th, pools on the calendar month.
        $c = CompensationSetting::forCompany($this->company->id);
        $c->company_id = $this->company->id;
        $c->payroll_cycle_start_day = 26;
        $c->save();

        Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'AZLAN BIN OSMAN', 'is_active' => true, 'join_date' => '2025-01-01',
            'basic_salary' => 3000, 'pay_type' => 'monthly',
            'service_points_entitlement' => 1,
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

    private function form()
    {
        return Livewire::actingAs($this->user)
            ->test(Payroll::class)
            ->set('showNew', true)
            ->set('newMonth', '2026-08')
            ->set('newOutlet', (string) $this->outlet->id);
    }

    private function pool(string $from, string $to, float $amount = 6000): ServiceChargePeriod
    {
        return ServiceChargePeriod::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_from' => $from, 'period_to' => $to,
            'amount' => $amount, 'retention_percent' => 0, 'mc_percent' => 0, 'abs_percent' => 0,
        ]);
    }

    private function latestRun(): PayrollRun
    {
        return PayrollRun::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    // ── The default ───────────────────────────────────────────────────────

    public function test_the_panel_opens_with_every_input_following_the_run(): void
    {
        $form = $this->form();

        foreach (RunPeriods::COMPONENTS as $component) {
            $form->assertSet("periodMode.{$component}", Payroll::MODE_FOLLOWS);
        }

        $form->call('generate');

        $run = $this->latestRun();

        $this->assertFalse($run->hasComponentPeriods(),
            'An untouched panel must still produce the run it always produced.');
        $this->assertSame('2026-07-26', $run->period_start->toDateString());
    }

    // ── Setting one ───────────────────────────────────────────────────────

    public function test_calendar_month_resolves_to_the_month_the_run_is_filed_under(): void
    {
        $this->pool('2026-08-01', '2026-08-31');

        $this->form()
            ->set('periodMode.service_charge', Payroll::MODE_MONTHLY)
            ->call('generate')
            ->assertHasNoErrors();

        $run = $this->latestRun();

        $this->assertSame('2026-08-01', $run->service_charge_from->toDateString());
        $this->assertSame('2026-08-31', $run->service_charge_to->toDateString());
        $this->assertGreaterThan(0, (float) $run->total_service_charge,
            'Pointed at the pool’s own dates, the run pays it.');

        // And the run itself is still the cycle's.
        $this->assertSame('2026-07-26', $run->period_start->toDateString());
        $this->assertNull($run->overtime_from);
    }

    public function test_a_custom_range_is_stored_as_typed(): void
    {
        $this->form()
            ->set('periodMode.overtime', Payroll::MODE_CUSTOM)
            ->set('periodDates.overtime.from', '2026-06-01')
            ->set('periodDates.overtime.to', '2026-06-30')
            ->call('generate')
            ->assertHasNoErrors();

        $run = $this->latestRun();

        $this->assertSame('2026-06-01', $run->overtime_from->toDateString());
        $this->assertSame('2026-06-30', $run->overtime_to->toDateString());
    }

    /** Two digits from a filled field, not two dates from an empty one. */
    public function test_switching_to_custom_pre_fills_what_the_input_already_uses(): void
    {
        $this->form()
            ->set('periodMode.attendance', Payroll::MODE_CUSTOM)
            ->assertSet('periodDates.attendance.from', '2026-07-26')
            ->assertSet('periodDates.attendance.to', '2026-08-25');
    }

    public function test_changing_the_month_re_seeds_a_custom_range(): void
    {
        $this->form()
            ->set('periodMode.attendance', Payroll::MODE_CUSTOM)
            ->set('newMonth', '2026-09')
            ->assertSet('periodDates.attendance.from', '2026-08-26',
                'A range typed against August must not go on describing it.');
    }

    // ── Refusals ──────────────────────────────────────────────────────────

    public function test_a_custom_range_missing_an_end_is_refused(): void
    {
        $this->form()
            ->set('periodMode.overtime', Payroll::MODE_CUSTOM)
            ->set('periodDates.overtime.from', '2026-06-01')
            ->set('periodDates.overtime.to', '')
            ->call('generate')
            ->assertHasErrors('periodDates.overtime.from');

        $this->assertSame(0, PayrollRun::withoutGlobalScopes()->count(),
            'A half-filled range must not quietly fall back to following the run.');
    }

    public function test_a_backwards_custom_range_is_refused(): void
    {
        $this->form()
            ->set('periodMode.overtime', Payroll::MODE_CUSTOM)
            ->set('periodDates.overtime.from', '2026-06-30')
            ->set('periodDates.overtime.to', '2026-06-01')
            ->call('generate')
            ->assertHasErrors('periodDates.overtime.to');
    }

    // ── The pool picker ───────────────────────────────────────────────────

    public function test_a_pool_can_be_adopted_in_one_click(): void
    {
        $pool = $this->pool('2026-08-01', '2026-08-31');

        $this->form()
            ->call('useServiceChargePool', $pool->id)
            ->assertSet('periodMode.service_charge', Payroll::MODE_CUSTOM)
            ->assertSet('periodDates.service_charge.from', '2026-08-01')
            ->assertSet('periodDates.service_charge.to', '2026-08-31');
    }

    public function test_the_panel_offers_the_pools_that_do_not_match(): void
    {
        $this->pool('2026-08-01', '2026-08-31');

        $html = $this->form()->html();

        $this->assertStringContainsString('No pool is saved for these exact dates', $html,
            'The run’s own 26th–25th dates match nothing, which is the reported fault.');
        $this->assertStringContainsString('1 Aug – 31 Aug 2026', $html);
    }

    public function test_the_panel_confirms_a_pool_once_it_matches(): void
    {
        $this->pool('2026-08-01', '2026-08-31');

        $html = $this->form()
            ->set('periodMode.service_charge', Payroll::MODE_MONTHLY)
            ->html();

        $this->assertStringContainsString('this run will carry the service charge', $html);
        $this->assertStringNotContainsString('No pool is saved for these exact dates', $html);
    }

    /** A company that levies no service charge is offered nothing. */
    public function test_a_company_with_no_pools_sees_no_picker(): void
    {
        $html = $this->form()->html();

        $this->assertStringNotContainsString('No pool is saved for these exact dates', $html);
    }

    // ── Not sticky ────────────────────────────────────────────────────────

    public function test_generating_resets_the_panel_for_the_next_run(): void
    {
        $this->form()
            ->set('periodMode.overtime', Payroll::MODE_CUSTOM)
            ->set('periodDates.overtime.from', '2026-06-01')
            ->set('periodDates.overtime.to', '2026-06-30')
            ->call('generate')
            ->assertSet('periodMode.overtime', Payroll::MODE_FOLLOWS,
                'A period chosen for one run must not silently apply to the next.');
    }
}
