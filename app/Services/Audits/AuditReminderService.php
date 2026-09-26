<?php

namespace App\Services\Audits;

use App\Jobs\SendAuditReminder;
use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\AuditReminder;
use App\Models\AuditSchedule;
use App\Models\Company;
use App\Models\CorrectiveAction;
use App\Models\Employee;
use App\Models\User;
use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Who needs telling that something audit-related is overdue, and what.
 *
 * TWO AUDIENCES, two emails:
 *
 *   Auditors — web users holding `audits.conduct` — get one digest: the
 *   scheduled audits that are overdue (assigned to them, or unassigned and at
 *   an outlet they can reach), the corrective actions overdue at those
 *   outlets, and findings on submitted audits that nobody has raised an
 *   action against yet. They are the people who verify, so the whole picture
 *   is theirs.
 *
 *   Owners — employees named on an overdue corrective action — get only
 *   their own list, with a link to "Audit fixes" on the Staff Portal. An
 *   employee has no web login; the portal is where they can act.
 *
 * ONCE A DAY, at 08:00 in the company's own timezone: the command runs
 * hourly and this service decides whether it is that hour. The
 * `audit_reminders` unique key makes a second run in the same hour a no-op.
 * Nobody is emailed about nothing — a recipient with an empty digest is
 * skipped, not sent a cheerful "all clear".
 */
class AuditReminderService
{
    public const SEND_HOUR = 8;

    /** Findings with no action are chased after this many days. */
    public const UNACTIONED_AFTER_DAYS = 3;

    /** A re-audit is mentioned from this many days before it is due. */
    public const REAUDIT_SOON_DAYS = 7;

    /** @return array{auditors:int, owners:int, skipped:string|null} */
    public function sendForCompany(Company $company, bool $force = false, bool $dryRun = false): array
    {
        $tz    = $company->timezone ?: config('app.timezone');
        $local = Carbon::now($tz);

        if (! $force && $local->hour !== self::SEND_HOUR) {
            return ['auditors' => 0, 'owners' => 0, 'skipped' => "not {$local->format('H:00')} → " . sprintf('%02d:00', self::SEND_HOUR) . " in {$tz}"];
        }

        $today = $local->toDateString();
        $sent  = ['auditors' => 0, 'owners' => 0, 'skipped' => null];

        foreach ($this->auditorDigests($company) as $digest) {
            if ($this->queue($company, AuditReminder::KIND_AUDITOR, $digest, $today, $dryRun)) {
                $sent['auditors']++;
            }
        }

        foreach ($this->ownerDigests($company) as $digest) {
            if ($this->queue($company, AuditReminder::KIND_OWNER, $digest, $today, $dryRun)) {
                $sent['owners']++;
            }
        }

        return $sent;
    }

    // ── Auditors ─────────────────────────────────────────────────────────

    /**
     * @return Collection<int, array{user:User, schedules:Collection, actions:Collection, unactioned:Collection}>
     */
    public function auditorDigests(Company $company): Collection
    {
        setPermissionsTeamId($company->id);

        $auditors = $company->members()
            ->where('users.company_id', $company->id)   // active in this company, so their outlet access resolves here
            ->whereNotNull('users.email')
            ->get()
            ->filter(fn (User $u) => $u->canDo('audits.conduct'));

        if ($auditors->isEmpty()) {
            return collect();
        }

        $schedules = AuditSchedule::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->overdue()
            ->with(['template', 'outlet'])
            ->orderBy('next_due_on')
            ->get();

        $actions = CorrectiveAction::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->overdue()
            ->with(['owner', 'outlet', 'finding'])
            ->orderBy('due_date')
            ->get();

        $reaudits = Audit::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->reauditOutstanding()
            ->whereDate('reaudit_due_on', '<=', now()->addDays(self::REAUDIT_SOON_DAYS)->toDateString())
            ->with(['outlet'])
            ->orderBy('reaudit_due_on')
            ->get();

        $unactioned = AuditFinding::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('status', AuditFinding::STATUS_OPEN)
            ->doesntHave('actions')
            ->whereHas('audit', fn ($q) => $q->where('status', '!=', 'draft')
                ->where('submitted_at', '<=', now()->subDays(self::UNACTIONED_AFTER_DAYS)))
            ->with(['outlet', 'audit'])
            ->orderBy('id')
            ->get();

        return $auditors->map(function (User $user) use ($schedules, $actions, $unactioned, $reaudits) {
            $outletIds = $user->accessibleOutletIds();
            $reach     = fn ($row) => in_array((int) $row->outlet_id, $outletIds, true);

            $mine = [
                'user'       => $user,
                'schedules'  => $schedules->filter(fn ($s) => $s->assigned_user_id === $user->id
                    || ($s->assigned_user_id === null && $reach($s)))->values(),
                'actions'    => $actions->filter($reach)->values(),
                'unactioned' => $unactioned->filter($reach)->values(),
                'reaudits'   => $reaudits->filter($reach)->values(),
            ];

            return $mine['schedules']->isEmpty() && $mine['actions']->isEmpty() && $mine['unactioned']->isEmpty() && $mine['reaudits']->isEmpty()
                ? null
                : $mine;
        })->filter()->values();
    }

    // ── Owners ───────────────────────────────────────────────────────────

    /** @return Collection<int, array{employee:Employee, actions:Collection}> */
    public function ownerDigests(Company $company): Collection
    {
        return CorrectiveAction::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->overdue()
            ->whereNotNull('owner_employee_id')
            ->with(['owner', 'finding.audit', 'outlet'])
            ->orderBy('due_date')
            ->get()
            ->filter(fn ($a) => $a->owner && $a->owner->is_active && filter_var(trim((string) $a->owner->email), FILTER_VALIDATE_EMAIL))
            ->groupBy('owner_employee_id')
            ->map(fn ($rows) => ['employee' => $rows->first()->owner, 'actions' => $rows->values()])
            ->values();
    }

    // ── Queueing ─────────────────────────────────────────────────────────

    private function queue(Company $company, string $kind, array $digest, string $today, bool $dryRun): bool
    {
        $isAuditor = $kind === AuditReminder::KIND_AUDITOR;
        $recipient = $isAuditor ? $digest['user'] : $digest['employee'];
        $email     = trim((string) $recipient->email);

        if ($email === '') {
            return false;
        }

        $summary = $isAuditor
            ? ['schedules' => $digest['schedules']->count(), 'actions' => $digest['actions']->count(), 'unactioned' => $digest['unactioned']->count(), 'reaudits' => $digest['reaudits']->count()]
            : ['actions' => $digest['actions']->count()];

        if ($dryRun) {
            return true;
        }

        // The unique key is the throttle; a duplicate insert is "already told today".
        $already = AuditReminder::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('kind', $kind)
            ->where('email', $email)
            ->whereDate('sent_on', $today)
            ->exists();

        if ($already) {
            return false;
        }

        try {
            $reminder = AuditReminder::withoutGlobalScope(CompanyScope::class)->create([
                'company_id'     => $company->id,
                'kind'           => $kind,
                'user_id'        => $isAuditor ? $recipient->id : null,
                'employee_id'    => $isAuditor ? null : $recipient->id,
                'email'          => $email,
                'recipient_name' => $recipient->name,
                'sent_on'        => $today,
                'summary'        => $summary,
                'status'         => AuditReminder::QUEUED,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return false;
        }

        SendAuditReminder::dispatch($reminder->id);

        return true;
    }

    // ── Links ────────────────────────────────────────────────────────────

    /**
     * The Staff Portal "Audit fixes" page for a company: its subdomain in
     * production, the plain path where there is no domain to constrain on.
     * Same rule as StaffPins::staffAppUrl().
     */
    public static function staffActionsUrl(Company $company): string
    {
        $domain = config('app.domain');

        if (! $domain || ! $company->slug) {
            return url('/staff/actions');
        }

        return 'https://' . $company->slug . '.' . $domain . '/staff/actions';
    }
}
