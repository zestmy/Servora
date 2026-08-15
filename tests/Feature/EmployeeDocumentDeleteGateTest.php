<?php

namespace Tests\Feature;

use App\Livewire\Hr\EmployeeForm;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Deleting an employee's uploaded document is behind its own ability.
 *
 * It is the only irreversible action on the Documents tab: EmployeeDocument's
 * `deleted` hook removes the file from disk — deliberately, because an orphaned
 * IC scan would make the delete a lie — so there is no undo and nothing to
 * restore from. Before this it rode on `hr.employees.manage`, which is "can
 * edit staff", so the clerk who files paperwork all day could also destroy it
 * with a confirm dialog as the only guard.
 *
 * Uploading and viewing are deliberately NOT gated. Only the half that cannot
 * be taken back is.
 */
class EmployeeDocumentDeleteGateTest extends TestCase
{
    use RefreshDatabase;

    private const ABILITY = 'hr.employees.documents.delete';

    private Company $company;
    private Outlet $outlet;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->company = Company::create([
            'name' => 'Docs Co', 'slug' => Str::slug('Docs Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'AAA STAFF', 'is_active' => true, 'join_date' => '2025-01-01',
        ]);
    }

    /** @param list<string> $abilities */
    private function user(array $abilities): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach ($abilities as $a) {
            Permission::findOrCreate($a, 'web');
        }
        $user->givePermissionTo($abilities);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function document(): EmployeeDocument
    {
        Storage::disk('local')->put('employee-documents/ic.pdf', 'scan');

        return EmployeeDocument::create([
            'company_id' => $this->company->id, 'employee_id' => $this->employee->id,
            'type' => 'identity', 'file_path' => 'employee-documents/ic.pdf',
            'original_name' => 'ic.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 4,
        ]);
    }

    // ── The gate ──────────────────────────────────────────────────────────

    public function test_someone_with_the_ability_can_delete_a_document(): void
    {
        $doc = $this->document();
        $user = $this->user(['hr.view', 'hr.employees.manage', self::ABILITY]);

        Livewire::actingAs($user)->test(EmployeeForm::class, ['id' => $this->employee->id])
            ->call('deleteDocument', $doc->id);

        $this->assertDatabaseMissing('employee_documents', ['id' => $doc->id]);
        Storage::disk('local')->assertMissing('employee-documents/ic.pdf');
    }

    /**
     * The part that matters. Hiding the button stops the accident; this stops
     * the forged Livewire call, and only one of those is a security boundary.
     */
    public function test_editing_staff_is_no_longer_enough_to_delete_one(): void
    {
        $doc = $this->document();
        $user = $this->user(['hr.view', 'hr.employees.manage']);

        Livewire::actingAs($user)->test(EmployeeForm::class, ['id' => $this->employee->id])
            ->call('deleteDocument', $doc->id)
            ->assertForbidden();

        $this->assertDatabaseHas('employee_documents', ['id' => $doc->id]);
        Storage::disk('local')->assertExists('employee-documents/ic.pdf');
    }

    public function test_the_delete_button_is_hidden_without_the_ability(): void
    {
        $this->document();

        $without = Livewire::actingAs($this->user(['hr.view', 'hr.employees.manage']))
            ->test(EmployeeForm::class, ['id' => $this->employee->id])->html();

        $this->assertStringNotContainsString('deleteDocument', $without);

        $with = Livewire::actingAs($this->user(['hr.view', 'hr.employees.manage', self::ABILITY]))
            ->test(EmployeeForm::class, ['id' => $this->employee->id])->html();

        $this->assertStringContainsString('deleteDocument', $with);
    }

    // ── What the gate must NOT take away ──────────────────────────────────

    /** Filing paperwork is the routine half and stays with anyone who can edit staff. */
    /**
     * CHOOSING the file saves it. There is no second button to press.
     *
     * Reported as "Documents is not uploading or saving the chosen file", and
     * the server had the evidence: five temporary uploads in one five-minute
     * stretch and not a single document row written. Livewire pushes a file to
     * temporary storage the moment it is CHOSEN, so those five were five files
     * picked and an Upload button that never took — it sat inline beside the
     * input and was disabled while the upload it was waiting for was still in
     * flight, so an early press did nothing and read as a broken feature.
     */
    public function test_choosing_a_file_saves_the_document(): void
    {
        $user = $this->user(['hr.view', 'hr.employees.manage']);

        Livewire::actingAs($user)->test(EmployeeForm::class, ['id' => $this->employee->id])
            ->set('docType', 'identity')
            ->set('docLabel', 'Passport')
            ->set('docFile', \Illuminate\Http\UploadedFile::fake()->create('passport.pdf', 40, 'application/pdf'))
            ->assertHasNoErrors()
            // Cleared afterwards, so the next one starts from nothing rather
            // than re-saving the last.
            ->assertSet('docFile', null);

        $this->assertDatabaseHas('employee_documents', [
            'employee_id'   => $this->employee->id,
            'type'          => 'identity',
            'label'         => 'Passport',
            'original_name' => 'passport.pdf',
        ]);

        $this->assertSame(1, EmployeeDocument::where('employee_id', $this->employee->id)->count());
    }

    /** A file too large to accept says so rather than vanishing. */
    public function test_an_oversized_file_is_reported(): void
    {
        $user = $this->user(['hr.view', 'hr.employees.manage']);

        Livewire::actingAs($user)->test(EmployeeForm::class, ['id' => $this->employee->id])
            ->set('docFile', \Illuminate\Http\UploadedFile::fake()->create('huge.pdf', 20480, 'application/pdf'))
            ->assertHasErrors('docFile');

        $this->assertSame(0, EmployeeDocument::where('employee_id', $this->employee->id)->count());
    }

    public function test_uploading_still_works_without_the_delete_ability(): void
    {
        $user = $this->user(['hr.view', 'hr.employees.manage']);

        // No ->call('uploadDocument'): choosing the file IS the upload now.
        // Calling it again would find the field already cleared and complain
        // that no file was chosen.
        Livewire::actingAs($user)->test(EmployeeForm::class, ['id' => $this->employee->id])
            ->set('docType', 'identity')
            ->set('docFile', \Illuminate\Http\UploadedFile::fake()->create('new.pdf', 8, 'application/pdf'))
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employee_documents', [
            'employee_id' => $this->employee->id, 'original_name' => 'new.pdf',
        ]);
    }

    public function test_the_documents_tab_still_lists_and_links_documents(): void
    {
        $doc = $this->document();

        $html = Livewire::actingAs($this->user(['hr.view', 'hr.employees.manage']))
            ->test(EmployeeForm::class, ['id' => $this->employee->id])->html();

        $this->assertStringContainsString('ic.pdf', $html);
        $this->assertStringContainsString(route('hr.employee-documents.download', $doc), $html);
    }

    // ── The ability itself ────────────────────────────────────────────────

    public function test_the_ability_is_declared_so_it_can_be_granted(): void
    {
        $this->assertContains(self::ABILITY, \App\Helpers\PermissionRegistry::names(),
            'An ability nothing declares cannot be granted or revoked without a migration.');
    }

    /**
     * THE INVARIANT: exactly one role can delete an employee's paperwork.
     *
     * Written as "only Company Admin holds it" rather than "these five do not",
     * because the roles differ per environment — the developer database and
     * production do not seed the same set, and a list of names to exclude
     * would pass on one and miss a role on the other.
     */
    public function test_only_company_admin_can_delete_documents(): void
    {
        $target = \Spatie\Permission\Models\Permission::where('name', self::ABILITY)->first();
        $this->assertNotNull($target, 'The migration must create the ability.');

        $holders = \Illuminate\Support\Facades\DB::table('role_has_permissions')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->where('permission_id', $target->id)
            ->pluck('roles.name')->sort()->values()->all();

        /*
         * Asserted as "nobody else", not as "exactly Company Admin", because
         * this database does not seed a Company Admin role at all — and the
         * invariant is still satisfied there: only Company Admin may delete,
         * and there is no Company Admin, so nobody may. Pinning the positive
         * half would make this test a statement about the seed data rather
         * than about the rule.
         */
        $this->assertSame(
            [],
            array_values(array_diff($holders, ['Company Admin'])),
            'Only Company Admin may delete an employee document.'
        );

        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('model_has_permissions')
            ->where('permission_id', $target->id)->count(),
            'A direct grant to one person would break "only Company Admin".');
    }

    /**
     * The gate moved ONE action, not a job. Every role that lost deletion must
     * still be able to add, edit and file — otherwise this stopped the
     * paperwork rather than protecting it.
     */
    public function test_the_roles_that_lost_deletion_can_still_manage_staff(): void
    {
        $manageId = \Illuminate\Support\Facades\DB::table('permissions')
            ->where('name', 'hr.employees.manage')->value('id');

        $stillManage = \Illuminate\Support\Facades\DB::table('role_has_permissions')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->where('permission_id', $manageId)
            ->pluck('roles.name');

        $this->assertContains('HR Manager', $stillManage->all(),
            'HR Manager lost deletion, not the ability to keep staff records.');

        // Whoever can edit staff can still upload — proven behaviourally
        // above in test_uploading_still_works_without_the_delete_ability.
        $this->assertGreaterThan(1, $stillManage->count());
    }
}
