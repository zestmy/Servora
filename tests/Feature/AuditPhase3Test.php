<?php

namespace Tests\Feature;

use App\Livewire\Audits\Index as AuditsIndex;
use App\Livewire\Audits\Schedules;
use App\Livewire\Audits\Start;
use App\Livewire\Reports\Audits\AuditTrend;
use App\Livewire\Staff\CorrectiveActions as StaffCorrectiveActions;
use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\AuditSchedule;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 3 of the Audits module: schedules, the trend report, and the staff
 * portal screen where an action's owner marks it done.
 */
class AuditPhase3Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $auditor;
    private AuditTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Carbon::setTestNow('2026-09-26 10:00:00');

        $this->company = Company::create([
            'name' => 'Phase Three Co', 'slug' => Str::slug('Phase Three Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'IOI City Mall', 'code' => 'IOI', 'is_active' => true,
        ]);

        $this->auditor = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true,
        ]);
        $this->auditor->companies()->syncWithoutDetaching([$this->company->id]);
        $this->auditor->outlets()->syncWithoutDetaching([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $this->auditor->givePermissionTo(collect([
            'audits.view', 'audits.conduct', 'audits.manage', 'audits.actions.manage', 'audits.reopen', 'audits.delete', 'reports.view',
        ])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->template = AuditTemplate::create(['company_id' => $this->company->id, 'name' => 'Mini', 'code' => 'MINI']);
        $bar = AuditTemplateSection::create(['audit_template_id' => $this->template->id, 'name' => 'Bar', 'sort_order' => 0]);
        AuditTemplateItem::create(['audit_template_section_id' => $bar->id, 'number' => '1', 'label' => 'Chiller', 'points' => 2, 'sort_order' => 0]);
        AuditTemplateItem::create(['audit_template_section_id' => $bar->id, 'number' => '2', 'label' => 'Towels', 'points' => 2, 'sort_order' => 1]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A submitted audit on a date with the given lines marked NC. */
    private function submittedAudit(string $date, array $nc = []): Audit
    {
        $svc   = app(AuditService::class);
        $audit = $svc->start($this->template, $this->outlet, $this->auditor, $date);

        foreach ($nc as $label) {
            $svc->answer($audit->lines()->where('label', $label)->firstOrFail(), 'nc');
        }
        foreach ($audit->sections as $s) {
            $svc->markRemainingOk($s);
        }

        return $svc->submit($audit, $this->auditor);
    }

    // ── Schedules ────────────────────────────────────────────────────────

    public function test_starting_from_a_schedule_stamps_the_audit_and_rolls_the_due_date_from_the_due_date(): void
    {
        $this->actingAs($this->auditor);

        $schedule = AuditSchedule::create([
            'company_id' => $this->company->id, 'audit_template_id' => $this->template->id,
            'outlet_id' => $this->outlet->id, 'frequency' => 'quarterly', 'next_due_on' => '2026-09-01',
        ]);

        $this->assertTrue($schedule->isOverdue());

        Livewire::withQueryParams(['schedule' => $schedule->id])
            ->test(Start::class)
            ->assertSet('scheduleId', $schedule->id)
            ->assertSet('templateId', (string) $this->template->id)
            ->call('start')
            ->assertRedirect();

        $audit = Audit::firstOrFail();
        $schedule->refresh();

        $this->assertSame($schedule->id, $audit->audit_schedule_id);
        $this->assertSame($audit->id, $schedule->last_audit_id);
        $this->assertSame('2026-12-01', $schedule->next_due_on->toDateString(), 'Rolled from 1 Sep, not from today.');
        $this->assertFalse($schedule->isOverdue());
    }

    public function test_a_very_late_schedule_rolls_until_it_is_in_the_future(): void
    {
        $schedule = new AuditSchedule(['frequency' => 'monthly']);

        $this->assertSame('2026-10-05', $schedule->nextDueAfter(Carbon::parse('2026-03-05'))->toDateString());
    }

    public function test_changing_the_form_on_the_start_screen_makes_it_an_ad_hoc_audit(): void
    {
        $this->actingAs($this->auditor);

        $other = AuditTemplate::create(['company_id' => $this->company->id, 'name' => 'Other']);
        $sec   = AuditTemplateSection::create(['audit_template_id' => $other->id, 'name' => 'S', 'sort_order' => 0]);
        AuditTemplateItem::create(['audit_template_section_id' => $sec->id, 'label' => 'X', 'points' => 1, 'sort_order' => 0]);

        $schedule = AuditSchedule::create([
            'company_id' => $this->company->id, 'audit_template_id' => $this->template->id,
            'outlet_id' => $this->outlet->id, 'frequency' => 'monthly', 'next_due_on' => '2026-09-01',
        ]);

        Livewire::withQueryParams(['schedule' => $schedule->id])
            ->test(Start::class)
            ->set('templateId', (string) $other->id)
            ->call('start');

        $this->assertNull(Audit::firstOrFail()->audit_schedule_id);
        $this->assertSame('2026-09-01', $schedule->fresh()->next_due_on->toDateString(), 'The plan did not move.');
    }

    public function test_the_schedule_screen_creates_a_row_and_the_index_shows_the_due_strip(): void
    {
        $this->actingAs($this->auditor);

        Livewire::test(Schedules::class)
            ->call('openCreate')
            ->set('templateId', (string) $this->template->id)
            ->set('outletId', (string) $this->outlet->id)
            ->set('frequency', 'weekly')
            ->set('nextDueOn', '2026-09-20')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('IOI City Mall')
            ->assertSee('Weekly');

        Livewire::test(AuditsIndex::class)
            ->assertSee('1 scheduled audit is overdue');

        AuditSchedule::query()->update(['next_due_on' => '2026-10-01']);

        Livewire::test(AuditsIndex::class)
            ->assertSee('1 scheduled audit due within');
    }

    public function test_a_paused_schedule_is_never_due(): void
    {
        $this->actingAs($this->auditor);

        AuditSchedule::create([
            'company_id' => $this->company->id, 'audit_template_id' => $this->template->id,
            'outlet_id' => $this->outlet->id, 'frequency' => 'monthly', 'next_due_on' => '2026-01-01', 'is_active' => false,
        ]);

        $this->assertSame(0, AuditSchedule::overdue()->count());
        Livewire::test(AuditsIndex::class)->assertDontSee('overdue');
    }

    // ── Trend report ─────────────────────────────────────────────────────

    public function test_the_trend_report_averages_submitted_audits_and_ranks_the_most_failed_items(): void
    {
        $this->actingAs($this->auditor);

        $this->submittedAudit('2026-07-01', ['Chiller']);            // 50%
        $this->submittedAudit('2026-08-01', ['Chiller', 'Towels']);  // 0%
        $this->submittedAudit('2026-09-01');                         // 100%
        app(AuditService::class)->start($this->template, $this->outlet, $this->auditor, '2026-09-20'); // draft, ignored

        Livewire::test(AuditTrend::class)
            ->assertSee('IOI City Mall')
            ->assertSee('50.0%')          // average of 50, 0, 100
            ->assertSee('100.0%')         // latest
            ->assertSee('+100.0')         // change vs previous
            ->assertSee('Chiller')
            ->assertSee('2×')
            ->assertDontSee('No submitted audits');

        $this->get(route('reports.audit-trend'))->assertOk();
    }

    public function test_the_trend_report_needs_the_audit_ability_as_well_as_reports(): void
    {
        $reader = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id]);
        $reader->companies()->syncWithoutDetaching([$this->company->id]);
        setPermissionsTeamId($this->company->id);
        $reader->givePermissionTo(Permission::findOrCreate('reports.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($reader)->get(route('reports.audit-trend'))->assertForbidden();
    }

    // ── Staff portal ─────────────────────────────────────────────────────

    public function test_an_owner_sees_their_actions_on_the_staff_portal_and_marks_one_done_with_a_photo(): void
    {
        $chef = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Chef Ali', 'designation' => 'Chef', 'is_active' => true,
            'email' => 'ali' . uniqid() . '@example.test',
        ]);
        $other = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Siti', 'designation' => 'Manager', 'is_active' => true,
        ]);

        $audit = $this->submittedAudit('2026-09-01', ['Chiller', 'Towels']);
        [$chillerFinding, $towelFinding] = AuditFinding::orderBy('id')->get();

        $actions = app(CorrectiveActionService::class);
        $mine    = $actions->create($chillerFinding, $this->auditor, ['owner_employee_id' => $chef->id, 'description' => 'Fix the chiller seal', 'due_date' => '2026-09-10']);
        $theirs  = $actions->create($towelFinding, $this->auditor, ['owner_employee_id' => $other->id, 'description' => 'Replace bar towels', 'due_date' => null]);

        // A PIN session, not a guard.
        session(['subdomain_company_id' => $this->company->id]);
        app(\App\Services\Staff\StaffSession::class)->signIn($chef, 'email');

        $screen = Livewire::test(StaffCorrectiveActions::class)
            ->assertSee('Fix the chiller seal')
            ->assertSee('overdue')
            ->assertDontSee('Replace bar towels')
            ->assertDontSee('Verify');

        $screen->set('tab', 'outlet')->assertSee('Replace bar towels')->assertSee('Siti');

        $screen->set('tab', 'mine')
            ->set("notes.{$mine->id}", 'New seal fitted, reading 3°C')
            ->call('setStatus', $mine->id, 'done')
            ->set("evidence.{$mine->id}", UploadedFile::fake()->image('seal.jpg'))
            ->assertHasNoErrors();

        $mine->refresh();
        $this->assertSame('done', $mine->status);
        $this->assertSame('New seal fitted, reading 3°C', $mine->completion_note);
        $this->assertNotNull($mine->evidence_path);
        Storage::disk('public')->assertExists($mine->evidence_path);
        $this->assertSame('open', $chillerFinding->fresh()->status, 'Done is a claim; only the auditor resolves it.');

        // Somebody else's action cannot be touched from here: it is simply not
        // found, because the lookup is scoped to the owner.
        try {
            Livewire::test(StaffCorrectiveActions::class)->call('setStatus', $theirs->id, 'done');
            $this->fail('Another employee\'s action was reachable.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            // expected
        }

        $this->assertSame('open', $theirs->fresh()->status);
    }

    public function test_the_staff_actions_route_needs_a_staff_session(): void
    {
        $this->get('/staff/actions')->assertRedirect();
    }
}
