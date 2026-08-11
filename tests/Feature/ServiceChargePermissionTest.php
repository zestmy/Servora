<?php

namespace Tests\Feature;

use App\Livewire\Hr\AttendanceRecords;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Running the service charge does not require sight of everyone's salary.
 *
 * REPORTED AS: an Operations Manager holding the permission literally titled
 * "HR — Attendance & Service Charge" could not see the Service Charge button.
 * The screen gated it on hr.compensation, so the only way to let somebody
 * split a pool was to show them every basic salary in the company — and the
 * permission they had been granted promised the distribution in its own help
 * text.
 *
 * hr.attendance.service_charge is that ability. The pair of tests that matter
 * are the two halves of the split: the panel opens WITHOUT hr.compensation,
 * and salary stays hidden while it is open.
 */
class ServiceChargePermissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'SC Perm Co', 'slug' => Str::slug('SC Perm Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'AISYAH BINTI RAHMAN',
            'basic_salary' => 4321,
            'service_points_entitlement' => 2.5,
            'is_active'  => true,
        ]);

        foreach (['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge', 'hr.compensation'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /** @param array<int, string> $abilities */
    private function userWith(array $abilities): User
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

    /** The reported case: an Operations Manager's set of abilities. */
    public function test_the_service_charge_panel_opens_without_salary_access(): void
    {
        $user = $this->userWith(['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge']);

        Livewire::actingAs($user)
            ->test(AttendanceRecords::class)
            ->assertSee('Service Charge')
            ->set('showServiceCharge', true)
            ->assertSet('showServiceCharge', true)
            ->call('saveServiceCharge')
            ->assertOk();
    }

    /** The other half: the button arrives, the salary column does not. */
    public function test_salary_stays_hidden_while_the_panel_is_open(): void
    {
        $user = $this->userWith(['hr.attendance', 'hr.attendance.service_charge']);

        $html = Livewire::actingAs($user)
            ->test(AttendanceRecords::class)
            ->set('showServiceCharge', true)
            ->html();

        $this->assertStringNotContainsString('Basic Salary', $html);
        $this->assertStringNotContainsString('4,321', $html);
    }

    /**
     * The points ARE loaded for them, which is not a leak but the arithmetic:
     * stripping them would have shown every share as zero and looked like a
     * broken pool rather than a withheld figure.
     */
    public function test_the_distribution_can_still_be_computed(): void
    {
        $user = $this->userWith(['hr.attendance', 'hr.attendance.service_charge']);

        $employees = Livewire::actingAs($user)
            ->test(AttendanceRecords::class)
            ->set('showServiceCharge', true)
            ->viewData('employees');

        $this->assertEqualsWithDelta(
            2.5,
            (float) $employees->first()->service_points_entitlement,
            0.01,
            'Without service points every share computes as zero.'
        );
    }

    /** Attendance alone is not the service charge. */
    public function test_attendance_alone_does_not_open_the_panel(): void
    {
        $user = $this->userWith(['hr.attendance', 'hr.attendance.record']);

        Livewire::actingAs($user)
            ->test(AttendanceRecords::class)
            ->assertDontSee('Service Charge')
            ->call('saveServiceCharge')
            ->assertForbidden();
    }

    /**
     * hr.compensation on its own no longer carries it. Everyone who holds it
     * today is granted the new ability by the migration, so nobody loses the
     * screen — but the two are separate abilities from here on, and a role
     * created later gets exactly what it was ticked for.
     */
    public function test_salary_access_alone_does_not_open_the_panel(): void
    {
        $user = $this->userWith(['hr.attendance', 'hr.compensation']);

        Livewire::actingAs($user)
            ->test(AttendanceRecords::class)
            ->call('saveServiceCharge')
            ->assertForbidden();
    }
}
