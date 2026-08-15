<?php

namespace Tests\Feature;

use App\Livewire\Hr\EmployeeForm;
use App\Models\Company;
use App\Models\Employee;
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
 * The employee photo can be changed, and a bad file cannot kill the form.
 *
 * THE REPORT was "I cannot remove or change the photo — when saved it's back
 * to the previous one", and every word of it was a symptom of one line: the
 * blade calls temporaryUrl() to draw the preview, and for an iPhone HEIC that
 * call THROWS. The render after choosing the file 500s, the chosen photo is
 * lost, the old one stays — and because the poisoned property rides along in
 * the component state, every later request dies on the same line, which is why
 * Remove looked broken too.
 *
 * So what these tests pin is not "uploads work" but the failure shape: an
 * unreadable file must produce a validation error and a live form, never an
 * exception.
 */
class EmployeePhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $employee;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->company = Company::create([
            'name' => 'Photo Co', 'slug' => Str::slug('Photo Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'AAA STAFF', 'is_active' => true, 'join_date' => '2025-01-01',
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['hr.view', 'hr.employees.manage'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(['hr.view', 'hr.employees.manage']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** A normal photo lands, previews, and the form stays clean. */
    public function test_a_jpeg_photo_is_accepted_and_previewed(): void
    {
        Livewire::actingAs($this->user)
            ->test(EmployeeForm::class, ['id' => $this->employee->id])
            ->set('photo', UploadedFile::fake()->image('face.jpg', 400, 400))
            ->assertHasNoErrors()
            ->assertOk();
    }

    /**
     * The case from the report. An iPhone HEIC that cannot be converted must
     * come back as a VALIDATION ERROR on a working form — the old behaviour
     * was an exception from temporaryUrl(), which killed this render and every
     * one after it.
     */
    public function test_an_unreadable_heic_errors_politely_instead_of_killing_the_form(): void
    {
        $form = Livewire::actingAs($this->user)
            ->test(EmployeeForm::class, ['id' => $this->employee->id])
            // Garbage bytes wearing a .heic name — which is also what a
            // corrupt real-world transfer looks like.
            ->set('photo', UploadedFile::fake()->create('face.heic', 500, 'image/heic'))
            ->assertOk();

        /*
         * Environment split, both acceptable and both asserted: without
         * Imagick (or without its HEIC delegate) the file is rejected with the
         * iPhone tip; with a working Imagick the conversion of garbage fails
         * and the same rejection lands. Either way: an error, not a throw.
         */
        $this->assertTrue(
            $form->errors()->has('photo'),
            'An unconvertible HEIC must produce a validation error, not pass silently.'
        );

        // And the form is still alive: the next interaction works rather than
        // dying on a poisoned preview — which is the actual regression.
        $form->set('f_name', 'AAA STAFF RENAMED')->assertOk();
    }

    /** Remove is immediate: row cleared, file gone, no Save required. */
    public function test_remove_photo_clears_the_record_and_the_disk(): void
    {
        Storage::disk('local')->put('employee-photos/old.jpg', 'bytes');
        $this->employee->update(['photo_path' => 'employee-photos/old.jpg']);

        Livewire::actingAs($this->user)
            ->test(EmployeeForm::class, ['id' => $this->employee->id])
            ->call('removePhoto')
            ->assertOk()
            ->assertSet('photoPath', null);

        $this->assertNull($this->employee->fresh()->photo_path);
        Storage::disk('local')->assertMissing('employee-photos/old.jpg');
    }
}
