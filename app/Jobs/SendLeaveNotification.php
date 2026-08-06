<?php

namespace App\Jobs;

use App\Mail\Hr\LeaveDecided;
use App\Mail\Hr\LeaveSubmitted;
use App\Models\LeaveNotification;
use App\Models\LeaveRequest;
use App\Models\TimeOffRequest;
use App\Scopes\CompanyScope;
use App\Services\Hr\LeaveBalance;
use App\Services\Hr\TimeOffBalance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send one leave notification, and record what happened.
 *
 * ONE JOB PER RECIPIENT, the same reasoning as the payslip emails: a failure
 * sending to the second of three approvers must not take the other two with
 * it, and a retry must retry one person rather than the batch.
 *
 * Never sent inline. Applying for leave is a thing someone does on a phone in
 * a corridor; it must not wait on, or fail because of, a mail server.
 */
class SendLeaveNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [60, 300];

    public function __construct(public int $notificationId) {}

    public function middleware(): array
    {
        // Shares the payslip limiter: both are staff email from the same
        // domain, and the reason for pacing is the domain's reputation, not
        // the individual feature.
        return [new RateLimited('payslip-email')];
    }

    public function handle(): void
    {
        $notification = LeaveNotification::withoutGlobalScope(CompanyScope::class)
            ->find($this->notificationId);

        if (! $notification || $notification->status === LeaveNotification::SENT) {
            return;
        }

        $mailable = $this->build($notification);

        if (! $mailable) {
            $notification->update([
                'status' => LeaveNotification::FAILED,
                'error'  => 'The request this related to no longer exists.',
            ]);
            return;
        }

        try {
            Mail::to($notification->email)->send($mailable);

            $notification->update([
                'status'  => LeaveNotification::SENT,
                'sent_at' => now(),
                'error'   => null,
            ]);
        } catch (\Throwable $e) {
            $notification->update([
                'status' => LeaveNotification::FAILED,
                'error'  => mb_substr($e->getMessage(), 0, 500),
            ]);

            Log::error('Leave notification failed', [
                'notification_id' => $notification->id,
                'error'           => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function build(LeaveNotification $notification)
    {
        $brand = fn ($companyId) => optional(
            \App\Models\Company::withoutGlobalScopes()->find($companyId)
        )->brand_name ?: optional(
            \App\Models\Company::withoutGlobalScopes()->find($companyId)
        )->name ?: 'Payroll';

        if ($notification->leave_request_id) {
            $request = LeaveRequest::withoutGlobalScope(CompanyScope::class)
                ->with(['employee', 'leaveType', 'approver'])
                ->find($notification->leave_request_id);

            if (! $request || ! $request->employee) {
                return null;
            }

            $dates = $request->start_date->format('d M Y')
                . ($request->start_date->isSameDay($request->end_date)
                    ? '' : ' – ' . $request->end_date->format('d M Y'));

            $days = rtrim(rtrim(number_format((float) $request->days, 1), '0'), '.') . ' day(s)';

            if ($notification->event === LeaveNotification::SUBMITTED) {
                $remaining = app(LeaveBalance::class)->remainingFor(
                    $request->employee, $request->leaveType, $request->balanceYear()
                );

                return new LeaveSubmitted(
                    recipientName: $notification->recipient_name ?: 'there',
                    employeeName:  $request->employee->name,
                    typeName:      $request->leaveType?->name ?? 'leave',
                    dates:         $dates,
                    days:          $days,
                    reason:        $request->reason,
                    remaining:     rtrim(rtrim(number_format($remaining, 1), '0'), '.') . ' day(s)',
                    brandName:     $brand($notification->company_id),
                    url:           route('hr.leave'),
                );
            }

            return new LeaveDecided(
                employeeName: $request->employee->name,
                typeName:     $request->leaveType?->name ?? 'leave',
                dates:        $dates,
                days:         $days,
                outcome:      $request->statusLabel(),
                decisionNote: $request->decision_note,
                decidedBy:    $request->approver?->name,
                brandName:    $brand($notification->company_id),
            );
        }

        $request = TimeOffRequest::withoutGlobalScope(CompanyScope::class)
            ->with(['employee', 'approver'])
            ->find($notification->time_off_request_id);

        if (! $request || ! $request->employee) {
            return null;
        }

        $hours = rtrim(rtrim(number_format((float) $request->hours, 2), '0'), '.') . ' hour(s)';

        if ($notification->event === LeaveNotification::SUBMITTED) {
            return new LeaveSubmitted(
                recipientName: $notification->recipient_name ?: 'there',
                employeeName:  $request->employee->name,
                typeName:      'time off',
                dates:         $request->off_date->format('d M Y'),
                days:          $hours,
                reason:        $request->reason,
                remaining:     rtrim(rtrim(number_format(
                    app(TimeOffBalance::class)->availableHours($request->employee), 2), '0'), '.') . ' hour(s)',
                brandName:     $brand($notification->company_id),
                url:           route('hr.time-off'),
                isTimeOff:     true,
            );
        }

        return new LeaveDecided(
            employeeName: $request->employee->name,
            typeName:     'time off',
            dates:        $request->off_date->format('d M Y'),
            days:         $hours,
            outcome:      $request->statusLabel(),
            decisionNote: $request->decision_note,
            decidedBy:    $request->approver?->name,
            brandName:    $brand($notification->company_id),
            isTimeOff:    true,
        );
    }

    /** Final failure — the row must not sit "queued" for ever. */
    public function failed(\Throwable $e): void
    {
        LeaveNotification::withoutGlobalScope(CompanyScope::class)
            ->where('id', $this->notificationId)
            ->where('status', '!=', LeaveNotification::SENT)
            ->update([
                'status' => LeaveNotification::FAILED,
                'error'  => mb_substr($e->getMessage(), 0, 500),
            ]);
    }
}
