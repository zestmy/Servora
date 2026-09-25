<?php

namespace Tests\Feature;

use App\Livewire\Audits\Actions as AuditActions;
use App\Livewire\Audits\Conduct;
use App\Livewire\Audits\FindingActions;
use App\Livewire\Audits\Index as AuditsIndex;
use App\Livewire\Audits\Start;
use App\Livewire\Audits\TemplateEdit;
use App\Livewire\Audits\Templates;
use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\AuditLine;
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
use App\Support\Audits\RoseTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Audits module end to end: form, audit, score, findings, actions.
 *
 * The rules pinned here are decisions: how a penalty section reaches the
 * total, that an audit is a copy of its form, that an NC line is a finding
 * with photos while still a draft, and that a finding resolves only when
 * every action on it is verified.
 */
class AuditModuleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->company = Company::create([
            'name' => 'Audit Screens Co', 'slug' => Str::slug('Audit Screens Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'IOI City Mall', 'code' => 'IOI', 'is_active' => true,
        ]);
    }

    private const ALL = [
        'audits.view', 'audits.conduct', 'audits.manage', 'audits.actions.manage', 'audits.reopen', 'audits.delete',
    ];

    /** @param array<int, string> $permissions */
    private function userWith(array $permissions): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(collect($permissions)->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /**
     * A small form: one penalty section (2 × 10) and one area section with a
     * heading of two sub-items (2 each), a plain 4-point item and an info line.
     */
    private function smallTemplate(): AuditTemplate
    {
        $template = AuditTemplate::create([
            'company_id' => $this->company->id, 'name' => 'Mini', 'code' => 'MINI', 'is_active' => true,
            'header_fields' => [['key' => 'so', 'label' => 'Shift officer', 'type' => 'text', 'required' => false]],
        ]);

        $critical = AuditTemplateSection::create([
            'audit_template_id' => $template->id, 'name' => 'Critical', 'scoring_mode' => 'penalty', 'sort_order' => 0,
        ]);
        AuditTemplateItem::create(['audit_template_section_id' => $critical->id, 'number' => '1', 'label' => 'Halal cert', 'points' => 10, 'sort_order' => 0]);
        AuditTemplateItem::create(['audit_template_section_id' => $critical->id, 'number' => '2', 'label' => 'No pests', 'points' => 10, 'sort_order' => 1]);

        $bar = AuditTemplateSection::create([
            'audit_template_id' => $template->id, 'name' => 'Bar', 'scoring_mode' => 'area', 'sort_order' => 1,
        ]);
        $equipment = AuditTemplateItem::create(['audit_template_section_id' => $bar->id, 'number' => '1', 'label' => 'Equipment', 'points' => 0, 'sort_order' => 0]);
        AuditTemplateItem::create(['audit_template_section_id' => $bar->id, 'parent_id' => $equipment->id, 'number' => 'a', 'label' => 'Chiller', 'hint' => '0–4°C', 'points' => 2, 'sort_order' => 1]);
        AuditTemplateItem::create(['audit_template_section_id' => $bar->id, 'parent_id' => $equipment->id, 'number' => 'b', 'label' => 'Freezer', 'points' => 2, 'sort_order' => 2]);
        AuditTemplateItem::create(['audit_template_section_id' => $bar->id, 'number' => '2', 'label' => 'Towels clean', 'points' => 4, 'sort_order' => 3]);
        AuditTemplateItem::create(['audit_template_section_id' => $bar->id, 'number' => '3', 'label' => 'Chiller temp', 'type' => 'info', 'info_type' => 'number', 'points' => 0, 'sort_order' => 4]);

        return $template;
    }

    private function startAudit(User $user, ?AuditTemplate $template = null): Audit
    {
        return app(AuditService::class)->start($template ?? $this->smallTemplate(), $this->outlet, $user, '2026-09-26');
    }

    private function lineNamed(Audit $audit, string $label): AuditLine
    {
        return $audit->lines()->where('label', $label)->firstOrFail();
    }

    // ── Forms ─────────────────────────────────────────────────────────────

    public function test_the_rose_starter_installs_with_its_five_sections_and_a_penalty_first(): void
    {
        $this->actingAs($this->userWith(self::ALL));

        Livewire::test(Templates::class)->call('installRose')->assertRedirect();

        $template = AuditTemplate::where('code', 'ROSE')->firstOrFail();

        $this->assertCount(5, $template->sections);
        $this->assertSame('penalty', $template->sections->first()->scoring_mode, 'Main food safety / halal NCs deduct from the total.');
        $this->assertSame(140, (int) $template->sections->first()->items()->sum('points'), '14 critical items at 10 points.');
        $this->assertSame(20, (int) AuditTemplateItem::where('audit_template_section_id', $template->sections->last()->id)->whereNull('parent_id')->get()->sum(fn ($i) => $i->points ?: $i->children->sum('points')), 'Profile & documentation is 20 points.');
        $this->assertTrue(AuditTemplateItem::whereIn('audit_template_section_id', $template->sections->pluck('id'))->where('type', 'product')->exists(), 'Product slots exist for tasting.');
        $this->assertGreaterThan(250, AuditTemplateItem::whereIn('audit_template_section_id', $template->sections->pluck('id'))->count());
    }

    public function test_the_builder_adds_a_section_an_item_and_a_sub_item_and_bumps_the_version(): void
    {
        $this->actingAs($this->userWith(self::ALL));

        $template = AuditTemplate::create(['company_id' => $this->company->id, 'name' => 'Blank', 'is_active' => true]);

        $test = Livewire::test(TemplateEdit::class, ['id' => $template->id])
            ->call('addSection')
            ->set('sectionName', 'Kitchen')
            ->set('sectionMode', 'area')
            ->call('saveSection')
            ->call('addItem');

        $item = AuditTemplateItem::firstOrFail();

        $test->set("items.{$item->id}.label", 'Equipment in good condition')
             ->set("items.{$item->id}.points", 6)
             ->call('addItem', $item->id);

        $child = AuditTemplateItem::where('parent_id', $item->id)->firstOrFail();

        $this->assertSame('a', $child->number, 'Sub-items are lettered.');
        $this->assertSame('Equipment in good condition', $item->fresh()->label);
        $this->assertSame(6, $item->fresh()->points);
        $this->assertSame('Kitchen', $template->sections()->first()->name);
        $this->assertGreaterThan(1, $template->fresh()->version, 'Structural edits bump the version.');
    }

    // ── Starting and scoring ─────────────────────────────────────────────

    public function test_starting_an_audit_copies_the_form_so_later_edits_do_not_touch_it(): void
    {
        $user = $this->userWith(self::ALL);
        $this->actingAs($user);

        $template = $this->smallTemplate();
        $audit    = $this->startAudit($user, $template);

        $this->assertCount(2, $audit->sections);
        $this->assertCount(7, $audit->lines);
        $this->assertSame(8, $audit->available_points, 'Area pool: 2 + 2 + 4; the penalty section is not in it.');

        $template->sections->last()->items()->where('label', 'Towels clean')->update(['points' => 40, 'label' => 'Renamed']);

        $this->assertSame(4, $this->lineNamed($audit, 'Towels clean')->points);
        $this->assertSame(8, $audit->fresh()->available_points);
    }

    public function test_scores_follow_the_form_arithmetic_including_na_and_the_penalty(): void
    {
        $user  = $this->userWith(self::ALL);
        $this->actingAs($user);
        $audit = $this->startAudit($user);
        $svc   = app(AuditService::class);

        $svc->answer($this->lineNamed($audit, 'Chiller'), 'nc');          // −2 of 2
        $svc->answer($this->lineNamed($audit, 'Freezer'), 'na');          // 2 pts leave the pool
        $svc->answer($this->lineNamed($audit, 'Towels clean'), 'nc', 1);  // −1 of 4
        $svc->answer($this->lineNamed($audit, 'Halal cert'), 'ok');
        $svc->answer($this->lineNamed($audit, 'No pests'), 'nc');         // −10 penalty

        $audit->refresh();
        $bar = $audit->sections->firstWhere('name', 'Bar');

        $this->assertSame(6, $bar->available_points, '8 − 2 N/A');
        $this->assertSame(3, $bar->lost_points);
        $this->assertSame(50.0, (float) $bar->score_percent, '3 / 6');

        $this->assertSame(6, $audit->available_points);
        $this->assertSame(3, $audit->lost_points);
        $this->assertSame(10, $audit->penalty_points);
        $this->assertSame(-7, $audit->score_points, '6 − 3 − 10: the penalty comes off the total, not the area.');
        $this->assertSame(3, $audit->finding_count);
    }

    public function test_marking_nc_opens_a_finding_and_marking_ok_again_removes_it(): void
    {
        $user  = $this->userWith(self::ALL);
        $this->actingAs($user);
        $audit = $this->startAudit($user);
        $line  = $this->lineNamed($audit, 'No pests');

        Livewire::test(Conduct::class, ['id' => $audit->id])
            ->call('answer', $line->id, 'nc')
            ->set("lineNotes.{$line->id}", 'Droppings behind the freezer');

        $finding = AuditFinding::where('audit_line_id', $line->id)->firstOrFail();
        $this->assertSame('major', $finding->severity, 'A penalty-section NC is major.');
        $this->assertSame('Droppings behind the freezer', $finding->description);
        $this->assertSame(10, $finding->points_lost);

        Livewire::test(Conduct::class, ['id' => $audit->id])->call('answer', $line->id, 'ok');

        $this->assertDatabaseMissing('audit_findings', ['audit_line_id' => $line->id]);
        $this->assertSame(0, $audit->fresh()->finding_count);
    }

    public function test_a_photo_attaches_to_the_finding_and_is_removed_with_it(): void
    {
        $user  = $this->userWith(self::ALL);
        $this->actingAs($user);
        $audit = $this->startAudit($user);
        $line  = $this->lineNamed($audit, 'Chiller');

        Livewire::test(Conduct::class, ['id' => $audit->id])
            ->call('answer', $line->id, 'nc')
            ->set("photos.{$line->id}", UploadedFile::fake()->image('chiller.jpg', 800, 600))
            ->assertHasNoErrors();

        $finding = AuditFinding::where('audit_line_id', $line->id)->firstOrFail();
        $photo   = $finding->photos()->firstOrFail();

        Storage::disk('public')->assertExists($photo->file_path);

        Livewire::test(Conduct::class, ['id' => $audit->id])->call('answer', $line->id, 'ok');

        Storage::disk('public')->assertMissing($photo->file_path);
        $this->assertDatabaseCount('audit_finding_photos', 0);
    }

    public function test_a_product_slot_names_its_findings_after_what_was_tasted(): void
    {
        $user = $this->userWith(self::ALL);
        $this->actingAs($user);

        $template = AuditTemplate::create(['company_id' => $this->company->id, 'name' => 'Tasting', 'is_active' => true]);
        $section  = AuditTemplateSection::create(['audit_template_id' => $template->id, 'name' => 'Bar', 'sort_order' => 0]);
        $slot     = AuditTemplateItem::create(['audit_template_section_id' => $section->id, 'number' => 'T1', 'label' => 'Toast 1', 'type' => 'product', 'sort_order' => 0]);
        AuditTemplateItem::create(['audit_template_section_id' => $section->id, 'parent_id' => $slot->id, 'number' => 'a', 'label' => 'Correct procedure', 'points' => 4, 'sort_order' => 1]);

        $audit = $this->startAudit($user, $template);
        $slotLine = $this->lineNamed($audit, 'Toast 1');
        $crit     = $this->lineNamed($audit, 'Correct procedure');

        Livewire::test(Conduct::class, ['id' => $audit->id])
            ->set("subjects.{$slotLine->id}", 'Kaya & butter toast')
            ->call('answer', $crit->id, 'nc');

        $this->assertSame('Toast 1 — Kaya & butter toast · a. Correct procedure', AuditFinding::firstOrFail()->item_label);
    }

    public function test_submit_refuses_while_a_line_is_unanswered_and_mark_the_rest_ok_fixes_that(): void
    {
        $user  = $this->userWith(self::ALL);
        $this->actingAs($user);
        $audit = $this->startAudit($user);

        $test = Livewire::test(Conduct::class, ['id' => $audit->id])
            ->call('answer', $this->lineNamed($audit, 'Chiller')->id, 'nc')
            ->call('submit')
            ->assertHasErrors('audit');

        $this->assertTrue($audit->fresh()->isDraft());

        foreach ($audit->sections as $section) {
            $test->call('selectSection', $section->id)->call('markRemainingOk');
        }

        $test->call('submit')->assertHasNoErrors();

        $audit->refresh();
        $this->assertSame('submitted', $audit->status);
        $this->assertNotNull($audit->submitted_at);
        $this->assertSame(75.0, (float) $audit->score_percent, '(8 − 2) / 8');
        $this->assertSame(0, $audit->lines()->where('is_leaf', true)->where('type', '!=', 'info')->whereNull('result')->count());
        $this->assertNull($this->lineNamed($audit, 'Chiller temp')->result, 'Info lines take no result.');
    }

    public function test_a_submitted_audit_cannot_be_changed_and_reopening_clears_the_acknowledgement(): void
    {
        $user  = $this->userWith(self::ALL);
        $this->actingAs($user);
        $audit = $this->startAudit($user);
        $svc   = app(AuditService::class);

        foreach ($audit->sections as $s) { $svc->markRemainingOk($s); }
        $svc->submit($audit, $user);

        Livewire::test(Conduct::class, ['id' => $audit->id])
            ->call('answer', $this->lineNamed($audit, 'Chiller')->id, 'nc')
            ->assertStatus(403);

        Livewire::test(Conduct::class, ['id' => $audit->id])
            ->set('ackName', 'Juliana')
            ->set('ackPosition', 'Manager')
            ->set('signature', 'data:image/png;base64,' . base64_encode(str_repeat('x', 200)))
            ->call('acknowledge')
            ->assertHasNoErrors();

        $audit->refresh();
        $this->assertSame('closed', $audit->status, 'No findings and acknowledged: nothing left to do.');
        $this->assertNotNull($audit->signature_path);
        Storage::disk('local')->assertExists($audit->signature_path);

        Livewire::test(Conduct::class, ['id' => $audit->id])->call('reopen');

        $audit->refresh();
        $this->assertTrue($audit->isDraft());
        $this->assertNull($audit->acknowledged_by_name);
        $this->assertNull($audit->signature_path);
    }

    public function test_reopening_needs_its_own_ability(): void
    {
        $user  = $this->userWith(['audits.view', 'audits.conduct']);
        $this->actingAs($user);
        $audit = $this->startAudit($user);
        $svc   = app(AuditService::class);
        foreach ($audit->sections as $s) { $svc->markRemainingOk($s); }
        $svc->submit($audit, $user);

        Livewire::test(Conduct::class, ['id' => $audit->id])->call('reopen')->assertStatus(403);
    }

    // ── Corrective actions ───────────────────────────────────────────────

    public function test_a_finding_resolves_only_when_every_action_is_verified_and_the_audit_then_closes(): void
    {
        $user  = $this->userWith(self::ALL);
        $this->actingAs($user);
        $audit = $this->startAudit($user);
        $svc   = app(AuditService::class);

        $chef = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Chef Ali', 'designation' => 'Chef', 'is_active' => true,
        ]);

        $svc->answer($this->lineNamed($audit, 'Chiller'), 'nc');
        foreach ($audit->sections as $s) { $svc->markRemainingOk($s); }
        $svc->submit($audit, $user);
        $svc->acknowledge($audit, 'Juliana', 'Manager', null);

        $finding = AuditFinding::firstOrFail();
        $this->assertSame('acknowledged', $audit->fresh()->status, 'An open finding keeps the audit from closing.');

        $panel = Livewire::test(FindingActions::class, ['findingId' => $finding->id])
            ->call('startAdding')
            ->set('ownerId', (string) $chef->id)
            ->set('description', 'Service the chiller compressor')
            ->set('dueDate', '2026-10-03')
            ->call('add')
            ->assertHasNoErrors();

        $panel->call('startAdding')
            ->set('description', 'Retrain on temperature log')
            ->call('add');

        [$fix, $train] = CorrectiveAction::orderBy('id')->get();

        $this->assertSame($chef->id, $fix->owner_employee_id);
        $this->assertSame('open', $finding->fresh()->status);

        $panel->call('setStatus', $fix->id, 'done')->call('verify', $fix->id);
        $this->assertSame('open', $finding->fresh()->status, 'One of two verified is not resolved.');
        $this->assertSame('acknowledged', $audit->fresh()->status);

        $panel->call('verify', $train->id);
        $this->assertSame('resolved', $finding->fresh()->status);
        $this->assertSame('closed', $audit->fresh()->status);

        $panel->call('unverify', $train->id);
        $this->assertSame('open', $finding->fresh()->status);
        $this->assertSame('acknowledged', $audit->fresh()->status, 'Closure reverses when an action is un-verified.');
    }

    public function test_an_action_owner_must_work_at_the_audited_outlet(): void
    {
        $user  = $this->userWith(self::ALL);
        $this->actingAs($user);
        $audit = $this->startAudit($user);
        app(AuditService::class)->answer($this->lineNamed($audit, 'Chiller'), 'nc');

        $elsewhere = Outlet::create(['company_id' => $this->company->id, 'name' => 'Other', 'code' => 'OTH', 'is_active' => true]);
        $stranger  = Employee::create(['company_id' => $this->company->id, 'outlet_id' => $elsewhere->id, 'name' => 'Stranger', 'is_active' => true]);

        Livewire::test(FindingActions::class, ['findingId' => AuditFinding::firstOrFail()->id])
            ->call('startAdding')
            ->set('ownerId', (string) $stranger->id)
            ->set('description', 'Something')
            ->call('add')
            ->assertHasErrors('ownerId');

        $this->assertDatabaseCount('corrective_actions', 0);
    }

    public function test_the_actions_summary_groups_by_outlet_then_owner_and_lists_findings_with_no_action(): void
    {
        $user  = $this->userWith(self::ALL);
        $this->actingAs($user);
        $audit = $this->startAudit($user);
        $svc   = app(AuditService::class);

        $manager = Employee::create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'name' => 'Juliana', 'designation' => 'Manager', 'is_active' => true]);

        $svc->answer($this->lineNamed($audit, 'Chiller'), 'nc');
        $svc->answer($this->lineNamed($audit, 'No pests'), 'nc');
        foreach ($audit->sections as $s) { $svc->markRemainingOk($s); }
        $svc->submit($audit, $user);

        $pests = AuditFinding::where('item_label', 'like', '%No pests%')->firstOrFail();
        app(CorrectiveActionService::class)->create($pests, $user, [
            'owner_employee_id' => $manager->id, 'description' => 'Call pest control', 'due_date' => '2020-01-01',
        ]);

        Livewire::test(AuditActions::class)
            ->assertSee('IOI City Mall')
            ->assertSee('Manager · Juliana')
            ->assertSee('Call pest control')
            ->assertSee('No action raised yet')
            ->assertSee('Chiller')
            ->set('statusFilter', 'overdue')
            ->assertSee('Call pest control');
    }

    // ── Lists, permissions, report ───────────────────────────────────────

    public function test_the_start_screen_creates_a_draft_and_the_index_lists_it(): void
    {
        $this->actingAs($this->userWith(self::ALL));
        $template = $this->smallTemplate();

        Livewire::test(Start::class)
            ->set('templateId', (string) $template->id)
            ->set('outlet_id', $this->outlet->id)
            ->set('auditDate', '2026-09-26')
            ->set('reference', 'Q3-01')
            ->call('start')
            ->assertRedirect();

        $audit = Audit::firstOrFail();
        $this->assertTrue($audit->isDraft());
        $this->assertNotNull($audit->time_in, 'Time in is stamped at start.');

        Livewire::test(AuditsIndex::class)->assertSee('IOI City Mall')->assertSee('Q3-01')->assertSee('Draft');
    }

    public function test_a_viewer_can_read_but_not_conduct(): void
    {
        $auditor = $this->userWith(self::ALL);
        $audit   = $this->startAudit($auditor);

        $viewer = $this->userWith(['audits.view']);
        $this->actingAs($viewer);

        $this->get(route('audits.show', $audit->id))->assertOk();
        $this->get(route('audits.start'))->assertForbidden();
        $this->get(route('audits.templates'))->assertForbidden();

        Livewire::test(Conduct::class, ['id' => $audit->id])
            ->call('answer', $this->lineNamed($audit, 'Chiller')->id, 'nc')
            ->assertStatus(403);
    }

    public function test_the_pdf_report_renders_for_a_submitted_audit_only(): void
    {
        $user  = $this->userWith(self::ALL);
        $this->actingAs($user);
        $audit = $this->startAudit($user);
        $svc   = app(AuditService::class);

        $this->get(route('audits.report', $audit->id))->assertNotFound();

        $svc->answer($this->lineNamed($audit, 'Chiller'), 'nc');
        foreach ($audit->sections as $s) { $svc->markRemainingOk($s); }
        $svc->submit($audit, $user);

        $this->get(route('audits.report', $audit->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_another_company_cannot_see_the_audit(): void
    {
        $user  = $this->userWith(self::ALL);
        $audit = $this->startAudit($user);

        $other = Company::create(['name' => 'Other Co', 'slug' => 'other-' . uniqid(), 'currency' => 'MYR', 'is_active' => true]);
        $otherOutlet = Outlet::create(['company_id' => $other->id, 'name' => 'Elsewhere', 'code' => 'ELS', 'is_active' => true]);
        $intruder = User::factory()->create(['company_id' => $other->id, 'outlet_id' => $otherOutlet->id, 'can_view_all_outlets' => true]);
        $intruder->companies()->syncWithoutDetaching([$other->id]);
        setPermissionsTeamId($other->id);
        $intruder->givePermissionTo(Permission::findOrCreate('audits.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($intruder)->get(route('audits.show', $audit->id))->assertNotFound();
    }
}
