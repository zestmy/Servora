<?php

namespace Tests\Feature;

use App\Livewire\Audits\Index as AuditsIndex;
use App\Livewire\Audits\TemplateEdit;
use App\Models\Audit;
use App\Models\AuditTemplate;
use App\Models\AuditTemplateItem;
use App\Models\AuditTemplateSection;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Audits\AuditScoreService;
use App\Services\Audits\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Pass / conditional pass / fail: three tests over the total, the majors and
 * the weakest area — and the rules travel with the audit.
 */
class AuditOutcomeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private AuditTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Outcome Co', 'slug' => Str::slug('Outcome Co') . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true]);
        $this->outlet  = Outlet::create(['company_id' => $this->company->id, 'name' => 'IOI City Mall', 'code' => 'IOI', 'is_active' => true]);

        $this->user = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(collect(['audits.view', 'audits.conduct', 'audits.manage'])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->user);

        /*
         * Critical: 2 × 10 (penalty). Bar: 10 × 10 = 100. Kitchen: 10 × 10 = 100.
         * Pool 200, so each area item is 5% of the total and 10% of its area.
         */
        $this->template = AuditTemplate::create(['company_id' => $this->company->id, 'name' => 'Grade', 'code' => 'GR']);
        $critical = AuditTemplateSection::create(['audit_template_id' => $this->template->id, 'name' => 'Critical', 'scoring_mode' => 'penalty', 'sort_order' => 0]);
        foreach (['Halal cert', 'Pests'] as $i => $label) {
            AuditTemplateItem::create(['audit_template_section_id' => $critical->id, 'number' => (string) ($i + 1), 'label' => $label, 'points' => 10, 'sort_order' => $i]);
        }
        foreach (['Bar', 'Kitchen'] as $s => $name) {
            $section = AuditTemplateSection::create(['audit_template_id' => $this->template->id, 'name' => $name, 'sort_order' => $s + 1]);
            for ($i = 1; $i <= 10; $i++) {
                AuditTemplateItem::create(['audit_template_section_id' => $section->id, 'number' => (string) $i, 'label' => "$name item $i", 'points' => 10, 'sort_order' => $i]);
            }
        }
    }

    /** @param array<int, string> $nc labels to mark NC; everything else OK */
    private function graded(array $nc): Audit
    {
        $svc   = app(AuditService::class);
        $audit = $svc->start($this->template, $this->outlet, $this->user, '2026-09-26');

        foreach ($nc as $label) {
            $svc->answer($audit->lines()->where('label', $label)->firstOrFail(), 'nc');
        }
        foreach ($audit->sections as $s) {
            $svc->markRemainingOk($s);
        }

        return $svc->submit($audit, $this->user)->fresh();
    }

    public function test_a_clean_audit_passes(): void
    {
        $audit = $this->graded([]);

        $this->assertSame('pass', $audit->outcome);
        $this->assertSame([], $audit->outcome_reasons);
        $this->assertSame(0, $audit->major_count);
    }

    public function test_one_major_makes_a_high_score_a_conditional_pass(): void
    {
        $audit = $this->graded(['Pests']);   // 95% total, one major

        $this->assertSame(95.0, (float) $audit->score_percent);
        $this->assertSame('conditional', $audit->outcome);
        $this->assertSame(1, $audit->major_count);
        $this->assertStringContainsString('1 major non-conformance', $audit->outcome_reasons[0]);
    }

    public function test_two_majors_fail_the_audit_whatever_the_score(): void
    {
        $audit = $this->graded(['Pests', 'Halal cert']);   // 90% total

        $this->assertSame(90.0, (float) $audit->score_percent);
        $this->assertSame('fail', $audit->outcome);
        $this->assertStringContainsString('2 major non-conformances', $audit->outcome_reasons[0]);
    }

    public function test_a_weak_area_inside_a_good_total_is_conditional_then_fail(): void
    {
        // Kitchen loses 3 of 10 → 70%; total 85%.
        $audit = $this->graded(['Kitchen item 1', 'Kitchen item 2', 'Kitchen item 3']);
        $this->assertSame('conditional', $audit->outcome);
        $this->assertTrue(collect($audit->outcome_reasons)->contains(fn ($r) => str_starts_with($r, 'Kitchen at 70.0%')));

        // Kitchen loses 4 of 10 → 60%: below the conditional floor, so fail — even though total is 80%.
        $audit = $this->graded(['Kitchen item 1', 'Kitchen item 2', 'Kitchen item 3', 'Kitchen item 4']);
        $this->assertSame(80.0, (float) $audit->score_percent);
        $this->assertSame('fail', $audit->outcome);
        $this->assertTrue(collect($audit->outcome_reasons)->contains(fn ($r) => str_contains($r, 'Kitchen at 60.0% is below 70%')));
    }

    public function test_a_total_between_the_bars_is_conditional_and_below_them_is_fail(): void
    {
        // Spread across areas so no section floor trips: 2 bar + 1 kitchen = 85%, areas 80% and 90%.
        $audit = $this->graded(['Bar item 1', 'Bar item 2', 'Kitchen item 1']);
        $this->assertSame(85.0, (float) $audit->score_percent);
        $this->assertSame('conditional', $audit->outcome);

        // 2 + 2 + one major: (200 − 40 − 10) / 200 = 75% → fail on total, even
        // though the one major and both areas at 80% would only be conditional.
        $audit = $this->graded(['Bar item 1', 'Bar item 2', 'Kitchen item 1', 'Kitchen item 2', 'Pests']);
        $this->assertSame(75.0, (float) $audit->score_percent);
        $this->assertSame('fail', $audit->outcome);
        $this->assertTrue(collect($audit->outcome_reasons)->contains(fn ($r) => str_starts_with($r, 'Total 75.0% is below 80%')));
    }

    public function test_a_draft_has_a_projected_outcome_and_the_rules_are_frozen_at_start(): void
    {
        $svc   = app(AuditService::class);
        $audit = $svc->start($this->template, $this->outlet, $this->user, '2026-09-26');

        $this->assertSame('pass', $audit->fresh()->outcome, 'Nothing lost yet, so the projection is a pass.');
        $this->assertSame(90, $audit->fresh()->outcomeRules()['pass_percent']);

        // The form is tightened afterwards; this audit keeps 90.
        $this->template->update(['outcome_rules' => ['pass_percent' => 98]]);

        foreach ($audit->sections as $s) {
            $svc->markRemainingOk($s);
        }
        $svc->answer($audit->lines()->where('label', 'Bar item 1')->firstOrFail(), 'nc');   // 95%
        $svc->submit($audit, $this->user);

        $this->assertSame('pass', $audit->fresh()->outcome, 'Graded under the rules it started with.');

        $later = $this->graded(['Bar item 1']);
        $this->assertSame('conditional', $later->outcome, 'A new audit is graded under the tightened rules.');
        $this->assertSame(98, $later->outcomeRules()['pass_percent']);
    }

    public function test_the_builder_saves_rules_and_refuses_an_inverted_pair(): void
    {
        Livewire::test(TemplateEdit::class, ['id' => $this->template->id])
            ->set('outcomeRules.pass_percent', 85)
            ->set('outcomeRules.conditional_percent', 90)
            ->call('saveHeader')
            ->assertHasErrors('outcomeRules.conditional_percent');

        Livewire::test(TemplateEdit::class, ['id' => $this->template->id])
            ->set('outcomeRules.pass_percent', 92)
            ->set('outcomeRules.conditional_percent', 85)
            ->set('outcomeRules.max_major_conditional', 0)
            ->call('saveHeader')
            ->assertHasNoErrors();

        $rules = $this->template->fresh()->outcomeRules();
        $this->assertSame(92, $rules['pass_percent']);
        $this->assertSame(0, $rules['max_major_conditional']);
        $this->assertSame(80, $rules['section_pass_percent'], 'Untouched keys keep their defaults.');
    }

    public function test_the_list_shows_and_filters_by_outcome(): void
    {
        $this->graded([]);
        $this->graded(['Pests', 'Halal cert']);

        Livewire::test(AuditsIndex::class)
            ->assertSee('Pass')
            ->assertSee('Fail')
            ->set('outcomeFilter', 'fail')
            ->assertSee('Fail')
            ->assertDontSee('badge-success');
    }

    public function test_evaluate_skips_sections_with_nothing_applicable(): void
    {
        $verdict = AuditScoreService::evaluate(95.0, 0, [['name' => 'Restroom', 'percent' => null]], []);

        $this->assertSame('pass', $verdict['outcome']);
    }
}
