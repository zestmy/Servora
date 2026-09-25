<?php

namespace Tests\Feature;

use App\Livewire\Hr\ServiceCharge;
use App\Models\AttendanceCode;
use App\Models\ClockSetting;
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
 * Lateness typed in by hand on the service charge panel.
 *
 * For outlets that record attendance on paper, whose lateness never reaches
 * the web clock. Minutes, priced at the clock's per-minute rate with no
 * per-shift cap, added to whatever the clock charged.
 */
class ServiceChargeManualLatenessTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Carbon $from;
    private Carbon $to;

    protected function setUp(): void
    {
        parent::setUp();

        ClockSetting::forget();

        $this->company = Company::create([
            'name' => 'Manual Late Co', 'slug' => Str::slug('Manual Late Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        AttendanceCode::seedDefaults($this->company->id);

        // RM1 a minute, and a per-shift cap far below what is typed in, to
        // prove the cap does not reach the hand-entered minutes.
        ClockSetting::forCompany($this->company->id)->update([
            'late_rate_per_minute' => 1, 'late_cap_per_shift' => 10,
        ]);

        $this->from = Carbon::parse('2026-07-01');
        $this->to   = Carbon::parse('2026-07-31');

        foreach (['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge', 'hr.compensation'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function staff(string $name): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2025-01-01',
            'service_points_entitlement' => 1, 'basic_salary' => 2000, 'pay_type' => 'monthly',
        ]);
    }

    private function pool(array $extra = []): ServiceChargePeriod
    {
        return ServiceChargePeriod::create(array_merge([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_from' => $this->from->toDateString(), 'period_to' => $this->to->toDateString(),
            'amount' => 2000, 'retention_percent' => 0, 'mc_percent' => 0, 'abs_percent' => 0,
            'redistribute_deductions' => false,
        ], $extra));
    }

    private function read(): array
    {
        return app(ServiceChargeDistribution::class)->forPeriod(
            $this->company->id, [$this->outlet->id], $this->from, $this->to, $this->outlet->id,
        );
    }

    private function rowFor(array $r, string $name): array
    {
        return collect($r['rows'])->first(fn ($row) => $row['employee']->name === $name);
    }

    public function test_typed_minutes_are_charged_at_the_clock_rate_with_no_cap(): void
    {
        $a = $this->staff('A');
        $this->staff('B');
        $this->pool(['manual_late_minutes' => [(string) $a->id => 60]]);

        $row = $this->rowFor($this->read(), 'A');

        $this->assertEquals(60, $row['lateMins']);
        $this->assertEquals(60, $row['manualLateMins']);
        $this->assertEquals(60.0, $row['lateAmt'], '60 min x RM1, not held to the RM10 shift cap.');
        $this->assertEquals(940.0, round($row['net'], 2));
    }

    public function test_they_are_added_to_what_the_clock_charged(): void
    {
        $pool = $this->pool(['manual_late_minutes' => ['7' => 30]]);

        $merged = $pool->withManualLateness([7 => ['minutes' => 5, 'amount' => 5.0, 'shifts' => 1]]);

        $this->assertEquals(35, $merged[7]['minutes']);
        $this->assertEquals(35.0, $merged[7]['amount']);
        $this->assertEquals(30, $merged[7]['manualMinutes']);
        $this->assertEquals(1, $merged[7]['shifts']);
    }

    public function test_they_are_redistributed_like_any_other_deduction(): void
    {
        $a = $this->staff('A');
        $this->staff('B');
        $this->pool(['redistribute_deductions' => true, 'manual_late_minutes' => [(string) $a->id => 100]]);

        $r = $this->read();

        // (2000 + 100) over 2 points.
        $this->assertEquals(1050, $r['perPoint']);
        $this->assertEquals(950.0, round($this->rowFor($r, 'A')['net'], 2));
        $this->assertEquals(2000.0, $r['allocated']);
    }

    public function test_the_panel_saves_them_and_an_empty_box_stores_nothing(): void
    {
        $a = $this->staff('A');
        $b = $this->staff('B');

        $user = User::factory()->create(['company_id' => $this->company->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge', 'hr.compensation']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::actingAs($user)
            ->test(ServiceCharge::class)
            ->set('outletFilter', (string) $this->outlet->id)
            ->set('periodMode', 'range')
            ->set('rangeFrom', $this->from->toDateString())
            ->set('rangeTo', $this->to->toDateString())
            ->set('scAmount', '2000')
            ->set('scRedistribute', false)
            ->set("scManualLate.{$a->id}", '45')
            ->set("scManualLate.{$b->id}", '')
            ->call('saveServiceCharge')
            ->assertHasNoErrors()
            ->assertSeeHtml("scManualLate.{$a->id}");

        $pool = ServiceChargePeriod::withoutGlobalScopes()->where('outlet_id', $this->outlet->id)->first();

        $this->assertSame([(string) $a->id => 45], $pool->manual_late_minutes, 'A blank box is not kept as a zero.');
        $this->assertEquals(45.0, $this->rowFor($this->read(), 'A')['lateAmt'], 'Kept in the calculated figures.');
        $this->assertEquals(0.0, $this->rowFor($this->read(), 'B')['lateAmt']);
    }

    public function test_part_minutes_are_refused(): void
    {
        $a = $this->staff('A');

        $user = User::factory()->create(['company_id' => $this->company->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge', 'hr.compensation']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::actingAs($user)
            ->test(ServiceCharge::class)
            ->set('outletFilter', (string) $this->outlet->id)
            ->set('periodMode', 'range')
            ->set('rangeFrom', $this->from->toDateString())
            ->set('rangeTo', $this->to->toDateString())
            ->set('scAmount', '2000')
            ->set("scManualLate.{$a->id}", '2.5')
            ->call('saveServiceCharge')
            ->assertHasErrors("scManualLate.{$a->id}");
    }

    public function test_an_edit_on_a_calculated_pool_says_it_is_not_applied_yet(): void
    {
        $a = $this->staff('A');
        $this->staff('B');

        $user = User::factory()->create(['company_id' => $this->company->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge', 'hr.compensation']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $panel = Livewire::actingAs($user)
            ->test(ServiceCharge::class)
            ->set('outletFilter', (string) $this->outlet->id)
            ->set('periodMode', 'range')
            ->set('rangeFrom', $this->from->toDateString())
            ->set('rangeTo', $this->to->toDateString())
            ->set('scAmount', '2000')
            ->set("scManualLate.{$a->id}", '45')
            ->call('saveServiceCharge')
            ->assertHasNoErrors()
            ->assertDontSee('Not applied yet');

        $panel->set("scManualLate.{$a->id}", '30')
            ->assertSee('Not applied yet: 1 late entry')
            ->assertSee('not applied');

        // Back to what was calculated: nothing pending. "045" is still 45.
        $panel->set("scManualLate.{$a->id}", '045')
            ->assertDontSee('Not applied yet');
    }

    public function test_a_special_deduction_edit_on_a_calculated_pool_says_it_is_not_applied_yet(): void
    {
        $a = $this->staff('A');
        $this->staff('B');

        $user = User::factory()->create(['company_id' => $this->company->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge', 'hr.compensation']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $panel = Livewire::actingAs($user)
            ->test(ServiceCharge::class)
            ->set('outletFilter', (string) $this->outlet->id)
            ->set('periodMode', 'range')
            ->set('rangeFrom', $this->from->toDateString())
            ->set('rangeTo', $this->to->toDateString())
            ->set('scAmount', '2000')
            ->set("scSpecial.{$a->id}.amount", '50')
            ->set("scSpecial.{$a->id}.note", 'Till short')
            ->call('saveServiceCharge')
            ->assertHasNoErrors()
            ->assertDontSee('Not applied yet');

        // The note alone is a change: it is printed on the slip.
        $panel->set("scSpecial.{$a->id}.note", 'Till short 12 Jul')
            ->assertSee('Not applied yet: 1 special deduction');

        // Back as calculated, with the amount typed differently: nothing pending.
        $panel->set("scSpecial.{$a->id}.note", ' Till short ')
            ->set("scSpecial.{$a->id}.amount", '50.00')
            ->assertDontSee('Not applied yet');

        // Several kinds at once read as a list.
        $panel->set("scSpecial.{$a->id}.amount", '80')
            ->set("scManualLate.{$a->id}", '10')
            ->assertSee('Not applied yet: 1 late entry and 1 special deduction');
    }

    public function test_a_fund_allocation_edit_on_a_calculated_pool_says_it_is_not_applied_yet(): void
    {
        $this->staff('A');

        $user = User::factory()->create(['company_id' => $this->company->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge', 'hr.compensation']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $panel = Livewire::actingAs($user)
            ->test(ServiceCharge::class)
            ->set('outletFilter', (string) $this->outlet->id)
            ->set('periodMode', 'range')
            ->set('rangeFrom', $this->from->toDateString())
            ->set('rangeTo', $this->to->toDateString())
            ->set('scAmount', '2000')
            ->set('scFunds', [['name' => 'Outlet Fund', 'points' => '1']])
            ->call('saveServiceCharge')
            ->assertHasNoErrors()
            ->assertDontSee('Not applied yet')
            ->assertSee('= RM 1,000.00');

        // An empty row from "+ Add allocation" is not a change yet.
        $panel->call('addServiceChargeFund')->assertDontSee('Not applied yet');

        $panel->set('scFunds.0.points', '2')
            ->assertSee('Not applied yet: the allocations')
            ->assertDontSee('= RM 1,000.00');

        $panel->set('scFunds.0.points', '1.00')->assertDontSee('Not applied yet');

        $panel->call('removeServiceChargeFund', 0)->assertSee('Not applied yet: the allocations');
    }

    public function test_a_pool_setting_edit_on_a_calculated_pool_says_it_is_not_applied_yet(): void
    {
        $this->staff('A');

        $user = User::factory()->create(['company_id' => $this->company->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge', 'hr.compensation']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $panel = Livewire::actingAs($user)
            ->test(ServiceCharge::class)
            ->set('outletFilter', (string) $this->outlet->id)
            ->set('periodMode', 'range')
            ->set('rangeFrom', $this->from->toDateString())
            ->set('rangeTo', $this->to->toDateString())
            ->set('scAmount', '2000')
            ->set('scMcPercent', '5')
            ->call('saveServiceCharge')
            ->assertHasNoErrors()
            ->assertDontSee('Not applied yet');

        $panel->set('scAmount', '2500')
            ->set('scMcPercent', '6')
            ->assertSee('Not applied yet: the amount collected and the MC %');

        // Same numbers written differently are not a change.
        $panel->set('scAmount', '2000.00')->set('scMcPercent', '5.0')
            ->assertDontSee('Not applied yet');

        // Nor is a figure that is not a number the same as the saved one.
        $panel->set('scRetention', '')->assertSee('Not applied yet: the retention %');
    }
}
