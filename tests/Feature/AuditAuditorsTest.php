<?php

namespace Tests\Feature;

use App\Livewire\Audits\Auditors;
use App\Livewire\Audits\Conduct;
use App\Livewire\Audits\TemplateEdit;
use App\Models\AuditAuditor;
use App\Models\AuditTemplate;
use App\Models\AuditTemplateItem;
use App\Models\AuditTemplateSection;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Audits\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Two people-pickers on an audit form's header: the audited outlet's staff,
 * and the company's appointed auditors — and the setting that defines the
 * second list.
 */
class AuditAuditorsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Outlet $hq;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Auditor Co', 'slug' => Str::slug('Auditor Co') . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true]);
        $this->outlet  = Outlet::create(['company_id' => $this->company->id, 'name' => 'IOI City Mall', 'code' => 'IOI', 'is_active' => true]);
        $this->hq      = Outlet::create(['company_id' => $this->company->id, 'name' => 'Head Office', 'code' => 'HQ', 'is_active' => true]);

        $this->user = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id, $this->hq->id]);
        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(collect(['audits.view', 'audits.conduct', 'audits.manage', 'audits.delete'])
            ->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->user);
    }

    private function employee(string $name, Outlet $outlet, string $designation = 'Staff'): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $outlet->id,
            'name' => $name, 'designation' => $designation, 'staff_id' => Str::upper(Str::random(5)), 'is_active' => true,
        ]);
    }

    public function test_the_settings_screen_appoints_and_removes_auditors(): void
    {
        $qa = $this->employee('Syuhada', $this->hq, 'QA Executive');
        $this->employee('Juliana', $this->outlet, 'Manager');

        Livewire::test(Auditors::class)
            ->set('search', 'Syu')
            ->assertSee('Syuhada')
            ->assertDontSee('Juliana')
            ->call('appoint', $qa->id)
            ->assertSee('appointed as an auditor')
            ->assertSee('QA Executive');

        $this->assertDatabaseHas('audit_auditors', ['employee_id' => $qa->id, 'appointed_by' => $this->user->id]);

        // Appointing twice is one row.
        Livewire::test(Auditors::class)->call('appoint', $qa->id);
        $this->assertDatabaseCount('audit_auditors', 1);

        Livewire::test(Auditors::class)->call('remove', AuditAuditor::firstOrFail()->id);
        $this->assertDatabaseCount('audit_auditors', 0);
    }

    public function test_the_builder_offers_the_auditor_type_and_saves_it(): void
    {
        $template = AuditTemplate::create(['company_id' => $this->company->id, 'name' => 'Form']);

        Livewire::test(TemplateEdit::class, ['id' => $template->id])
            ->assertSee('Appointed auditor')
            ->assertSee('Outlet employee')
            ->call('addHeaderField')
            ->set('headerFields.0.label', 'Accompanying auditor')
            ->set('headerFields.0.type', 'auditor')
            ->call('saveHeader')
            ->assertHasNoErrors();

        $this->assertSame('auditor', $template->fresh()->headerFieldList()[0]['type']);
    }

    public function test_an_auditor_field_offers_appointed_auditors_and_an_employee_field_offers_outlet_staff(): void
    {
        $qa   = $this->employee('Syuhada', $this->hq, 'QA Executive');
        $mgr  = $this->employee('Juliana', $this->outlet, 'Manager');
        $this->employee('Farid', $this->hq, 'Accounts');   // HQ, but not appointed

        AuditAuditor::create(['company_id' => $this->company->id, 'employee_id' => $qa->id]);

        $template = AuditTemplate::create([
            'company_id' => $this->company->id, 'name' => 'Form',
            'header_fields' => [
                ['key' => 'so',      'label' => 'Shift officer', 'type' => 'employee', 'required' => false],
                ['key' => 'auditor', 'label' => 'Auditor',       'type' => 'auditor',  'required' => false],
            ],
        ]);
        $section = AuditTemplateSection::create(['audit_template_id' => $template->id, 'name' => 'Bar', 'sort_order' => 0]);
        AuditTemplateItem::create(['audit_template_section_id' => $section->id, 'label' => 'Chiller', 'points' => 2, 'sort_order' => 0]);

        $audit = app(AuditService::class)->start($template, $this->outlet, $this->user, '2026-09-26');

        $html = Livewire::test(Conduct::class, ['id' => $audit->id])
            ->set('headerValues.auditor', 'Syuhada')
            ->html();

        // The auditor picker lists the appointed QA person, and not HQ staff who
        // were never appointed; the employee picker lists the outlet's manager.
        $this->assertStringContainsString('Syuhada · QA Executive', $html);
        $this->assertStringContainsString('Juliana · Manager', $html);
        $this->assertStringNotContainsString('Farid', $html);

        $saved = collect($audit->fresh()->header_values)->firstWhere('key', 'auditor');
        $this->assertSame('Syuhada', $saved['value']);
    }

    public function test_the_rose_starter_carries_an_auditor_field_and_installed_forms_are_given_one(): void
    {
        $fresh = \App\Support\Audits\RoseTemplate::install($this->company, $this->user);

        $this->assertSame('auditor', $fresh->headerFieldList()[0]['type']);
        $this->assertSame('Auditor', $fresh->headerFieldList()[0]['label']);

        // A ROSE form installed before the field existed, with a field of its own.
        $old = AuditTemplate::create([
            'company_id' => $this->company->id, 'name' => 'Old ROSE', 'code' => 'ROSE',
            'header_fields' => [['key' => 'area_manager', 'label' => 'Area Manager', 'type' => 'text', 'required' => false]],
        ]);

        $migration = require database_path('migrations/2026_09_26_000008_add_auditor_field_to_installed_rose_forms.php');
        $migration->up();
        $migration->up();   // idempotent

        $fields = $old->fresh()->headerFieldList();
        $this->assertCount(2, $fields);
        $this->assertSame('auditor', $fields[0]['type']);
        $this->assertSame('area_manager', $fields[1]['key'], "The company's own field is kept, after the new one.");

        // The freshly installed form already had one and was not given a second.
        $this->assertCount(1, collect($fresh->fresh()->headerFieldList())->where('type', 'auditor'));
    }

    public function test_a_rose_form_with_its_own_auditor_fields_has_them_converted_and_the_generic_one_dropped(): void
    {
        $custom = AuditTemplate::create([
            'company_id' => $this->company->id, 'name' => 'Customised ROSE', 'code' => 'ROSE',
            'header_fields' => [
                ['key' => 'auditor',       'label' => 'Auditor',                  'type' => 'auditor',  'required' => false],
                ['key' => 'shift_officer', 'label' => 'Management on duty (FOH)', 'type' => 'employee', 'required' => false],
                ['key' => '1st_auditor',   'label' => '1st Auditor',              'type' => 'employee', 'required' => false],
                ['key' => '2nd_auditor',   'label' => '2nd Auditor',              'type' => 'employee', 'required' => false],
            ],
        ]);
        $plain = \App\Support\Audits\RoseTemplate::install($this->company, $this->user);

        $migration = require database_path('migrations/2026_09_26_000009_convert_rose_auditor_fields_to_appointed_auditors.php');
        $migration->up();
        $migration->up();   // idempotent

        $fields = collect($custom->fresh()->headerFieldList());
        $this->assertSame(['shift_officer', '1st_auditor', '2nd_auditor'], $fields->pluck('key')->all(), 'Generic field dropped, own fields kept in place.');
        $this->assertSame('employee', $fields->firstWhere('key', 'shift_officer')['type'], 'A manager-on-duty field is not an auditor field.');
        $this->assertSame('auditor', $fields->firstWhere('key', '1st_auditor')['type']);
        $this->assertSame('auditor', $fields->firstWhere('key', '2nd_auditor')['type']);

        // A form with only the starter's generic field keeps it.
        $this->assertSame('auditor', $plain->fresh()->headerFieldList()[0]['key']);
    }

    public function test_only_form_builders_reach_the_auditor_settings(): void
    {
        $viewer = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id]);
        $viewer->companies()->syncWithoutDetaching([$this->company->id]);
        setPermissionsTeamId($this->company->id);
        $viewer->givePermissionTo(Permission::findOrCreate('audits.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($viewer)->get(route('audits.auditors'))->assertForbidden();
        $this->actingAs($this->user)->get(route('audits.auditors'))->assertOk();
    }
}
