<?php

namespace Tests\Feature;

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
 * Every employment status can be rendered on the employee list.
 *
 * REPORTED AS a 500 on changing a filter. Adding "Internship" to
 * Employee::EMPLOYMENT_STATUSES did not add a colour to the badge map in
 * employees.blade.php, and the lookup was unguarded — so one intern in the
 * results took the whole screen down with "Undefined array key".
 *
 * The employees PDF already carried a fallback and a comment saying not to
 * "break the PDF again", so this had happened once before in the other view.
 * The list now has the same fallback, and this walks EVERY status so the next
 * one added fails here instead of in production.
 */
class EmploymentStatusBadgeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Badge Co', 'slug' => Str::slug('Badge Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['hr.view', 'hr.employees.manage'] as $a) {
            Permission::findOrCreate($a, 'web');
        }
        $this->user->givePermissionTo(['hr.view', 'hr.employees.manage']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Every status in the constant must render, not just the ones with colours. */
    public function test_the_list_renders_an_employee_of_every_employment_status(): void
    {
        foreach (array_keys(Employee::EMPLOYMENT_STATUSES) as $i => $status) {
            Employee::create([
                'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
                'name' => 'STAFF ' . strtoupper($status), 'is_active' => true,
                'join_date' => '2025-01-01',
                'employment_status' => $status,
                'employment_status_date' => array_key_exists($status, Employee::EMPLOYMENT_STATUS_DATE_LABELS)
                    ? '2026-12-31' : null,
                'outsourcing_company' => $status === 'outsourcing' ? 'Experiva' : null,
            ]);
        }

        $html = Livewire::actingAs($this->user)
            ->test(Employees::class, ['outlet' => (string) $this->outlet->id, 'status' => 'all'])
            ->html();

        foreach (Employee::EMPLOYMENT_STATUSES as $status => $label) {
            $this->assertStringContainsString($label, $html,
                "The list must render a '$label' employee without falling over.");
        }
    }

    /** A status with no colour must degrade to a plain badge, not an exception. */
    public function test_a_status_with_no_badge_colour_still_renders(): void
    {
        Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'ODD ONE', 'is_active' => true, 'join_date' => '2025-01-01',
            // Not in the colour map, and deliberately not in the constant
            // either — this is the shape of the next status somebody adds.
            'employment_status' => 'secondment',
        ]);

        Livewire::actingAs($this->user)
            ->test(Employees::class, ['outlet' => (string) $this->outlet->id, 'status' => 'all'])
            ->assertOk();
    }

    /** Filtering to the new status is what the user was doing when it broke. */
    public function test_filtering_to_internship_works(): void
    {
        Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'THE INTERN', 'is_active' => true, 'join_date' => '2025-01-01',
            'employment_status' => 'internship', 'employment_status_date' => '2026-12-31',
        ]);

        $c = Livewire::actingAs($this->user)
            ->test(Employees::class, ['outlet' => (string) $this->outlet->id])
            ->set('employmentStatusFilter', 'internship');

        $c->assertOk();
        $this->assertSame(['THE INTERN'],
            $c->viewData('employees')->getCollection()->pluck('name')->all());
    }
}
