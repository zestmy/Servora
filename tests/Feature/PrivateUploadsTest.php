<?php

namespace Tests\Feature;

use App\Models\AiInvoiceScan;
use App\Models\AuditFinding;
use App\Models\AuditFindingPhoto;
use App\Models\AuditTemplate;
use App\Models\AuditTemplateItem;
use App\Models\AuditTemplateSection;
use App\Models\Company;
use App\Models\CorrectiveAction;
use App\Models\CorrectiveActionPhoto;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\SalesRecord;
use App\Models\SalesRecordAttachment;
use App\Models\User;
use App\Services\Audits\AuditService;
use App\Services\Audits\CorrectiveActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Sales attachments, supplier invoice scans and audit photos are behind login.
 *
 * They were on the public disk, which the web server hands to anyone holding
 * the URL. Now they live on the private disk and are streamed only after the
 * person, their permission, their company and the record's outlet check out.
 */
class PrivateUploadsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        [$this->company, $this->outlet] = $this->company('Alpha');
    }

    private function company(string $name): array
    {
        $company = Company::create(['name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true]);
        $outlet  = Outlet::create(['company_id' => $company->id, 'name' => "{$name} Main", 'code' => 'M' . $company->id, 'is_active' => true]);

        return [$company, $outlet];
    }

    private function user(Company $company, Outlet $outlet, array $permissions, bool $allOutlets = true): User
    {
        $user = User::factory()->create(['company_id' => $company->id, 'outlet_id' => $outlet->id, 'can_view_all_outlets' => $allOutlets]);
        $user->companies()->syncWithoutDetaching([$company->id]);
        $user->outlets()->syncWithoutDetaching([$outlet->id]);
        setPermissionsTeamId($company->id);
        foreach ($permissions as $p) {
            $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function attachment(): SalesRecordAttachment
    {
        Storage::disk('local')->put('sales-attachments/slip.jpg', 'jpeg-bytes');
        $record = SalesRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'sale_date' => today()->toDateString(), 'total_revenue' => 100, 'total_cost' => 0,
        ]);

        return SalesRecordAttachment::create([
            'sales_record_id' => $record->id, 'file_name' => 'slip.jpg', 'file_path' => 'sales-attachments/slip.jpg',
            'mime_type' => 'image/jpeg', 'file_size' => 10,
        ]);
    }

    public function test_a_sales_attachment_url_is_no_longer_a_public_storage_url(): void
    {
        $url = $this->attachment()->url();

        $this->assertStringNotContainsString('/storage/', $url);
        $this->assertStringContainsString('/files/sales-attachments/', $url);
    }

    public function test_a_sales_attachment_needs_a_login(): void
    {
        $this->get($this->attachment()->url())->assertRedirect();
    }

    public function test_a_sales_attachment_opens_for_a_sales_viewer_of_the_company(): void
    {
        $file = $this->attachment();

        $this->actingAs($this->user($this->company, $this->outlet, ['sales.view']))
            ->get($file->url())
            ->assertOk();
    }

    public function test_a_sales_attachment_is_refused_without_permission_or_from_another_company(): void
    {
        $file = $this->attachment();

        $this->actingAs($this->user($this->company, $this->outlet, ['recipes.view']))
            ->get($file->url())->assertForbidden();

        [$other, $otherOutlet] = $this->company('Bravo');
        $this->actingAs($this->user($other, $otherOutlet, ['sales.view']))
            ->get($file->url())->assertNotFound();
    }

    public function test_a_sales_attachment_at_an_outlet_the_user_cannot_access_is_refused(): void
    {
        $file = $this->attachment();
        $elsewhere = Outlet::create(['company_id' => $this->company->id, 'name' => 'Alpha Other', 'code' => 'AO', 'is_active' => true]);

        $this->actingAs($this->user($this->company, $elsewhere, ['sales.view'], allOutlets: false))
            ->get($file->url())->assertNotFound();
    }

    public function test_an_invoice_scan_needs_the_invoice_permission_and_the_company(): void
    {
        Storage::disk('local')->put('invoices/inv.pdf', '%PDF');
        $uploader = $this->user($this->company, $this->outlet, ['purchasing.invoice']);
        $scan = AiInvoiceScan::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'uploaded_by' => $uploader->id, 'original_file_path' => 'invoices/inv.pdf',
            'original_file_name' => 'inv.pdf', 'status' => 'processing',
        ]);
        $url = route('files.invoice-scan', $scan->id);

        $this->actingAs($this->user($this->company, $this->outlet, ['purchasing.invoice']))->get($url)->assertOk();
        $this->actingAs($this->user($this->company, $this->outlet, ['purchasing.view']))->get($url)->assertForbidden();

        [$other, $otherOutlet] = $this->company('Bravo');
        $this->actingAs($this->user($other, $otherOutlet, ['purchasing.invoice']))->get($url)->assertNotFound();
    }

    public function test_audit_and_action_photos_open_for_auditors_and_for_the_owning_staff_member_only(): void
    {
        $auditor = $this->user($this->company, $this->outlet, ['audits.view', 'audits.conduct', 'audits.actions.manage']);
        $chef = Employee::create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'name' => 'Chef Ali', 'designation' => 'Chef', 'is_active' => true, 'email' => 'ali' . uniqid() . '@example.test']);

        $template = AuditTemplate::create(['company_id' => $this->company->id, 'name' => 'Mini']);
        $section  = AuditTemplateSection::create(['audit_template_id' => $template->id, 'name' => 'Bar', 'sort_order' => 0]);
        AuditTemplateItem::create(['audit_template_section_id' => $section->id, 'label' => 'Chiller', 'points' => 2, 'sort_order' => 0]);

        $this->actingAs($auditor);
        $svc   = app(AuditService::class);
        $audit = $svc->start($template, $this->outlet, $auditor, '2026-09-01');
        $svc->answer($audit->lines()->where('label', 'Chiller')->firstOrFail(), 'nc');
        $svc->submit($audit, $auditor);
        $finding = AuditFinding::firstOrFail();
        $action  = app(CorrectiveActionService::class)->create($finding, $auditor, [
            'owner_employee_id' => $chef->id, 'description' => 'Fix the seal', 'due_date' => '2026-09-10',
        ]);

        Storage::disk('local')->put('audit-photos/f.jpg', 'jpeg');
        Storage::disk('local')->put('audit-photos/a.jpg', 'jpeg');
        $findingPhoto = AuditFindingPhoto::create(['audit_finding_id' => $finding->id, 'file_path' => 'audit-photos/f.jpg', 'uploaded_by' => $auditor->id]);
        $actionPhoto  = CorrectiveActionPhoto::create(['corrective_action_id' => $action->id, 'kind' => CorrectiveActionPhoto::KIND_EVIDENCE, 'file_path' => 'audit-photos/a.jpg']);

        $this->get($findingPhoto->url())->assertOk();
        $this->get($actionPhoto->url())->assertOk();

        [$other, $otherOutlet] = $this->company('Bravo');
        $outsider = $this->user($other, $otherOutlet, ['audits.view']);
        $this->actingAs($outsider)->get($findingPhoto->url())->assertNotFound();
        $this->actingAs($outsider)->get($actionPhoto->url())->assertNotFound();

        // The owner, on the staff app's PIN session.
        auth()->logout();
        session(['subdomain_company_id' => $this->company->id]);
        app(\App\Services\Staff\StaffSession::class)->signIn($chef, 'email');
        $this->get($actionPhoto->staffUrl())->assertOk();

        // Someone from another company on the staff app gets nothing.
        $stranger = Employee::create(['company_id' => $other->id, 'outlet_id' => $otherOutlet->id, 'name' => 'Stranger', 'designation' => 'Cook', 'is_active' => true, 'email' => 's' . uniqid() . '@example.test']);
        session(['subdomain_company_id' => $other->id]);
        app(\App\Services\Staff\StaffSession::class)->signIn($stranger, 'email');
        $this->get($actionPhoto->staffUrl())->assertNotFound();
    }

    public function test_the_migration_moves_existing_files_off_the_public_disk(): void
    {
        Storage::disk('public')->put('sales-attachments/old.jpg', 'old-bytes');
        $record = SalesRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'sale_date' => today()->toDateString(), 'total_revenue' => 100, 'total_cost' => 0,
        ]);
        SalesRecordAttachment::create([
            'sales_record_id' => $record->id, 'file_name' => 'old.jpg', 'file_path' => 'sales-attachments/old.jpg',
            'mime_type' => 'image/jpeg', 'file_size' => 9,
        ]);

        (require database_path('migrations/2026_10_01_000001_move_private_uploads_off_the_public_disk.php'))->up();

        Storage::disk('public')->assertMissing('sales-attachments/old.jpg');
        $this->assertSame('old-bytes', Storage::disk('local')->get('sales-attachments/old.jpg'));
    }
}
