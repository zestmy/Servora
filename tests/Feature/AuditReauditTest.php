<?php

namespace Tests\Feature;

use App\Livewire\Audits\Index as AuditsIndex;
use App\Livewire\Audits\Start;
use App\Mail\Audits\AuditorReminderMail;
use App\Models\Audit;
use App\Models\AuditTemplate;
use App\Models\AuditTemplateItem;
use App\Models\AuditTemplateSection;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Audits\AuditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A conditional pass is a promise to come back: the due date, the follow-up
 * that settles it, the strip that shows it, and the digest that chases it.
 */
class AuditReauditTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private AuditTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Carbon::setTestNow('2026-09-26 08:15:00');

        $this->company = Company::create(['name' => 'Reaudit Co', 'slug' => Str::slug('Reaudit Co') . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true, 'timezone' => 'Asia/Kuala_Lumpur']);
        $this->outlet  = Outlet::create(['company_id' => $this->company->id, 'name' => 'IOI City Mall', 'code' => 'IOI', 'is_active' => true]);

        $this->user = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true, 'email' => 'qa@example.test']);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(collect(['audits.view', 'audits.conduct', 'audits.reopen'])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->user);

        // Critical 1 × 10 (penalty); Bar 10 × 10 = 100. A failed critical on a clean bar = 90% with one major → conditional.
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

    private function submitted(string $date, array $nc, array $attrs = []): Audit
    {
        $svc   = app(AuditService::class);
        $audit = $svc->start($this->template, $this->outlet, $this->user, $date, $attrs);
        foreach ($nc as $label) {
            $svc->answer($audit->lines()->where('label', $label)->firstOrFail(), 'nc');
        }
        foreach ($audit->sections as $s) {
            $svc->markRemainingOk($s);
        }

        return $svc->submit($audit, $this->user)->fresh();
    }

    public function test_a_conditional_pass_gets_a_reaudit_due_date_and_a_pass_does_not(): void
    {
        $conditional = $this->submitted('2026-09-01', ['Pests']);

        $this->assertSame('conditional', $conditional->outcome);
        $this->assertSame('2026-10-01', $conditional->reaudit_due_on->toDateString(), "Audit date plus the form's 30 days.");
        $this->assertTrue($conditional->needsReaudit());

        // A clean pass gets no due date — and, dated after the conditional at
        // the same outlet, it is the re-audit that settles it.
        $pass = $this->submitted('2026-09-02', []);
        $this->assertNull($pass->reaudit_due_on);
        $this->assertSame($conditional->id, $pass->reaudit_of_id);
        $this->assertFalse($conditional->fresh()->needsReaudit());
    }

    public function test_starting_from_the_button_links_the_follow_up_and_submitting_it_settles_the_reaudit(): void
    {
        $conditional = $this->submitted('2026-08-20', ['Pests']);
        $this->assertTrue($conditional->isReauditOverdue(), 'Due 19 Sep, today is 26 Sep.');

        Livewire::withQueryParams(['reaudit' => $conditional->id])
            ->test(Start::class)
            ->assertSet('reauditOfId', $conditional->id)
            ->assertSet('templateId', (string) $this->template->id)
            ->call('start')
            ->assertRedirect();

        $followUp = Audit::where('reaudit_of_id', $conditional->id)->firstOrFail();
        $this->assertTrue($conditional->fresh()->needsReaudit(), 'A draft follow-up settles nothing yet.');

        $svc = app(AuditService::class);
        foreach ($followUp->sections as $s) {
            $svc->markRemainingOk($s);
        }
        $svc->submit($followUp, $this->user);

        $this->assertFalse($conditional->fresh()->needsReaudit());
        $this->assertSame($followUp->id, $conditional->fresh()->completedReaudit()->id);
    }

    public function test_an_ordinary_later_audit_of_the_same_form_and_outlet_counts_as_the_reaudit(): void
    {
        $conditional = $this->submitted('2026-09-01', ['Pests']);
        $later       = $this->submitted('2026-09-20', []);

        $this->assertSame($conditional->id, $later->reaudit_of_id);
        $this->assertFalse($conditional->fresh()->needsReaudit());

        // …but not one at another outlet.
        $other       = Outlet::create(['company_id' => $this->company->id, 'name' => 'Elsewhere', 'code' => 'ELS', 'is_active' => true]);
        $this->user->outlets()->syncWithoutDetaching([$other->id]);
        $conditional2 = $this->submitted('2026-09-21', ['Pests']);
        $svc = app(AuditService::class);
        $elsewhere = $svc->start($this->template, $other, $this->user, '2026-09-22');
        foreach ($elsewhere->sections as $s) { $svc->markRemainingOk($s); }
        $svc->submit($elsewhere, $this->user);

        $this->assertNull($elsewhere->fresh()->reaudit_of_id);
        $this->assertTrue($conditional2->fresh()->needsReaudit());
    }

    public function test_reopening_clears_the_due_date_and_resubmitting_re_derives_it(): void
    {
        $conditional = $this->submitted('2026-09-01', ['Pests']);
        $svc = app(AuditService::class);

        $svc->reopen($conditional);
        $this->assertNull($conditional->fresh()->reaudit_due_on);

        // The critical item turns out to be a mis-tap: now a clean pass.
        $svc->answer($conditional->lines()->where('label', 'Pests')->firstOrFail(), 'ok');
        $svc->submit($conditional->fresh(), $this->user);

        $this->assertSame('pass', $conditional->fresh()->outcome);
        $this->assertNull($conditional->fresh()->reaudit_due_on);
    }

    public function test_the_list_shows_the_strip_and_filters_to_reaudits_due(): void
    {
        $this->submitted('2026-08-20', ['Pests']);   // overdue
        $this->submitted('2026-09-24', []);          // a clean pass at the same outlet… but dated AFTER, so it settled it.

        // Make one that is genuinely waiting: a second conditional after the pass.
        $waiting = $this->submitted('2026-09-25', ['Pests']);

        Livewire::test(AuditsIndex::class)
            ->assertSee('waiting for a re-audit')
            ->set('outcomeFilter', 'reaudit_due')
            ->assertSee('re-audit by 25 Oct')
            ->assertDontSee('badge-success');

        $this->assertTrue($waiting->needsReaudit());
    }

    public function test_the_auditor_digest_chases_a_reaudit_due_within_a_week_and_stops_once_done(): void
    {
        $conditional = $this->submitted('2026-09-01', ['Pests']);   // due 1 Oct: 5 days away

        $this->artisan('audits:send-reminders');

        Mail::assertSent(AuditorReminderMail::class, fn (AuditorReminderMail $mail) =>
            str_contains($mail->envelope()->subject, '1 re-audit due')
            && str_contains($mail->render(), '01 Oct 2026'));

        // The follow-up settles it; the next day's run has nothing to say.
        $this->submitted('2026-09-26', []);
        Carbon::setTestNow('2026-09-27 08:15:00');
        $this->artisan('audits:send-reminders');

        Mail::assertSent(AuditorReminderMail::class, 1);
    }

    public function test_the_schedule_page_lists_the_reaudit_beside_recurring_schedules(): void
    {
        $conditional = $this->submitted('2026-08-20', ['Pests']);   // re-audit due 19 Sep: overdue

        \App\Models\AuditSchedule::create([
            'company_id' => $this->company->id, 'audit_template_id' => $this->template->id,
            'outlet_id' => $this->outlet->id, 'frequency' => 'quarterly', 'next_due_on' => '2026-12-01',
        ]);

        Livewire::test(\App\Livewire\Audits\Schedules::class)
            ->assertSee('Re-audit')
            ->assertSee('19 Sep 2026')
            ->assertSee('Start re-audit')
            ->assertSee('01 Dec 2026')          // the recurring schedule's row
            ->assertSee('Overdue (1)')
            ->set('filter', 'overdue')
            ->assertSee('Re-audit')
            ->assertDontSee('01 Dec 2026');     // the schedule is not overdue; only the re-audit is

        // Settled: the row leaves the page.
        $this->submitted('2026-09-26', []);

        Livewire::test(\App\Livewire\Audits\Schedules::class)
            ->assertDontSee('Start re-audit')
            ->assertSee('01 Dec 2026');
    }

    public function test_a_reaudit_more_than_a_week_away_is_not_chased_yet(): void
    {
        $this->submitted('2026-09-20', ['Pests']);   // due 20 Oct

        $this->artisan('audits:send-reminders');

        Mail::assertNothingSent();
    }
}
