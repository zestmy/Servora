<?php

namespace Tests\Feature;

use App\Mail\Audits\AuditorReminderMail;
use App\Mail\Audits\OwnerReminderMail;
use App\Models\AuditReminder;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Daily reminders for overdue audits and corrective actions: who gets one,
 * what is in it, and that nobody gets two in a day or one about nothing.
 */
class AuditReminderTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $auditor;
    private Employee $chef;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        // The app timezone IS Asia/Kuala_Lumpur, so this is 08:15 local — the send hour.
        Carbon::setTestNow('2026-09-26 08:15:00');

        $this->company = Company::create([
            'name' => 'Reminder Co', 'slug' => Str::slug('Reminder Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true, 'timezone' => 'Asia/Kuala_Lumpur',
        ]);
        $this->outlet = Outlet::create(['company_id' => $this->company->id, 'name' => 'IOI City Mall', 'code' => 'IOI', 'is_active' => true]);

        $this->auditor = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true, 'email' => 'qa@example.test', 'name' => 'Syuhada',
        ]);
        $this->auditor->companies()->syncWithoutDetaching([$this->company->id]);
        $this->auditor->outlets()->syncWithoutDetaching([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $this->auditor->givePermissionTo(collect(['audits.view', 'audits.conduct', 'audits.actions.manage'])
            ->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->chef = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Chef Ali', 'designation' => 'Chef', 'is_active' => true, 'email' => 'ali@example.test',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function template(): AuditTemplate
    {
        $t = AuditTemplate::create(['company_id' => $this->company->id, 'name' => 'Mini', 'code' => 'MINI']);
        $s = AuditTemplateSection::create(['audit_template_id' => $t->id, 'name' => 'Bar', 'sort_order' => 0]);
        AuditTemplateItem::create(['audit_template_section_id' => $s->id, 'number' => '1', 'label' => 'Chiller', 'points' => 2, 'sort_order' => 0]);

        return $t;
    }

    /** A submitted audit with one NC and one overdue action owned by the chef. */
    private function overdueAction(string $due = '2026-09-10'): CorrectiveAction
    {
        $svc   = app(AuditService::class);
        $audit = $svc->start($this->template(), $this->outlet, $this->auditor, '2026-09-01');
        $svc->answer($audit->lines()->where('label', 'Chiller')->firstOrFail(), 'nc');
        $svc->submit($audit, $this->auditor);

        return app(CorrectiveActionService::class)->create($audit->findings()->firstOrFail(), $this->auditor, [
            'owner_employee_id' => $this->chef->id, 'description' => 'Fix the chiller seal', 'due_date' => $due,
        ]);
    }

    public function test_an_overdue_schedule_and_action_produce_one_auditor_digest_and_one_owner_reminder(): void
    {
        AuditSchedule::create([
            'company_id' => $this->company->id, 'audit_template_id' => $this->template()->id,
            'outlet_id' => $this->outlet->id, 'frequency' => 'quarterly', 'next_due_on' => '2026-09-01',
        ]);
        $this->overdueAction();

        $this->artisan('audits:send-reminders')->assertSuccessful();

        Mail::assertSent(AuditorReminderMail::class, function (AuditorReminderMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('qa@example.test')
                && str_contains($mail->envelope()->subject, '1 overdue audit')
                && str_contains($mail->envelope()->subject, '1 overdue corrective action')
                && str_contains($html, 'IOI City Mall')
                && str_contains($html, 'Fix the chiller seal')
                && str_contains($html, 'filter=overdue');
        });

        Mail::assertSent(OwnerReminderMail::class, function (OwnerReminderMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('ali@example.test')
                && str_contains($mail->envelope()->subject, '1 audit fix is overdue')
                && str_contains($html, 'Fix the chiller seal')
                && str_contains($html, '/staff/actions');
        });

        $this->assertSame(2, AuditReminder::withoutGlobalScopes()->where('status', 'sent')->count());
    }

    public function test_nobody_is_reminded_twice_in_a_day_or_about_nothing(): void
    {
        $this->overdueAction();

        $this->artisan('audits:send-reminders');
        $this->artisan('audits:send-reminders');

        Mail::assertSent(OwnerReminderMail::class, 1);
        Mail::assertSent(AuditorReminderMail::class, 1);

        // Everything dealt with: a fresh day sends nothing at all.
        app(CorrectiveActionService::class)->verify(CorrectiveAction::firstOrFail(), $this->auditor);
        Carbon::setTestNow('2026-09-27 08:15:00');

        $this->artisan('audits:send-reminders');

        Mail::assertSent(OwnerReminderMail::class, 1);
        Mail::assertSent(AuditorReminderMail::class, 1);
    }

    public function test_reminders_go_out_only_in_the_companys_eight_oclock_hour_unless_forced(): void
    {
        $this->overdueAction();

        Carbon::setTestNow('2026-09-26 14:00:00'); // afternoon in Kuala Lumpur
        $this->artisan('audits:send-reminders');
        Mail::assertNothingSent();

        $this->artisan('audits:send-reminders --force');
        Mail::assertSent(OwnerReminderMail::class, 1);
    }

    public function test_an_action_due_today_or_verified_is_not_overdue_and_an_owner_without_email_is_skipped(): void
    {
        $this->overdueAction('2026-09-26');   // due today: not overdue

        $this->artisan('audits:send-reminders');
        Mail::assertNothingSent();

        $late = $this->overdueAction('2026-09-01');
        $this->chef->update(['email' => null]);

        $this->artisan('audits:send-reminders');

        Mail::assertNotSent(OwnerReminderMail::class);
        Mail::assertSent(AuditorReminderMail::class, 1);

        // Both verified — including the one due today, which would otherwise
        // be overdue tomorrow — so the next morning has nothing to chase.
        CorrectiveAction::all()->each(fn ($a) => app(CorrectiveActionService::class)->verify($a, $this->auditor));
        Carbon::setTestNow('2026-09-27 08:15:00');
        $this->artisan('audits:send-reminders');
        Mail::assertSent(AuditorReminderMail::class, 1);
    }

    public function test_a_finding_left_without_an_action_is_chased_after_three_days(): void
    {
        $svc   = app(AuditService::class);
        $audit = $svc->start($this->template(), $this->outlet, $this->auditor, '2026-09-20');
        $svc->answer($audit->lines()->where('label', 'Chiller')->firstOrFail(), 'nc');
        $svc->submit($audit, $this->auditor);
        $audit->forceFill(['submitted_at' => '2026-09-20 10:00:00'])->save();

        $this->artisan('audits:send-reminders');

        Mail::assertSent(AuditorReminderMail::class, fn (AuditorReminderMail $mail) =>
            str_contains($mail->envelope()->subject, '1 finding with no action')
            && str_contains($mail->render(), 'status=unassigned'));
    }

    public function test_dry_run_sends_and_records_nothing(): void
    {
        $this->overdueAction();

        $this->artisan('audits:send-reminders --dry-run')
            ->expectsOutputToContain('1 owner reminder(s)')
            ->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('audit_reminders', 0);
    }
}
