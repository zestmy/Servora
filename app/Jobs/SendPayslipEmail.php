<?php

namespace App\Jobs;

use App\Mail\PayslipMail;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\PayslipDelivery;
use App\Scopes\CompanyScope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email one payslip, and record what happened.
 *
 * ONE JOB PER EMPLOYEE, not one per run: rendering fifty PDFs and sending
 * fifty emails inside one job means a failure at number thirty leaves twenty
 * people without a payslip and no way to tell which twenty. Per-employee, a
 * retry retries exactly one person.
 *
 * The delivery row is written by the CALLER before dispatch, so a job that
 * never runs — a dead worker, a full queue — still leaves a visible "queued"
 * record rather than silence.
 */
class SendPayslipEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Three attempts with a growing backoff: the realistic failure is a
     * temporary SMTP refusal, which a retry does fix. A bad address does not
     * improve on retry, so it is caught before dispatch instead.
     */
    public $tries = 3;

    public $backoff = [60, 300];

    public function __construct(public int $deliveryId) {}

    public function middleware(): array
    {
        // Payroll day sends the whole company at once; a burst is the fastest
        // way to have a provider treat the domain as a spam source.
        return [new RateLimited('payslip-email')];
    }

    public function handle(): void
    {
        // No authenticated user on the queue, so the company scope is dropped
        // by hand — every row carries its own company_id.
        $delivery = PayslipDelivery::withoutGlobalScope(CompanyScope::class)->find($this->deliveryId);

        if (! $delivery || $delivery->status === PayslipDelivery::SENT) {
            return;
        }

        $line = PayrollRunLine::withoutGlobalScope(CompanyScope::class)->find($delivery->payroll_run_line_id);
        $run  = PayrollRun::withoutGlobalScopes()->with('company')->find($delivery->payroll_run_id);

        if (! $line || ! $run) {
            $delivery->update([
                'status' => PayslipDelivery::FAILED,
                'error'  => 'The payroll run or line no longer exists.',
            ]);
            return;
        }

        $brandName = $run->company?->brand_name ?: $run->company?->name ?: 'Payroll';

        try {
            // The address on the DELIVERY, not the employee's current one: the
            // send was authorised against what was shown at the time, and an
            // edit between confirming and the queue draining must not silently
            // redirect someone's payslip.
            Mail::to($delivery->email)->send(new PayslipMail($run, $line, $brandName));

            $delivery->update([
                'status'  => PayslipDelivery::SENT,
                'sent_at' => now(),
                'error'   => null,
            ]);
        } catch (\Throwable $e) {
            $delivery->update([
                'status' => PayslipDelivery::FAILED,
                'error'  => mb_substr($e->getMessage(), 0, 500),
            ]);

            Log::error('Payslip email failed', [
                'delivery_id' => $delivery->id,
                'run_id'      => $run->id,
                'error'       => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /** Final failure after every retry — the row must not stay "queued" forever. */
    public function failed(\Throwable $e): void
    {
        PayslipDelivery::withoutGlobalScope(CompanyScope::class)
            ->where('id', $this->deliveryId)
            ->where('status', '!=', PayslipDelivery::SENT)
            ->update([
                'status' => PayslipDelivery::FAILED,
                'error'  => mb_substr($e->getMessage(), 0, 500),
            ]);
    }
}
