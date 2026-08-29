<?php

namespace Tests\Feature;

use App\Livewire\Reports\Hr\ServiceChargePayout;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\PayrollRun;
use App\Models\ServiceChargePeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Seeing the saved service charge pools, and deleting one.
 *
 * The payout report already listed pools, because a pool exists only for the
 * exact from/to it was saved against and a typed date is no way to find one.
 * What it had no answer for was a pool entered against the wrong dates, or
 * twice, or for an outlet nobody meant: there was no way to remove it.
 *
 * Deleting is its own ability, and it is REFUSED while an approved payroll
 * run has paid from the pool — the payslips keep their own figures, but the
 * pool is the only record of how a point came to be worth what it was.
 */
class ServiceChargePoolDeleteTest extends TestCase
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
            'name' => 'Pool Del Co', 'slug' => Str::slug('Pool Del Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->from = Carbon::parse('2026-07-01');
        $this->to   = Carbon::parse('2026-07-31');

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

    private function pool(float $amount = 4000): ServiceChargePeriod
    {
        return ServiceChargePeriod::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_from' => $this->from->toDateString(), 'period_to' => $this->to->toDateString(),
            'amount' => $amount, 'retention_percent' => 0, 'mc_percent' => 0, 'abs_percent' => 0,
        ]);
    }

    /** @param array<int, string> $abilities */
    private function user(array $abilities): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo($abilities);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function report(array $abilities)
    {
        return Livewire::actingAs($this->user($abilities))->test(ServiceChargePayout::class);
    }

    public function test_a_saved_pool_is_listed_with_what_state_it_is_in(): void
    {
        $this->pool();

        $this->report(['hr.view', 'hr.attendance', 'hr.attendance.service_charge'])
            ->assertSee('01 Jul')
            ->assertSee('KLCC')
            ->assertSee('Not calculated');
    }

    public function test_a_pool_can_be_deleted(): void
    {
        $pool = $this->pool();

        $this->report(['hr.view', 'hr.attendance', 'hr.attendance.service_charge',
                       'hr.attendance.service_charge.delete'])
            ->assertSee('Delete this pool')
            ->call('delete', $pool->id);

        $this->assertDatabaseMissing('service_charge_periods', ['id' => $pool->id]);
    }

    /** The ability is its own, so holding the parent alone is not enough. */
    public function test_deleting_needs_the_delete_ability(): void
    {
        $pool = $this->pool();

        $this->report(['hr.view', 'hr.attendance', 'hr.attendance.service_charge'])
            ->assertDontSee('Delete this pool')
            ->call('delete', $pool->id)
            ->assertForbidden();

        $this->assertDatabaseHas('service_charge_periods', ['id' => $pool->id]);
    }

    /**
     * The refusal that matters. An approved run's payslips were paid from this
     * pool, and the pool is the working behind them.
     */
    public function test_a_pool_an_approved_run_paid_from_cannot_be_deleted(): void
    {
        $pool = $this->pool();

        PayrollRun::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_month' => '2026-07-01',
            'period_start' => $this->from->toDateString(),
            'period_end'   => $this->to->toDateString(),
            'status' => PayrollRun::APPROVED,
        ]);

        $this->report(['hr.view', 'hr.attendance', 'hr.attendance.service_charge',
                       'hr.attendance.service_charge.delete'])
            ->call('delete', $pool->id)
            ->assertSee('approved payroll run', false);

        // Still there: an approved run had already paid from it.
        $this->assertDatabaseHas('service_charge_periods', ['id' => $pool->id]);
    }

    /** A draft does not refuse — nothing has been paid — but it is named. */
    public function test_a_draft_run_warns_but_does_not_refuse(): void
    {
        $pool = $this->pool();

        PayrollRun::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_month' => '2026-07-01',
            'period_start' => $this->from->toDateString(),
            'period_end'   => $this->to->toDateString(),
            'status' => PayrollRun::DRAFT,
        ]);

        $component = $this->report(['hr.view', 'hr.attendance', 'hr.attendance.service_charge',
                                    'hr.attendance.service_charge.delete']);

        $component->assertSee('draft payroll')->call('delete', $pool->id);

        $this->assertDatabaseMissing('service_charge_periods', ['id' => $pool->id]);
    }

    /**
     * An id arriving from a browser must not reach past the query that scopes
     * pools to this company and this user's outlets.
     */
    public function test_a_pool_from_another_company_cannot_be_deleted(): void
    {
        $other = Company::create([
            'name' => 'Someone Else', 'slug' => Str::slug('Someone Else') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $otherOutlet = Outlet::create([
            'company_id' => $other->id, 'name' => 'Theirs', 'code' => 'THR', 'is_active' => true,
        ]);
        $theirs = ServiceChargePeriod::create([
            'company_id' => $other->id, 'outlet_id' => $otherOutlet->id,
            'period_from' => $this->from->toDateString(), 'period_to' => $this->to->toDateString(),
            'amount' => 9999, 'retention_percent' => 0, 'mc_percent' => 0, 'abs_percent' => 0,
        ]);

        $this->report(['hr.view', 'hr.attendance', 'hr.attendance.service_charge',
                       'hr.attendance.service_charge.delete'])
            ->call('delete', $theirs->id);

        $this->assertDatabaseHas('service_charge_periods', ['id' => $theirs->id]);
    }
}
