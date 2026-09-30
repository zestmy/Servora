<?php

namespace Tests\Feature;

use App\Livewire\Hr\Employees;
use App\Livewire\Hr\OvertimeClaims;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Screens that READ staff (the staff list under hr.view, OT claims under
 * hr.claims) used to carry write actions guarded only by the page route. Each
 * write now checks the ability that owns it: standing (hr.employment), staff
 * records (hr.employees.manage), removal (hr.employees.delete).
 */
class HrEmployeesPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Staff Perm Co', 'slug' => Str::slug('Staff Perm Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);
        $this->employee = Employee::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Aisyah', 'is_active' => true,
        ]);

        foreach (['hr.view', 'hr.employment', 'hr.employees.manage', 'hr.employees.delete', 'hr.claims'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function user(array $abilities): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo($abilities);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function csv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'staff.csv',
            "Outlet,Employee Name,Employment Status\nMain,Imported Person,Resigned\n"
        );
    }

    public function test_a_viewer_cannot_deactivate_staff(): void
    {
        Livewire::actingAs($this->user(['hr.view']))
            ->test(Employees::class)
            ->call('toggleActive', $this->employee->id)
            ->assertForbidden();

        $this->assertTrue((bool) $this->employee->fresh()->is_active);
    }

    public function test_employment_standing_may_deactivate_staff(): void
    {
        Livewire::actingAs($this->user(['hr.view', 'hr.employment']))
            ->test(Employees::class)
            ->call('toggleActive', $this->employee->id)
            ->assertOk();

        $this->assertFalse((bool) $this->employee->fresh()->is_active);
    }

    public function test_a_viewer_cannot_import_staff(): void
    {
        Livewire::actingAs($this->user(['hr.view']))
            ->test(Employees::class)
            ->set('csvFile', $this->csv())
            ->call('processImport')
            ->assertForbidden();

        $this->assertFalse(Employee::withoutGlobalScopes()->where('name', 'Imported Person')->exists());
    }

    public function test_a_staff_editor_can_import_but_not_set_employment_standing(): void
    {
        Livewire::actingAs($this->user(['hr.view', 'hr.employees.manage']))
            ->test(Employees::class)
            ->set('csvFile', $this->csv())
            ->call('processImport')
            ->assertOk();

        $imported = Employee::withoutGlobalScopes()->where('name', 'Imported Person')->first();
        $this->assertNotNull($imported, 'hr.employees.manage may import staff.');
        $this->assertNull($imported->employment_status, 'Standing columns need hr.employment.');
        $this->assertTrue((bool) $imported->is_active);
    }

    /*
     * The OT screen is driven directly rather than through Livewire::test():
     * its render runs a raw-MySQL weekly aggregate (WEEKDAY / DATE_SUB) that
     * SQLite cannot execute — same approach as OtClaimDuplicateTest.
     */

    public function test_a_claims_user_cannot_delete_staff_from_the_ot_screen(): void
    {
        $this->actingAs($this->user(['hr.claims']));

        try {
            (new OvertimeClaims())->deleteEmployee($this->employee->id);
            $this->fail('hr.claims alone deleted an employee.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertNotNull(Employee::withoutGlobalScopes()->find($this->employee->id));
    }

    public function test_the_delete_ability_can_remove_staff_from_the_ot_screen(): void
    {
        $this->actingAs($this->user(['hr.claims', 'hr.employees.delete']));

        (new OvertimeClaims())->deleteEmployee($this->employee->id);

        // Soft-deleted, like every other employee removal.
        $this->assertTrue(Employee::withoutGlobalScopes()->find($this->employee->id)->trashed());
    }
}
