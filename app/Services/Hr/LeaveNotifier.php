<?php

namespace App\Services\Hr;

use App\Jobs\SendLeaveNotification;
use App\Models\LeaveNotification;
use App\Models\LeaveRequest;
use App\Models\TimeOffRequest;
use App\Scopes\CompanyScope;

/**
 * Tells the right people that leave has been applied for, or decided.
 *
 * The ROW IS WRITTEN BEFORE THE JOB IS DISPATCHED, so a queue that never
 * drains leaves a visible "queued" record rather than silence — the same rule
 * the payslip emails follow, and for the same reason: "did my manager get
 * told" must have an answer.
 *
 * Nothing here throws. A notification failing must never take an approval with
 * it: the leave is the fact, the email is the courtesy.
 */
class LeaveNotifier
{
    public function __construct(private LeaveApprovers $approvers)
    {
    }

    /** Applied for — tell whoever decides it. */
    public function submitted(LeaveRequest|TimeOffRequest $request): int
    {
        $employee = $request->employee;

        if (! $employee) {
            return 0;
        }

        $recipients = $this->approvers->forEmployee($employee);

        return $this->queue($request, LeaveNotification::SUBMITTED, $recipients->map(fn ($r) => [
            'email' => $r['email'],
            'name'  => $r['name'],
        ])->all());
    }

    /**
     * Decided — tell the person who asked.
     *
     * Only they are told. Copying the approvers on their own decision is the
     * kind of noise that makes people filter the whole sender.
     */
    public function decided(LeaveRequest|TimeOffRequest $request): int
    {
        $employee = $request->employee;

        if (! $employee || ! filter_var((string) $employee->email, FILTER_VALIDATE_EMAIL)) {
            return 0;
        }

        return $this->queue($request, LeaveNotification::DECIDED, [[
            'email' => trim($employee->email),
            'name'  => $employee->name,
        ]]);
    }

    /**
     * @param  array<int, array{email: string, name: string}>  $recipients
     */
    private function queue(LeaveRequest|TimeOffRequest $request, string $event, array $recipients): int
    {
        $isLeave = $request instanceof LeaveRequest;
        $queued  = 0;

        foreach ($recipients as $recipient) {
            try {
                $notification = LeaveNotification::withoutGlobalScope(CompanyScope::class)->create([
                    'company_id'          => $request->company_id,
                    'leave_request_id'    => $isLeave ? $request->id : null,
                    'time_off_request_id' => $isLeave ? null : $request->id,
                    'event'               => $event,
                    'email'               => $recipient['email'],
                    'recipient_name'      => $recipient['name'],
                    'status'              => LeaveNotification::QUEUED,
                ]);

                SendLeaveNotification::dispatch($notification->id);
                $queued++;
            } catch (\Throwable $e) {
                // Swallowed on purpose: the leave decision has already been
                // made and saved, and losing it because an email row would not
                // write would be far worse than a missing notification.
                report($e);
            }
        }

        return $queued;
    }
}
