<?php

namespace Tests\Feature;

use App\Livewire\Hr\ServiceCharge;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\PayrollRun;
use App\Models\ServiceChargePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Service Charge page lists the pools already saved, so the one to edit or
 * delete can be found without guessing its exact period back into the picker.
 */
class ServiceChargeSavedPoolsListTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Pool List Co', 'slug' => Str::slug('Pool List Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'AISYAH BINTI RAHMAN', 'is_active' => true, 'join_date' => '2025-01-01',
            'service_points_entitlement' => 1, 'basic_salary' => 2000, 'pay_type' => 'monthly',
        ]);

        foreach (['hr.view', 'hr.attendance', 'hr.attendance.service_charge',
                  'hr.attendance.service_charge.delete'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function pool(string $from, string $to, float $amount = 4000): ServiceChargePeriod
    {
        return ServiceChargePeriod::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_from' => $from, 'period_to' => $to,
            'amount' => $amount, 'retention_percent' => 0, 'mc_percent' => 0, 'abs_percent' => 0,
        ]);
    }

    /** @param array<int, string> $abilities */
    private function page(array $abilities)
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo($abilities);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return Livewire::actingAs($user)->test(ServiceCharge::class);
    }

    public function test_saved_pools_are_listed(): void
    {
        $this->pool('2026-07-01', '2026-07-31', 4321.50);
        $this->pool('2026-06-01', '2026-06-25', 1000);

        $this->page(['hr.view', 'hr.attendance', 'hr.attendance.service_charge'])
            ->assertSee('Saved pools')
            ->assertSee('01 Jul 2026 – 31 Jul 2026')
            ->assertSee('01 Jun 2026 – 25 Jun 2026')
            ->assertSee('4,321.50')
            ->assertSee('Not calculated');
    }

    public function test_opening_a_month_pool_loads_it_for_editing(): void
    {
        $pool = $this->pool('2026-07-01', '2026-07-31', 4321.50);

        $this->page(['hr.view', 'hr.attendance', 'hr.attendance.service_charge'])
            ->call('openPool', $pool->id)
            ->assertSet('periodMode', 'month')
            ->assertSet('month', '2026-07')
            ->assertSet('outletFilter', (string) $this->outlet->id)
            ->assertSet('scAmount', '4321.50');
    }

    public function test_opening_a_custom_range_pool_uses_its_exact_dates(): void
    {
        $pool = $this->pool('2026-06-26', '2026-07-25', 900);

        $this->page(['hr.view', 'hr.attendance', 'hr.attendance.service_charge'])
            ->call('openPool', $pool->id)
            ->assertSet('periodMode', 'range')
            ->assertSet('rangeFrom', '2026-06-26')
            ->assertSet('rangeTo', '2026-07-25')
            ->assertSet('scAmount', '900.00');
    }

    public function test_a_pool_can_be_deleted_from_the_list(): void
    {
        $pool = $this->pool('2026-07-01', '2026-07-31');

        $this->page(['hr.view', 'hr.attendance', 'hr.attendance.service_charge',
                     'hr.attendance.service_charge.delete'])
            ->call('deletePool', $pool->id);

        $this->assertDatabaseMissing('service_charge_periods', ['id' => $pool->id]);
    }

    public function test_deleting_needs_the_delete_ability(): void
    {
        $pool = $this->pool('2026-07-01', '2026-07-31');

        $this->page(['hr.view', 'hr.attendance', 'hr.attendance.service_charge'])
            ->call('deletePool', $pool->id)
            ->assertForbidden();

        $this->assertDatabaseHas('service_charge_periods', ['id' => $pool->id]);
    }

    public function test_a_pool_an_approved_run_paid_from_is_not_deleted(): void
    {
        $pool = $this->pool('2026-07-01', '2026-07-31');

        PayrollRun::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_month' => '2026-07-01',
            'period_start' => '2026-07-01', 'period_end' => '2026-07-31',
            'status' => PayrollRun::APPROVED,
        ]);

        $this->page(['hr.view', 'hr.attendance', 'hr.attendance.service_charge',
                     'hr.attendance.service_charge.delete'])
            ->call('deletePool', $pool->id)
            ->assertSee('approved payroll run', false);

        $this->assertDatabaseHas('service_charge_periods', ['id' => $pool->id]);
    }

    public function test_a_pool_from_another_company_is_neither_opened_nor_deleted(): void
    {
        $other = Company::create([
            'name' => 'Someone Else', 'slug' => 'someone-else-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $theirOutlet = Outlet::create([
            'company_id' => $other->id, 'name' => 'Theirs', 'code' => 'THR', 'is_active' => true,
        ]);
        $theirs = ServiceChargePeriod::create([
            'company_id' => $other->id, 'outlet_id' => $theirOutlet->id,
            'period_from' => '2026-07-01', 'period_to' => '2026-07-31',
            'amount' => 9999, 'retention_percent' => 0, 'mc_percent' => 0, 'abs_percent' => 0,
        ]);

        $this->page(['hr.view', 'hr.attendance', 'hr.attendance.service_charge',
                     'hr.attendance.service_charge.delete'])
            ->assertDontSee('9,999.00')
            ->call('openPool', $theirs->id)
            ->assertNotSet('outletFilter', (string) $theirOutlet->id)
            ->call('deletePool', $theirs->id);

        $this->assertDatabaseHas('service_charge_periods', ['id' => $theirs->id]);
    }
}
