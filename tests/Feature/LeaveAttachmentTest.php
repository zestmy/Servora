<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The medical certificate attached to the leave it justifies.
 *
 * WHICH TYPES ASK FOR ONE IS DATA. Leave types are per-company and editable —
 * a company may call its sick leave something else, or want a document against
 * compassionate leave — so the offer follows a flag on the type rather than a
 * hardcoded "if the code is MC".
 *
 * OFFERED, NEVER DEMANDED. A clinic hands out a paper slip and it gets
 * photographed when somebody is well enough to think of it. Refusing to record
 * Friday's absence until the picture exists means the absence goes unrecorded,
 * which is the worse failure — so the upload is optional and can follow.
 *
 * PRIVATE. An MC carries a name, a clinic, a date and often a diagnosis, so it
 * is served through a controller that checks rather than linked from a public
 * disk.
 */
class LeaveAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $employee;
    private LeaveType $medical;
    private LeaveType $annual;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->company = Company::create([
            'name' => 'MC Co', 'slug' => Str::slug('MC Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'AISYAH BINTI RAHMAN', 'is_active' => true,
        ]);

        $this->medical = LeaveType::create([
            'company_id' => $this->company->id, 'name' => 'Medical Leave', 'code' => 'MC',
            'default_days' => 14, 'requires_attachment' => true, 'requires_approval' => true, 'is_active' => true,
        ]);

        $this->annual = LeaveType::create([
            'company_id' => $this->company->id, 'name' => 'Annual Leave', 'code' => 'AL',
            'default_days' => 8, 'requires_attachment' => false, 'requires_approval' => true, 'is_active' => true,
        ]);

        foreach (['hr.leave', 'hr.leave.approve', 'hr.view'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function manager(): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.leave', 'hr.view']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function request(bool $withFile = false): LeaveRequest
    {
        return LeaveRequest::create([
            'company_id'    => $this->company->id,
            'employee_id'   => $this->employee->id,
            'leave_type_id' => $this->medical->id,
            'start_date'    => '2026-08-03',
            'end_date'      => '2026-08-04',
            'days'          => 2,
            'status'        => LeaveRequest::PENDING,
            'attachment_path' => $withFile ? 'leave-attachments/x/y/file.jpg' : null,
            'attachment_name' => $withFile ? 'mc.jpg' : null,
        ]);
    }

    /** The seeded medical types ask for one; annual leave does not. */
    public function test_only_the_types_that_ask_for_one_offer_the_upload(): void
    {
        $this->assertTrue($this->medical->requires_attachment);
        $this->assertFalse($this->annual->requires_attachment);
    }

    public function test_a_certificate_uploaded_with_the_application_is_stored_privately(): void
    {
        $user = $this->manager();

        Livewire::actingAs($user)
            ->test(\App\Livewire\Hr\Leave::class)
            ->set('a_employee', (string) $this->employee->id)
            ->set('a_type', (string) $this->medical->id)
            ->set('a_start', '2026-08-03')
            ->set('a_end', '2026-08-04')
            ->set('a_days', '2')
            ->set('a_attachment', UploadedFile::fake()->image('mc.jpg'))
            ->call('apply')
            ->assertHasNoErrors();

        $created = LeaveRequest::where('employee_id', $this->employee->id)->latest('id')->first();

        $this->assertNotNull($created->attachment_path, 'The certificate must be recorded against the request.');
        $this->assertSame('mc.jpg', $created->attachment_name);
        Storage::disk('local')->assertExists($created->attachment_path);
    }

    /** The whole point of "option": an absence records without one. */
    public function test_leave_still_applies_without_a_certificate(): void
    {
        Livewire::actingAs($this->manager())
            ->test(\App\Livewire\Hr\Leave::class)
            ->set('a_employee', (string) $this->employee->id)
            ->set('a_type', (string) $this->medical->id)
            ->set('a_start', '2026-08-03')
            ->set('a_end', '2026-08-04')
            ->set('a_days', '2')
            ->call('apply')
            ->assertHasNoErrors();

        $this->assertNull(
            LeaveRequest::where('employee_id', $this->employee->id)->latest('id')->first()->attachment_path
        );
    }

    /** The form says it can follow, so it has to be able to. */
    public function test_a_certificate_can_be_attached_afterwards(): void
    {
        $request = $this->request();

        Livewire::actingAs($this->manager())
            ->test(\App\Livewire\Hr\Leave::class)
            ->call('attachTo', $request->id)
            ->set('attachFile', UploadedFile::fake()->image('late-mc.jpg'))
            ->call('saveAttachment')
            ->assertHasNoErrors();

        $request->refresh();

        $this->assertNotNull($request->attachment_path);
        Storage::disk('local')->assertExists($request->attachment_path);
    }

    /** A re-upload replaces rather than accumulating files nobody can see. */
    public function test_replacing_a_certificate_removes_the_old_file(): void
    {
        $request = $this->request();

        $component = Livewire::actingAs($this->manager())->test(\App\Livewire\Hr\Leave::class);

        $component->call('attachTo', $request->id)
            ->set('attachFile', UploadedFile::fake()->image('first.jpg'))
            ->call('saveAttachment');

        $first = $request->fresh()->attachment_path;

        $component->call('attachTo', $request->id)
            ->set('attachFile', UploadedFile::fake()->image('second.jpg'))
            ->call('saveAttachment');

        $second = $request->fresh()->attachment_path;

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    }

    /** A spreadsheet is not a medical certificate. */
    public function test_an_unacceptable_file_is_refused(): void
    {
        $request = $this->request();

        Livewire::actingAs($this->manager())
            ->test(\App\Livewire\Hr\Leave::class)
            ->call('attachTo', $request->id)
            ->set('attachFile', UploadedFile::fake()->create('numbers.xlsx', 100))
            ->call('saveAttachment')
            ->assertHasErrors('attachFile');

        $this->assertNull($request->fresh()->attachment_path);
    }

    /**
     * The document is only as private as the record it hangs off — outlet
     * scope, exactly as the employee's own record is behind.
     */
    public function test_a_manager_from_another_outlet_cannot_read_it(): void
    {
        $request = $this->request(withFile: true);

        $elsewhere = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Other', 'code' => 'OTH', 'is_active' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$elsewhere->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.leave', 'hr.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user)
            ->get(route('hr.leave.attachment', $request))
            ->assertForbidden();
    }

    public function test_a_user_without_hr_leave_cannot_read_it(): void
    {
        $request = $this->request(withFile: true);

        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);

        $this->actingAs($user)
            ->get(route('hr.leave.attachment', $request))
            ->assertForbidden();
    }

    /** A request with nothing attached is a 404, not an empty file. */
    public function test_a_request_with_no_certificate_is_not_found(): void
    {
        $this->actingAs($this->manager())
            ->get(route('hr.leave.attachment', $this->request()))
            ->assertNotFound();
    }
}
