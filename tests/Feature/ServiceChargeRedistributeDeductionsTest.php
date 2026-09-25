<?php

namespace Tests\Feature;

use App\Livewire\Hr\ServiceCharge;
use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\ServiceChargePeriod;
use App\Models\User;
use App\Services\Hr\ServiceChargeDistribution;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A pool can hand what it deducts from staff back to everyone in it.
 *
 * Off (the default), an MC, absence, lateness or special deduction simply is
 * not paid and stays in the remainder. On, it lifts the final RM/point. The
 * property that matters is that the pool still never pays out more than it
 * holds.
 */
class ServiceChargeRedistributeDeductionsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Carbon $from;
    private Carbon $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Redistribute Co', 'slug' => Str::slug('Redistribute Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        AttendanceCode::seedDefaults($this->company->id);

        $this->from = Carbon::parse('2026-07-01');
        $this->to   = Carbon::parse('2026-07-31');

        foreach (['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge', 'hr.compensation'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function staff(string $name, float $points = 1): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2025-01-01',
            'service_points_entitlement' => $points,
            'basic_salary' => 2000, 'pay_type' => 'monthly',
        ]);
    }

    private function pool(float $amount, bool $redistribute, float $mcPct = 0, array $extra = []): ServiceChargePeriod
    {
        return ServiceChargePeriod::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_from' => $this->from->toDateString(), 'period_to' => $this->to->toDateString(),
            'amount' => $amount, 'retention_percent' => 0, 'mc_percent' => $mcPct, 'abs_percent' => 0,
            'redistribute_deductions' => $redistribute,
        ] + $extra);
    }

    private function mc(Employee $emp, int $days): void
    {
        $codeId = AttendanceCode::where('company_id', $this->company->id)->where('code', 'SL')->value('id');

        for ($i = 0; $i < $days; $i++) {
            AttendanceRecord::create([
                'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
                'employee_id' => $emp->id,
                'work_date' => $this->from->copy()->addDays($i)->toDateString(),
                'attendance_code_id' => $codeId,
            ]);
        }
    }

    private function read(): array
    {
        return app(ServiceChargeDistribution::class)->forPeriod(
            $this->company->id, [$this->outlet->id], $this->from, $this->to, $this->outlet->id,
        );
    }

    private function net(array $result, string $name): float
    {
        return round(collect($result['rows'])->first(fn ($r) => $r['employee']->name === $name)['net'], 2);
    }

    public function test_off_keeps_the_deduction_out_of_the_pool(): void
    {
        $this->mc($this->staff('A'), 2);
        $this->staff('B');
        $this->staff('C');
        $this->pool(3000, redistribute: false, mcPct: 10);

        $r = $this->read();

        $this->assertEquals(1000, $r['perPoint']);
        $this->assertEquals(1000, $r['basePerPoint']);
        $this->assertEquals(0.0, $r['redistributed']);
        $this->assertEquals(800.0, $this->net($r, 'A'));
        $this->assertEquals(2800.0, $r['allocated'], 'The 200 deducted stays with the company.');
    }

    public function test_on_shares_an_mc_deduction_through_a_higher_point_value(): void
    {
        $this->mc($this->staff('A'), 2);
        $this->staff('B');
        $this->staff('C');
        $this->pool(3000, redistribute: true, mcPct: 10);

        $r = $this->read();

        // A keeps 0.8 of a point, so 3000 is shared over 2.8 kept points.
        $this->assertEquals(1000, $r['basePerPoint']);
        $this->assertEquals(1071, $r['perPoint']);
        $this->assertEquals(1071.0, $this->net($r, 'B'));
        $this->assertEquals(856.8, $this->net($r, 'A'), 'Still 20% off their own share, at the new rate.');
        $this->assertEquals(214.2, $r['redistributed']);
        $this->assertLessThanOrEqual(3000, $r['allocated']);
        $this->assertGreaterThan(2995, $r['allocated'], 'Only the floor to a whole ringgit is left behind.');
    }

    public function test_a_flat_special_deduction_goes_back_to_everyone(): void
    {
        $a = $this->staff('A');
        $this->staff('B');
        $this->staff('C');
        $this->pool(3000, redistribute: true, extra: [
            'special_deductions' => [(string) $a->id => ['amount' => 300, 'note' => 'Till short']],
        ]);

        $r = $this->read();

        $this->assertEquals(1100, $r['perPoint']);
        $this->assertEquals(800.0, $this->net($r, 'A'));
        $this->assertEquals(1100.0, $this->net($r, 'B'));
        $this->assertEquals(3000.0, $r['allocated'], 'Nothing of the pool is lost.');
    }

    public function test_funds_hold_points_so_they_share_what_comes_back(): void
    {
        $a = $this->staff('A');
        $this->staff('B');
        $this->staff('C');
        $this->pool(4000, redistribute: true, extra: [
            'fund_allocations'   => [['name' => 'Outlet Fund', 'points' => 1]],
            'special_deductions' => [(string) $a->id => ['amount' => 400, 'note' => null]],
        ]);

        $r = $this->read();

        $this->assertEquals(1100, $r['perPoint']);
        $this->assertEquals(1100.0, $r['funds'][0]['amount']);
        $this->assertEquals(4000.0, $r['allocated']);
    }

    public function test_a_share_deducted_to_nothing_never_overpays_the_pool(): void
    {
        $this->mc($this->staff('A'), 12);   // 12 x 10% capped at 100%
        $this->staff('B');
        $this->staff('C');
        $this->pool(3000, redistribute: true, mcPct: 10);

        $r = $this->read();

        $this->assertEquals(0.0, $this->net($r, 'A'));
        $this->assertEquals(1500, $r['perPoint']);
        $this->assertEquals(3000.0, $r['allocated']);
    }

    private function panel()
    {
        $user = User::factory()->create(['company_id' => $this->company->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge', 'hr.compensation']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return Livewire::actingAs($user)
            ->test(ServiceCharge::class)
            ->set('outletFilter', (string) $this->outlet->id)
            ->set('periodMode', 'range')
            ->set('rangeFrom', $this->from->toDateString())
            ->set('rangeTo', $this->to->toDateString());
    }

    public function test_a_new_pool_redistributes_unless_somebody_unticks_it(): void
    {
        $this->staff('A');

        $this->panel()
            ->assertSet('scRedistribute', true)
            ->set('scAmount', '1000')
            ->call('saveServiceCharge')
            ->assertHasNoErrors();

        $row = ServiceChargePeriod::withoutGlobalScopes()->where('outlet_id', $this->outlet->id)->first();
        $this->assertTrue($row->redistributesDeductions());
    }

    public function test_a_saved_pool_that_keeps_its_deductions_still_reads_unticked(): void
    {
        $this->staff('A');
        $this->pool(1000, redistribute: false);

        $this->panel()->assertSet('scRedistribute', false);
    }

    public function test_the_panel_saves_the_tick_and_the_kept_figures_carry_it(): void
    {
        $this->mc($this->staff('A'), 2);
        $this->staff('B');
        $this->staff('C');

        $this->panel()
            ->set('scAmount', '3000')
            ->set('scMcPercent', '10')
            ->set('scAbsPercent', '0')
            ->set('scRedistribute', true)
            ->call('saveServiceCharge')
            ->assertHasNoErrors()
            ->assertSee('deductions redistributed');

        $row = ServiceChargePeriod::withoutGlobalScopes()->where('outlet_id', $this->outlet->id)->first();
        $this->assertTrue($row->redistributesDeductions());
        $this->assertTrue($row->isFrozen());

        $r = $this->read();
        $this->assertTrue($r['frozen']);
        $this->assertEquals(1071, $r['perPoint']);
        $this->assertEquals(1000, $r['basePerPoint']);
    }

    private function slips(array $r): string
    {
        return view('pdf.service-charge-payout', [
            'rows' => collect($r['rows'])->filter(fn ($row) => $row['points'] > 0)->values(),
            'serviceCharge' => $r, 'from' => $this->from, 'to' => $this->to,
            'brandName' => 'Redistribute Co', 'logoBase64' => null, 'outletName' => 'KLCC',
            'lateRate' => 0.0, 'exportedBy' => 'Tester',
        ])->render();
    }

    public function test_each_slip_shows_its_share_of_what_was_redistributed(): void
    {
        $this->mc($this->staff('A'), 2);
        $this->staff('B', points: 2);
        $this->pool(3000, redistribute: true, mcPct: 10);

        // 3000 over 2.8 kept points: base 1000, final 1071.
        $html = $this->slips($this->read());

        $this->assertStringContainsString('Redistributed deductions', $html);
        $this->assertStringContainsString('2.00 × RM 1,000 per point', $html);
        $this->assertStringContainsString('2.00 × RM 71 per point', $html);
        $this->assertStringContainsString('142.00', $html, "B's 2 points x RM71.");
        $this->assertStringContainsString('lifting each point from RM 1,000', $html);
    }

    public function test_a_pool_keeping_its_deductions_prints_the_slip_as_before(): void
    {
        $this->mc($this->staff('A'), 2);
        $this->staff('B');
        $this->pool(2000, redistribute: false, mcPct: 10);

        $html = $this->slips($this->read());

        $this->assertStringNotContainsString('Redistributed deductions', $html);
        $this->assertStringContainsString('1.00 × RM 1,000 per point', $html);
    }
}
