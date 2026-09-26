<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\AuditTemplate;
use App\Models\AuditTemplateItem;
use App\Models\AuditTemplateSection;
use App\Models\Company;
use App\Models\CorrectiveAction;
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
 * The corrective-actions card on the dashboard: counts by state, overdue,
 * unactioned findings, verified this period, and who carries the most.
 */
class DashboardCorrectiveActionsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private AuditTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-26 10:00:00');

        $this->company = Company::create(['name' => 'CA Dash Co', 'slug' => Str::slug('CA Dash Co') . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true]);
        $this->outlet  = Outlet::create(['company_id' => $this->company->id, 'name' => 'IOI City Mall', 'code' => 'IOI', 'is_active' => true]);

        $this->user = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(collect(['audits.view', 'audits.conduct', 'audits.actions.manage', 'sales.view', 'purchasing.view'])
            ->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->user);

        $this->template = AuditTemplate::create(['company_id' => $this->company->id, 'name' => 'Mini', 'code' => 'MINI']);
        $bar = AuditTemplateSection::create(['audit_template_id' => $this->template->id, 'name' => 'Bar', 'sort_order' => 0]);
        foreach (['Chiller', 'Freezer', 'Towels', 'Floor'] as $i => $label) {
            AuditTemplateItem::create(['audit_template_section_id' => $bar->id, 'label' => $label, 'points' => 10, 'sort_order' => $i]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_card_counts_by_state_and_names_who_carries_the_most(): void
    {
        $svc   = app(AuditService::class);
        $audit = $svc->start($this->template, $this->outlet, $this->user, '2026-09-01');
        foreach (['Chiller', 'Freezer', 'Towels', 'Floor'] as $label) {
            $svc->answer($audit->lines()->where('label', $label)->firstOrFail(), 'nc');
        }
        $svc->submit($audit, $this->user);

        [$chiller, $freezer, $towels, $floor] = $audit->findings()->orderBy('id')->get();

        $chef    = Employee::create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'name' => 'Chef Ali', 'designation' => 'Chef', 'is_active' => true]);
        $manager = Employee::create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'name' => 'Juliana', 'designation' => 'Manager', 'is_active' => true]);

        $actions = app(CorrectiveActionService::class);
        $a1 = $actions->create($chiller, $this->user, ['owner_employee_id' => $chef->id, 'description' => 'Fix seal', 'due_date' => '2026-09-10']);   // overdue, open
        $a2 = $actions->create($freezer, $this->user, ['owner_employee_id' => $chef->id, 'description' => 'Defrost', 'due_date' => '2026-10-10']);   // in progress
        $a3 = $actions->create($towels,  $this->user, ['owner_employee_id' => $manager->id, 'description' => 'New towels', 'due_date' => null]);    // done, awaiting verification
        // Floor: no action yet.

        $actions->setStatus($a2, 'in_progress');
        $actions->setStatus($a3, 'done');

        // One verified this month, on a separate finding of another audit.
        $second = $svc->start($this->template, $this->outlet, $this->user, '2026-09-05');
        $svc->answer($second->lines()->where('label', 'Floor')->firstOrFail(), 'nc');
        foreach ($second->sections as $s) { $svc->markRemainingOk($s); }
        $svc->submit($second, $this->user);
        $a4 = $actions->create($second->findings()->firstOrFail(), $this->user, ['owner_employee_id' => $manager->id, 'description' => 'Mop', 'due_date' => null]);
        $actions->verify($a4, $this->user);

        Livewire::test(Dashboard::class)
            ->assertSee('Corrective actions')
            ->assertSeeInOrder(['Outstanding', '3', '1 open · 1 in progress'])
            ->assertSeeInOrder(['Overdue', '1'])
            ->assertSeeInOrder(['Awaiting verification', '1'])
            ->assertSeeInOrder(['No action yet', '1'])
            ->assertSeeInOrder(['Verified', '1'])
            ->assertSeeInOrder(['Chef Ali', 'Chef', '2 · 1 late'])
            ->assertSee('Juliana');
    }

    public function test_the_card_stays_off_the_page_when_there_is_nothing_to_say_or_no_ability(): void
    {
        Livewire::test(Dashboard::class)->assertDontSee('Corrective actions');

        $svc   = app(AuditService::class);
        $audit = $svc->start($this->template, $this->outlet, $this->user, '2026-09-01');
        $svc->answer($audit->lines()->where('label', 'Chiller')->firstOrFail(), 'nc');
        foreach ($audit->sections as $s) { $svc->markRemainingOk($s); }
        $svc->submit($audit, $this->user);

        Livewire::test(Dashboard::class)->assertSee('Corrective actions')->assertSeeInOrder(['No action yet', '1']);

        $viewer = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true]);
        $viewer->companies()->syncWithoutDetaching([$this->company->id]);
        setPermissionsTeamId($this->company->id);
        $viewer->givePermissionTo(collect(['sales.view', 'purchasing.view'])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($viewer);
        Livewire::test(Dashboard::class)->assertDontSee('Corrective actions');
    }
}
