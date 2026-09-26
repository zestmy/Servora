<?php

namespace Tests\Feature;

use App\Livewire\Audits\FindingActions;
use App\Livewire\Staff\CorrectiveActions as StaffCorrectiveActions;
use App\Models\AuditFinding;
use App\Models\AuditTemplate;
use App\Models\AuditTemplateItem;
use App\Models\AuditTemplateSection;
use App\Models\Company;
use App\Models\CorrectiveAction;
use App\Models\CorrectiveActionPhoto;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Audits\AuditService;
use App\Services\Audits\CorrectiveActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Photos on a corrective action: the owner's evidence and the auditor's
 * verification, kept apart, several of each, removed with their files.
 */
class CorrectiveActionPhotosTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $auditor;
    private Employee $chef;
    private CorrectiveAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->company = Company::create(['name' => 'Photo Co', 'slug' => Str::slug('Photo Co') . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true]);
        $this->outlet  = Outlet::create(['company_id' => $this->company->id, 'name' => 'IOI City Mall', 'code' => 'IOI', 'is_active' => true]);

        $this->auditor = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true]);
        $this->auditor->companies()->syncWithoutDetaching([$this->company->id]);
        $this->auditor->outlets()->syncWithoutDetaching([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $this->auditor->givePermissionTo(collect(['audits.view', 'audits.conduct', 'audits.actions.manage'])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->chef = Employee::create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'name' => 'Chef Ali', 'designation' => 'Chef', 'is_active' => true, 'email' => 'ali' . uniqid() . '@example.test']);

        $template = AuditTemplate::create(['company_id' => $this->company->id, 'name' => 'Mini']);
        $bar = AuditTemplateSection::create(['audit_template_id' => $template->id, 'name' => 'Bar', 'sort_order' => 0]);
        AuditTemplateItem::create(['audit_template_section_id' => $bar->id, 'label' => 'Chiller', 'points' => 2, 'sort_order' => 0]);

        $this->actingAs($this->auditor);
        $svc   = app(AuditService::class);
        $audit = $svc->start($template, $this->outlet, $this->auditor, '2026-09-01');
        $svc->answer($audit->lines()->where('label', 'Chiller')->firstOrFail(), 'nc');
        $svc->submit($audit, $this->auditor);

        $this->action = app(CorrectiveActionService::class)->create(AuditFinding::firstOrFail(), $this->auditor, [
            'owner_employee_id' => $this->chef->id, 'description' => 'Fix the seal', 'due_date' => '2026-09-10',
        ]);
    }

    public function test_the_owner_adds_several_photos_of_the_fix_and_the_auditor_adds_a_separate_verification_photo(): void
    {
        session(['subdomain_company_id' => $this->company->id]);
        app(\App\Services\Staff\StaffSession::class)->signIn($this->chef, 'email');

        $staff = Livewire::test(StaffCorrectiveActions::class);
        $staff->set("evidence.{$this->action->id}", UploadedFile::fake()->image('before.jpg'))->assertHasNoErrors();
        $staff->set("evidence.{$this->action->id}", UploadedFile::fake()->image('after.jpg'))->assertHasNoErrors();

        $this->assertSame(2, $this->action->evidencePhotos()->count(), 'Two photos, not one replacing the other.');
        $this->assertSame($this->chef->id, $this->action->evidencePhotos()->first()->uploaded_by_employee_id);

        // The auditor verifies with a photo of their own; the owner's are untouched.
        $this->actingAs($this->auditor);
        Livewire::test(FindingActions::class, ['findingId' => $this->action->audit_finding_id])
            ->set("verification.{$this->action->id}", UploadedFile::fake()->image('verified.jpg'))
            ->assertHasNoErrors()
            ->call('verify', $this->action->id);

        $this->action->refresh();
        $this->assertSame(2, $this->action->evidencePhotos()->count());
        $this->assertSame(1, $this->action->verificationPhotos()->count());
        $this->assertSame($this->auditor->id, $this->action->verificationPhotos()->first()->uploaded_by);
        $this->assertTrue($this->action->isVerified());

        foreach ($this->action->photos as $photo) {
            Storage::disk('public')->assertExists($photo->file_path);
        }
    }

    public function test_photos_can_be_added_and_are_shown_on_the_corrective_actions_page(): void
    {
        $svc = app(CorrectiveActionService::class);
        $svc->attachPhoto($this->action, UploadedFile::fake()->image('fixed.jpg'), CorrectiveActionPhoto::KIND_EVIDENCE, $this->chef);

        $page = Livewire::test(\App\Livewire\Audits\Actions::class)
            ->assertSee('Fix the seal')
            ->assertSee('+ Verification photo')
            ->set("verification.{$this->action->id}", UploadedFile::fake()->image('seen.jpg'))
            ->assertHasNoErrors();

        $this->assertSame(1, $this->action->verificationPhotos()->count());
        $this->assertSame(1, $this->action->evidencePhotos()->count());

        $html = $page->html();
        $this->assertStringContainsString($this->action->evidencePhotos()->first()->url(), $html);
        $this->assertStringContainsString($this->action->verificationPhotos()->first()->url(), $html);
    }

    public function test_the_owner_cannot_add_a_verification_photo_or_remove_one(): void
    {
        $verification = app(CorrectiveActionService::class)->attachPhoto($this->action, UploadedFile::fake()->image('v.jpg'), CorrectiveActionPhoto::KIND_VERIFICATION, $this->auditor);

        session(['subdomain_company_id' => $this->company->id]);
        app(\App\Services\Staff\StaffSession::class)->signIn($this->chef, 'email');

        // There is no verification upload on the staff screen; removing the
        // auditor's photo is simply not found from there.
        try {
            Livewire::test(StaffCorrectiveActions::class)->call('removePhoto', $verification->id);
            $this->fail('An owner removed a verification photo.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            // expected
        }

        $this->assertDatabaseHas('corrective_action_photos', ['id' => $verification->id]);
    }

    public function test_removing_a_photo_deletes_its_file_and_the_cap_is_six_per_kind(): void
    {
        $svc = app(CorrectiveActionService::class);

        for ($i = 0; $i < CorrectiveActionPhoto::MAX_PER_KIND; $i++) {
            $svc->attachPhoto($this->action, UploadedFile::fake()->image("e$i.jpg"), CorrectiveActionPhoto::KIND_EVIDENCE, $this->auditor);
        }

        try {
            $svc->attachPhoto($this->action, UploadedFile::fake()->image('one-too-many.jpg'), CorrectiveActionPhoto::KIND_EVIDENCE, $this->auditor);
            $this->fail('A seventh evidence photo was accepted.');
        } catch (\Illuminate\Validation\ValidationException) {
            // expected
        }

        // The other kind has its own allowance.
        $svc->attachPhoto($this->action, UploadedFile::fake()->image('v.jpg'), CorrectiveActionPhoto::KIND_VERIFICATION, $this->auditor);
        $this->assertSame(7, $this->action->photos()->count());

        $first = $this->action->evidencePhotos()->first();
        $path  = $first->file_path;

        Livewire::test(FindingActions::class, ['findingId' => $this->action->audit_finding_id])
            ->call('removePhoto', $first->id);

        Storage::disk('public')->assertMissing($path);
        $this->assertSame(6, $this->action->photos()->count());

        // Deleting the action takes every remaining file with it.
        $paths = $this->action->photos()->pluck('file_path');
        $svc->delete($this->action);
        foreach ($paths as $p) {
            Storage::disk('public')->assertMissing($p);
        }
        $this->assertDatabaseCount('corrective_action_photos', 0);
    }
}
