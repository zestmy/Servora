<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\AuditSchedule;
use App\Models\AuditTemplate;
use App\Models\AuditTemplateItem;
use App\Models\AuditTemplateSection;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Audits\AuditService;
use App\Services\Audits\CorrectiveActionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Audit work due shows in the dashboard's "Needs attention" card: each
 * re-audit with its due date, overdue scheduled audits, overdue corrective
 * actions — and none of it for somebody who cannot see audits.
 */
class DashboardAuditAlertsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private AuditTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-26 10:00:00');

        $this->company = Company::create(['name' => 'Dash Co', 'slug' => Str::slug('Dash Co') . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true]);
        $this->outlet  = Outlet::create(['company_id' => $this->company->id, 'name' => 'IOI City Mall', 'code' => 'IOI', 'is_active' => true]);

        $this->template = AuditTemplate::create(['company_id' => $this->company->id, 'name' => 'Mini', 'code' => 'MINI']);
        $crit = AuditTemplateSection::create(['audit_template_id' => $this->template->id, 'name' => 'Critical', 'scoring_mode' => 'penalty', 'sort_order' => 0]);
        AuditTemplateItem::create(['audit_template_section_id' => $crit->id, 'label' => 'Pests', 'points' => 10, 'sort_order' => 0]);
        $bar = AuditTemplateSection::create(['audit_template_id' => $this->template->id, 'name' => 'Bar', 'sort_order' => 1]);
        foreach (range(1, 10) as $i) {
            AuditTemplateItem::create(['audit_template_section_id' => $bar->id, 'label' => "Bar $i", 'points' => 10, 'sort_order' => $i]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<int, string> $permissions */
    private function userWith(array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->syncWithoutDetaching([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(collect($permissions)->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function conditionalAudit(User $auditor, string $date)
    {
        $svc   = app(AuditService::class);
        $audit = $svc->start($this->template, $this->outlet, $auditor, $date);
        $svc->answer($audit->lines()->where('label', 'Pests')->firstOrFail(), 'nc');
        foreach ($audit->sections as $s) {
            $svc->markRemainingOk($s);
        }

        return $svc->submit($audit, $auditor)->fresh();
    }

    public function test_the_dashboard_lists_each_reaudit_with_its_due_date_and_the_other_overdue_audit_work(): void
    {
        $user = $this->userWith(['audits.view', 'audits.conduct', 'audits.actions.manage', 'sales.view', 'purchasing.view']);
        $this->actingAs($user);

        $overdue = $this->conditionalAudit($user, '2026-08-20');   // re-audit was due 19 Sep

        AuditSchedule::create([
            'company_id' => $this->company->id, 'audit_template_id' => $this->template->id,
            'outlet_id' => $this->outlet->id, 'frequency' => 'monthly', 'next_due_on' => '2026-09-01',
        ]);

        $chef = Employee::create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'name' => 'Chef Ali', 'is_active' => true]);
        app(CorrectiveActionService::class)->create($overdue->findings()->firstOrFail(), $user, [
            'owner_employee_id' => $chef->id, 'description' => 'Call pest control', 'due_date' => '2026-09-10',
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('Needs attention')
            ->assertSee('Re-audit of IOI City Mall (MINI) was due 19 Sep 2026')
            ->assertSee('Start re-audit')
            ->assertSee('reaudit=' . $overdue->id, false)
            ->assertSee('1 scheduled audit is overdue')
            ->assertSee('1 corrective action is overdue');
    }

    public function test_a_reaudit_not_yet_due_is_shown_as_upcoming_and_a_settled_one_is_not_shown(): void
    {
        $user = $this->userWith(['audits.view', 'audits.conduct', 'sales.view', 'purchasing.view']);
        $this->actingAs($user);

        $soon = $this->conditionalAudit($user, '2026-09-20');   // due 20 Oct

        Livewire::test(Dashboard::class)
            ->assertSee('Re-audit of IOI City Mall (MINI) due 20 Oct 2026');

        // A follow-up settles it.
        $svc = app(AuditService::class);
        $followUp = $svc->start($this->template, $this->outlet, $user, '2026-09-26');
        foreach ($followUp->sections as $s) { $svc->markRemainingOk($s); }
        $svc->submit($followUp, $user);

        Livewire::test(Dashboard::class)->assertDontSee('Re-audit of IOI City Mall');
    }

    public function test_someone_without_the_audit_ability_sees_none_of_it(): void
    {
        $auditor = $this->userWith(['audits.view', 'audits.conduct']);
        $this->conditionalAudit($auditor, '2026-08-20');

        $this->actingAs($this->userWith(['sales.view', 'purchasing.view']));

        Livewire::test(Dashboard::class)->assertDontSee('Re-audit of');
    }
}
