<?php

namespace Tests\Feature;

use App\Livewire\Hr\EmployeeForm;
use App\Livewire\Hr\Employees;
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
 * Saving an employee returns you to the list you came from.
 *
 * REPORTED AS: after saving, the list came back on the default filter rather
 * than the outlet being worked through. The redirect carried nothing, and the
 * list defaults its filter to the EDITOR's own active outlet — so somebody
 * going through another branch's staff had to re-pick that branch after every
 * single record.
 *
 * "All outlets" is a value here, not an absence. Somebody who widened the list
 * on purpose was the worst affected: an empty filter is indistinguishable from
 * "no filter given", so they were narrowed back to their own outlet every
 * time.
 */
class EmployeeReturnOutletTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $mine;
    private Outlet $theirs;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Return Co', 'slug' => Str::slug('Return Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->mine   = $this->outlet('Mine', 'MINE');
        $this->theirs = $this->outlet('Theirs', 'THRS');

        $this->employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->theirs->id,
            'name' => 'AISYAH BINTI RAHMAN', 'is_active' => true,
        ]);

        foreach (['hr.view', 'hr.employees.manage'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    /** Their active outlet is "Mine" — the one they kept being sent back to. */
    private function manager(): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->mine->id, $this->theirs->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.view', 'hr.employees.manage']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /** The reported case: editing from another branch's list. */
    public function test_saving_returns_to_the_outlet_you_were_working_through(): void
    {
        Livewire::actingAs($this->manager())
            ->test(EmployeeForm::class, ['id' => $this->employee->id, 'outlet' => (string) $this->theirs->id])
            ->set('f_designation', 'Sous Chef')
            ->call('save')
            ->assertRedirect(route('hr.employees', ['outlet' => $this->theirs->id]));
    }

    /** And the list opens on it rather than on the editor's own outlet. */
    public function test_the_list_opens_on_the_outlet_it_is_handed(): void
    {
        Livewire::actingAs($this->manager())
            ->test(Employees::class, ['outlet' => (string) $this->theirs->id])
            ->assertSet('outletFilter', (string) $this->theirs->id);
    }

    /**
     * The case an empty string cannot express: somebody who deliberately
     * widened the list must not be narrowed again on the way back.
     */
    public function test_all_outlets_survives_the_round_trip(): void
    {
        Livewire::actingAs($this->manager())
            ->test(EmployeeForm::class, ['id' => $this->employee->id, 'outlet' => 'all'])
            ->set('f_designation', 'Sous Chef')
            ->call('save')
            ->assertRedirect(route('hr.employees', ['outlet' => 'all']));

        Livewire::actingAs($this->manager())
            ->test(Employees::class, ['outlet' => 'all'])
            ->assertSet('outletFilter', '');
    }

    /**
     * Reached directly — a bookmark, or a link in an email — there is no list
     * to go back to, so it falls back to the one this record is actually on.
     * Landing anywhere else looks like the save did not happen.
     */
    public function test_a_form_opened_directly_returns_to_the_employees_own_outlet(): void
    {
        Livewire::actingAs($this->manager())
            ->test(EmployeeForm::class, ['id' => $this->employee->id])
            ->set('f_designation', 'Sous Chef')
            ->call('save')
            ->assertRedirect(route('hr.employees', ['outlet' => $this->theirs->id]));
    }

    /** With nothing named at all, the list still defaults as it always did. */
    public function test_the_list_still_defaults_to_your_own_outlet(): void
    {
        $user = $this->manager();
        $user->update(['company_id' => $this->company->id]);

        Livewire::actingAs($user)
            ->test(Employees::class)
            ->assertSet('outletFilter', (string) ($user->activeOutletId() ?: ''));
    }
}
