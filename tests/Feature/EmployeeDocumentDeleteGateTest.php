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
    public function test_uploading_still_works_without_the_delete_ability(): void
    {
        $user = $this->user(['hr.view', 'hr.employees.manage']);

        Livewire::actingAs($user)->test(EmployeeForm::class, ['id' => $this->employee->id])
            ->set('docType', 'identity')
            ->set('docFile', \Illuminate\Http\UploadedFile::fake()->create('new.pdf', 8, 'application/pdf'))
            ->call('uploadDocument')
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
     * The backfill rule: nobody loses a capability the moment this deploys.
     * A permission that silently strips access is the worse failure — the
     * button just stops being there and nothing says why.
     */
    public function test_the_backfill_gave_it_to_everyone_who_could_already_delete(): void
    {
        $manage = \Spatie\Permission\Models\Permission::where('name', 'hr.employees.manage')->first();
        $target = \Spatie\Permission\Models\Permission::where('name', self::ABILITY)->first();

        $this->assertNotNull($manage, 'hr.employees.manage must exist for the backfill to key off.');
        $this->assertNotNull($target, 'The migration must create the ability.');

        $manageRoles = \Illuminate\Support\Facades\DB::table('role_has_permissions')
            ->where('permission_id', $manage->id)->pluck('role_id')->sort()->values()->all();
        $targetRoles = \Illuminate\Support\Facades\DB::table('role_has_permissions')
            ->where('permission_id', $target->id)->pluck('role_id')->sort()->values()->all();

        $this->assertSame($manageRoles, $targetRoles);
    }
}
