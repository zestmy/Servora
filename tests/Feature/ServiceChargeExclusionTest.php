<?php

namespace Tests\Feature;

use App\Livewire\Hr\AttendanceRecords;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\ServiceChargePeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Anybody the pool pays can be excluded from it, not only leavers.
 *
 * REPORTED AS: the exclusion tick was not available for everyone on the
 * employee list. It was offered for resigned staff alone, on the reasoning
 * that a leaver is the usual case and anyone still employed should be handled
 * by a special deduction. That is a different instrument: a special deduction
 * takes money off ONE person's share, while an exclusion takes their points
 * out of the divisor and gives the pool back to everybody else.
 *
 * What still has to hold is REACH — the ids arrive from a browser, so a
 * request must not be able to exclude somebody this pool does not pay and
 * re-price a rate in an outlet the user cannot see.
 */
class ServiceChargeExclusionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Outlet $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Exclusion Co', 'slug' => Str::slug('Exclusion Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->other = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'IOI', 'code' => 'IOI', 'is_active' => true,
        ]);

        foreach (['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function staff(string $name, float $points, ?Outlet $outlet = null): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => ($outlet ?? $this->outlet)->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2025-01-01',
            'employment_status' => 'confirmed',
            'service_points_entitlement' => $points,
            'basic_salary' => 2000, 'pay_type' => 'monthly',
        ]);
    }

    private function manager(): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id, $this->other->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /** The panel, pointed at KLCC for the current month. */
    private function panel(User $user)
    {
        return Livewire::actingAs($user)
            ->test(AttendanceRecords::class)
            ->set('outletFilter', (string) $this->outlet->id)
            ->set('showServiceCharge', true)
            ->set('scAmount', '2000');
    }

    public function test_an_employee_still_on_the_books_can_be_excluded(): void
    {
        $stays   = $this->staff('Stays', 10);
        $dropped = $this->staff('Dropped', 10);

        $this->panel($this->manager())
            ->set('scExcluded.' . $dropped->id, true)
            ->call('saveServiceCharge')
            ->assertHasNoErrors();

        $pool = ServiceChargePeriod::withoutGlobalScopes()
            ->where('company_id', $this->company->id)->firstOrFail();

        $this->assertSame([$dropped->id], $pool->excludedEmployeeIds(),
            'The tick used to be dropped for anyone who had not resigned.');

        $rows = collect($pool->distribution['rows']);

        $this->assertSame(0.0, (float) $rows[(string) $dropped->id]['net']);
        $this->assertEquals(2000.0, (float) $rows[(string) $stays->id]['net'],
            'Their points leave the divisor, so the pool goes to the rest rather than being left short.');
    }

    public function test_somebody_this_pool_does_not_pay_cannot_be_excluded_from_it(): void
    {
        $this->staff('KLCC Staff', 10);
        $elsewhere = $this->staff('IOI Staff', 10, $this->other);

        $this->panel($this->manager())
            ->set('scExcluded.' . $elsewhere->id, true)
            ->call('saveServiceCharge')
            ->assertHasNoErrors();

        $pool = ServiceChargePeriod::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('outlet_id', $this->outlet->id)
            ->firstOrFail();

        $this->assertSame([], $pool->excludedEmployeeIds(),
            'A tick for somebody another pool pays must not re-price this one.');
    }

    public function test_an_exclusion_already_recorded_survives_a_re_save(): void
    {
        $this->staff('Stays', 10);
        $dropped = $this->staff('Dropped', 10);

        $month = Carbon::now()->startOfMonth();

        /*
         * Seeded through the query builder rather than by saving the panel
         * twice, and the dates go in DATE-ONLY on purpose: these are `date`
         * columns, which MySQL stores as dates, but SQLite keeps whatever
         * string it is handed — and the model's datetime cast hands it
         * "2026-08-01 00:00:00", which the panel's own `= '2026-08-01'`
         * lookup then misses. That is a storage difference between the test
         * database and the real one, not the behaviour under test.
         */
        DB::table('service_charge_periods')->insert([
            'company_id'  => $this->company->id,
            'outlet_id'   => $this->outlet->id,
            'period_from' => $month->toDateString(),
            'period_to'   => $month->copy()->endOfMonth()->toDateString(),
            'amount'      => 2000, 'retention_percent' => 0,
            'mc_percent'  => 5, 'abs_percent' => 10, 'min_working_days' => 0,
            'excluded_employees' => json_encode([$dropped->id]),
            'created_at'  => now(), 'updated_at' => now(),
        ]);

        // Moved to the other outlet afterwards: the decision was about THIS
        // period and must not reverse itself the next time it is saved.
        $dropped->update(['outlet_id' => $this->other->id]);

        $this->panel($this->manager())
            ->set('scExcluded.' . $dropped->id, true)
            ->call('saveServiceCharge')
            ->assertHasNoErrors();

        $pool = ServiceChargePeriod::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('outlet_id', $this->outlet->id)
            ->firstOrFail();

        $this->assertSame([$dropped->id], $pool->excludedEmployeeIds());
    }
}
