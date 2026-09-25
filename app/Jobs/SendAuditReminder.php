<?php

namespace App\Jobs;

use App\Mail\Audits\AuditorReminderMail;
use App\Mail\Audits\OwnerReminderMail;
use App\Models\AuditReminder;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Audits\AuditReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends one audit reminder and records what happened on its row.
 *
 * The digest is REBUILT here, at send time, rather than serialised into the
 * queue: if an action is verified between the hourly run and the worker
 * picking this up, the email should not still be chasing it. Built the same
 * way as SendLeaveNotification — the row is the record, the transport throws
 * on failure, and the queue retries.
 */
class SendAuditReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [60, 300];

    public function __construct(public int $reminderId)
    {
    }

    public function handle(AuditReminderService $reminders): void
    {
        $reminder = AuditReminder::withoutGlobalScope(CompanyScope::class)->find($this->reminderId);

        if (! $reminder || $reminder->status === AuditReminder::SENT) {
            return;
        }

        $company = Company::withoutGlobalScopes()->find($reminder->company_id);

        if (! $company) {
            $reminder->update(['status' => AuditReminder::FAILED, 'error' => 'The company no longer exists.']);
            return;
        }

        $mailable = $this->build($reminder, $company, $reminders);

        if (! $mailable) {
            // Everything it was going to chase has been dealt with since. That
            // is a success, not a failure — record it as sent-with-nothing so
            // the day's throttle still holds and nobody gets a second run's copy.
            $reminder->update(['status' => AuditReminder::SENT, 'sent_at' => now(), 'error' => null, 'summary' => ['empty' => true]]);
            return;
        }

        try {
            Mail::to($reminder->email, $reminder->recipient_name)->send($mailable);

            $reminder->update(['status' => AuditReminder::SENT, 'sent_at' => now(), 'error' => null]);
        } catch (\Throwable $e) {
            $reminder->update(['status' => AuditReminder::FAILED, 'error' => mb_substr($e->getMessage(), 0, 500)]);

            Log::error('Audit reminder failed', ['reminder_id' => $reminder->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    private function build(AuditReminder $reminder, Company $company, AuditReminderService $reminders)
    {
        $brand = $company->brand_name ?: $company->name;

        if ($reminder->kind === AuditReminder::KIND_AUDITOR) {
            $user   = User::find($reminder->user_id);
            $digest = $user ? $reminders->auditorDigests($company)->first(fn ($d) => $d['user']->id === $user->id) : null;

            return $digest ? new AuditorReminderMail($digest, $brand) : null;
        }

        $employee = Employee::withoutGlobalScopes()->find($reminder->employee_id);
        $digest   = $employee ? $reminders->ownerDigests($company)->first(fn ($d) => $d['employee']->id === $employee->id) : null;

        return $digest ? new OwnerReminderMail($digest, $brand, AuditReminderService::staffActionsUrl($company)) : null;
    }

    public function failed(\Throwable $e): void
    {
        AuditReminder::withoutGlobalScope(CompanyScope::class)
            ->whereKey($this->reminderId)
            ->where('status', '!=', AuditReminder::SENT)
            ->update(['status' => AuditReminder::FAILED, 'error' => mb_substr($e->getMessage(), 0, 500)]);
    }
}
