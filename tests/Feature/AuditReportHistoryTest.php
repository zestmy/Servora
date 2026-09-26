<?php

namespace Tests\Feature;

use App\Http\Controllers\Audits\AuditReportController;
use App\Models\AuditTemplate;
use App\Models\AuditTemplateItem;
use App\Models\AuditTemplateSection;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Audits\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The score history on the PDF report: same outlet, same form, last twelve
 * months, up to and including the audit being printed.
 */
class AuditReportHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private AuditTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'History Co', 'slug' => Str::slug('History Co') . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true]);
        $this->outlet  = Outlet::create(['company_id' => $this->company->id, 'name' => 'IOI City Mall', 'code' => 'IOI', 'is_active' => true]);

        $this->user = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(collect(['audits.view', 'audits.conduct'])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->user);

        $this->template = AuditTemplate::create(['company_id' => $this->company->id, 'name' => 'Mini', 'code' => 'MINI']);
        $bar = AuditTemplateSection::create(['audit_template_id' => $this->template->id, 'name' => 'Bar', 'sort_order' => 0]);
        foreach (range(1, 10) as $i) {
            AuditTemplateItem::create(['audit_template_section_id' => $bar->id, 'label' => "Bar $i", 'points' => 10, 'sort_order' => $i]);
        }
    }

    private function submitted(string $date, int $ncCount, ?AuditTemplate $template = null, ?Outlet $outlet = null)
    {
        $svc   = app(AuditService::class);
        $audit = $svc->start($template ?? $this->template, $outlet ?? $this->outlet, $this->user, $date);
        for ($i = 1; $i <= $ncCount; $i++) {
            $svc->answer($audit->lines()->where('label', "Bar $i")->firstOrFail(), 'nc');
        }
        foreach ($audit->sections as $s) {
            $svc->markRemainingOk($s);
        }

        return $svc->submit($audit, $this->user)->fresh();
    }

    public function test_history_is_the_same_outlet_and_form_oldest_first_ending_with_this_audit(): void
    {
        $this->submitted('2025-01-15', 0);                 // older than twelve months: out
        $this->submitted('2026-03-01', 3);                 // 70%
        $this->submitted('2026-06-01', 1);                 // 90%
        $this->submitted('2026-11-01', 0);                 // after this audit: out
        $other = Outlet::create(['company_id' => $this->company->id, 'name' => 'Elsewhere', 'code' => 'ELS', 'is_active' => true]);
        $this->user->outlets()->syncWithoutDetaching([$other->id]);
        $this->submitted('2026-08-01', 5, null, $other);   // another outlet: out
        app(AuditService::class)->start($this->template, $this->outlet, $this->user, '2026-08-15');   // draft: out

        $this_audit = $this->submitted('2026-09-01', 2);   // 80%

        $history = AuditReportController::history($this_audit);

        $this->assertSame(['2026-03-01', '2026-06-01', '2026-09-01'], $history->map(fn ($h) => $h['date']->toDateString())->all());
        $this->assertSame([70.0, 90.0, 80.0], $history->pluck('score')->all());
        $this->assertSame([null, 20.0, -10.0], $history->pluck('delta')->all());
        $this->assertSame([false, false, true], $history->pluck('current')->all());
        // 70% is below the 80% conditional bar: a fail. 90% passes; 80% is conditional.
        $this->assertSame(['fail', 'pass', 'conditional'], $history->pluck('outcome')->all());
    }

    public function test_the_report_still_renders_with_and_without_history(): void
    {
        $first = $this->submitted('2026-06-01', 1);
        $this->get(route('audits.report', $first->id))->assertOk()->assertHeader('content-type', 'application/pdf');

        $second = $this->submitted('2026-09-01', 2);
        $this->assertCount(2, AuditReportController::history($second));
        $this->get(route('audits.report', $second->id))->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
