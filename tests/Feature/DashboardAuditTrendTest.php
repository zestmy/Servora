<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\AuditTemplate;
use App\Models\AuditTemplateItem;
use App\Models\AuditTemplateSection;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Audits\AuditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The audit score trend card on the dashboard: latest score and outcome per
 * outlet, change against the previous audit, average and pass rate.
 */
class DashboardAuditTrendTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $ioi;
    private Outlet $klcc;
    private User $user;
    private AuditTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-26 10:00:00');

        $this->company = Company::create(['name' => 'Trend Dash Co', 'slug' => Str::slug('Trend Dash Co') . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true]);
        $this->ioi     = Outlet::create(['company_id' => $this->company->id, 'name' => 'IOI City Mall', 'code' => 'IOI', 'is_active' => true]);
        $this->klcc    = Outlet::create(['company_id' => $this->company->id, 'name' => 'Suria KLCC', 'code' => 'KLCC', 'is_active' => true]);

        $this->user = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->ioi->id, 'can_view_all_outlets' => true]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->ioi->id, $this->klcc->id]);
        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(collect(['audits.view', 'audits.conduct', 'sales.view', 'purchasing.view', 'reports.view'])
            ->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->user);

        // Bar: 10 × 10 = 100, so each NC is exactly 10%.
        $this->template = AuditTemplate::create(['company_id' => $this->company->id, 'name' => 'Mini', 'code' => 'MINI']);
        $bar = AuditTemplateSection::create(['audit_template_id' => $this->template->id, 'name' => 'Bar', 'sort_order' => 0]);
        foreach (range(1, 10) as $i) {
            AuditTemplateItem::create(['audit_template_section_id' => $bar->id, 'label' => "Bar $i", 'points' => 10, 'sort_order' => $i]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function submitted(Outlet $outlet, string $date, int $ncCount): void
    {
        $svc   = app(AuditService::class);
        $audit = $svc->start($this->template, $outlet, $this->user, $date);
        // Not range(1, $n): PHP's range(1, 0) is [1, 0], which would look for "Bar 0".
        for ($i = 1; $i <= $ncCount; $i++) {
            $svc->answer($audit->lines()->where('label', "Bar $i")->firstOrFail(), 'nc');
        }
        foreach ($audit->sections as $s) {
            $svc->markRemainingOk($s);
        }
        $svc->submit($audit, $this->user);
    }

    public function test_the_card_shows_each_outlets_latest_score_change_and_the_overall_figures(): void
    {
        $this->submitted($this->ioi, '2026-07-01', 3);    // 70% — conditional
        $this->submitted($this->ioi, '2026-09-01', 1);    // 90% — pass, +20
        $this->submitted($this->klcc, '2026-08-15', 5);   // 50% — fail
        app(AuditService::class)->start($this->template, $this->klcc, $this->user, '2026-09-20');   // draft: ignored
        $this->submitted($this->ioi, '2025-01-01', 0);    // outside the 12-month window: ignored

        Livewire::test(Dashboard::class)
            ->assertSee('Audit scores')
            ->assertSee('3 audits')
            ->assertSee('average')
            ->assertSee('70.0%')                              // (70 + 90 + 50) / 3
            ->assertSee('pass rate')
            ->assertSee('33%')
            ->assertSeeInOrder(['Suria KLCC', 'Fail', '50.0%', 'first audit', 'IOI City Mall', 'Pass', '90.0%', '+20.0 vs previous']);
    }

    public function test_the_outlet_filter_narrows_the_card_and_it_is_absent_without_audits_or_the_ability(): void
    {
        Livewire::test(Dashboard::class)->assertDontSee('Audit scores');

        $this->submitted($this->ioi, '2026-09-01', 1);
        $this->submitted($this->klcc, '2026-09-02', 5);

        // The outlet filter's own <select> names every outlet, so the check is
        // on the scores: KLCC's 50% stays, IOI's 90% goes.
        Livewire::test(Dashboard::class)
            ->set('outletFilter', (string) $this->klcc->id)
            ->assertSee('50.0%')
            ->assertDontSee('90.0%');

        $viewer = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->ioi->id, 'can_view_all_outlets' => true]);
        $viewer->companies()->syncWithoutDetaching([$this->company->id]);
        setPermissionsTeamId($this->company->id);
        $viewer->givePermissionTo(collect(['sales.view', 'purchasing.view'])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($viewer);
        Livewire::test(Dashboard::class)->assertDontSee('Audit scores');
    }
}
