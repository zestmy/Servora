<?php

namespace App\Livewire\Hr;

use App\Jobs\SendPayslipEmail;
use App\Models\PayrollRun;
use App\Models\PayslipDelivery;
use App\Services\Payroll\PayrollRunBuilder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * One payroll run: every line, and the three things you can do to it —
 * regenerate while it is a draft, approve it, then record that it was paid.
 *
 * Approval is the point of no return, so the screen states what is incomplete
 * BEFORE it is pressed rather than after: missing bank details, unconfirmed
 * statutory rates, employees whose overtime could not be priced.
 */
class PayrollRunShow extends Component
{
    /** Route key is the run's UUID — see PayrollRun::getRouteKeyName(). */
    public string $runUuid;

    public bool   $showApprove = false;
    public bool   $showPaid    = false;
    public bool   $showEmail   = false;
    public bool   $resendSent  = false;
    public string $paymentDate = '';
    public string $notes       = '';

    public function mount(string $run): void
    {
        $this->runUuid = $run;

        $model = $this->run();
        $this->notes       = (string) $model->notes;
        $this->paymentDate = now()->format('Y-m-d');
    }

    /**
     * Company-scoped by the global scope, so another tenant's UUID 404s.
     *
     * Memoised per request: render(), emailAudience() and the actions all ask
     * for it, and a lookup each would be four queries for one row. Cleared by
     * anything that writes, so a status change is not read back stale.
     */
    private ?PayrollRun $cachedRun = null;

    public function run(): PayrollRun
    {
        return $this->cachedRun ??= PayrollRun::with('outlet:id,name', 'generatedBy:id,name', 'approvedBy:id,name')
            ->where('uuid', $this->runUuid)
            ->firstOrFail();
    }

    private function forgetRun(): void
    {
        $this->cachedRun = null;
    }

    protected function accessibleOutletIds(): array
    {
        return Auth::user()->accessibleOutletIds();
    }

    public function regenerate(): void
    {
        $run = $this->run();

        if (! $run->isEditable()) {
            session()->flash('error', 'An approved payroll run cannot be regenerated.');
            return;
        }

        try {
            app(PayrollRunBuilder::class)->generate(
                $run->company_id,
                $this->accessibleOutletIds(),
                $run->period_month,
                $run->outlet_id,
                Auth::id(),
            );
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        $this->forgetRun();
        session()->flash('success', 'Payroll regenerated from current figures.');
    }

    public function approve(): void
    {
        // A separate permission from running payroll: generating is clerical,
        // approving commits the company to paying these amounts.
        abort_unless(Auth::user()->can('hr.payroll.approve'), 403);

        $run = $this->run();

        if ($run->isApproved()) {
            session()->flash('error', 'This run is already approved.');
            return;
        }

        if ($run->employee_count === 0) {
            session()->flash('error', 'There is nothing to approve: this run has no employees.');
            return;
        }

        $run->update([
            'status'      => PayrollRun::APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'notes'       => $this->notes ?: null,
        ]);

        $this->showApprove = false;
        $this->forgetRun();
        session()->flash('success', 'Payroll approved. The figures are now locked.');
    }

    public function markPaid(): void
    {
        abort_unless(Auth::user()->can('hr.payroll.approve'), 403);

        $this->validate(['paymentDate' => 'required|date'], [], ['paymentDate' => 'payment date']);

        $run = $this->run();

        // Paid implies approved: money must not leave against a draft.
        if (! $run->isApproved()) {
            session()->flash('error', 'Approve the run before recording payment.');
            return;
        }

        $run->update([
            'status'       => PayrollRun::PAID,
            'paid_at'      => now(),
            'payment_date' => $this->paymentDate,
        ]);

        $this->showPaid = false;
        $this->forgetRun();
        session()->flash('success', 'Payroll marked as paid.');
    }

    /**
     * Who a payslip can actually be emailed to, and who it cannot.
     *
     * The address comes from the employee record rather than the line, because
     * that is where it is maintained — but it is validated here and copied onto
     * the delivery, so what was authorised is what gets used.
     *
     * @return array{sendable: \Illuminate\Support\Collection, blocked: \Illuminate\Support\Collection, alreadySent: \Illuminate\Support\Collection}
     */
    public function emailAudience(): array
    {
        $lines = $this->run()->lines()->with('employee:id,email')->orderBy('employee_name')->get();

        $sentLineIds = PayslipDelivery::where('payroll_run_id', $this->run()->id)
            ->where('status', PayslipDelivery::SENT)
            ->pluck('payroll_run_line_id')
            ->all();

        $withEmail = $lines->map(function ($line) {
            $email = trim((string) $line->employee?->email);

            return [
                'line'  => $line,
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                'raw'   => $email,
            ];
        });

        return [
            // A malformed address is BLOCKED, not attempted: the queue would
            // just fail on it, and "we tried" is not better than "we told you".
            'sendable'    => $withEmail->filter(fn ($r) => $r['email'] !== null
                && ($this->resendSent || ! in_array($r['line']->id, $sentLineIds, true)))->values(),
            'blocked'     => $withEmail->filter(fn ($r) => $r['email'] === null)->values(),
            'alreadySent' => $withEmail->filter(fn ($r) => in_array($r['line']->id, $sentLineIds, true))->values(),
        ];
    }

    /**
     * Queue the payslip emails.
     *
     * Deliberately NOT fired by approval. Sending someone's pay details out is
     * its own decision, taken once the figures have been looked at, and an
     * approval that silently emailed fifty people would be impossible to undo.
     */
    public function sendPayslips(): void
    {
        abort_unless(Auth::user()->can('hr.payroll'), 403);

        $run = $this->run();

        if (! $run->isApproved()) {
            session()->flash('error', 'Approve the run before emailing payslips.');
            return;
        }

        $audience = $this->emailAudience();

        if ($audience['sendable']->isEmpty()) {
            session()->flash('error', 'Nobody to send to — no employee in this run has a valid email address'
                . ($audience['alreadySent']->isNotEmpty() && ! $this->resendSent
                    ? ' that has not already been sent to.' : '.'));
            return;
        }

        foreach ($audience['sendable'] as $row) {
            // The row is written BEFORE dispatch, so a job that never runs
            // still leaves a visible "queued" record rather than silence.
            $delivery = PayslipDelivery::create([
                'company_id'          => $run->company_id,
                'payroll_run_id'      => $run->id,
                'payroll_run_line_id' => $row['line']->id,
                'employee_id'         => $row['line']->employee_id,
                'email'               => $row['email'],
                'status'              => PayslipDelivery::QUEUED,
                'queued_by'           => Auth::id(),
            ]);

            SendPayslipEmail::dispatch($delivery->id);
        }

        $this->showEmail = false;
        session()->flash('success', $audience['sendable']->count() . ' payslip(s) queued for sending.');
    }

    public function render()
    {
        $run   = $this->run();
        $lines = $run->lines()->orderBy('employee_name')->get();

        // Said before approval, not after: these are the reasons a run is not
        // ready, and the point of showing them is that they are still fixable.
        $warnings = [];

        $noBank = $lines->filter(fn ($l) => $l->missingForPayment() !== []);
        if ($noBank->isNotEmpty()) {
            $warnings[] = $noBank->count() . ' employee(s) have no bank details — they cannot be included in a payment file.';
        }

        $noIc = $lines->whereNull('ic_number');
        if ($noIc->isNotEmpty()) {
            $warnings[] = $noIc->count() . ' employee(s) have no IC number — statutory submissions require it.';
        }

        $unpriced = $lines->filter(fn ($l) => collect($l->statutory_notes ?? [])
            ->contains(fn ($n) => str_contains($n, 'could not be priced')));
        if ($unpriced->isNotEmpty()) {
            $warnings[] = $unpriced->count() . ' employee(s) have overtime that could not be priced: no salary on record.';
        }

        if (! $run->rates_were_confirmed) {
            $warnings[] = 'Statutory rates were not confirmed when this run was generated — EPF, SOCSO, EIS and PCB are estimates.';
        }

        return view('livewire.hr.payroll-run-show', [
            'run'        => $run,
            'lines'      => $lines,
            'warnings'   => $warnings,
            'canApprove' => Auth::user()->can('hr.payroll.approve'),
            'audience'   => $this->emailAudience(),
            'deliveries' => PayslipDelivery::where('payroll_run_id', $run->id)
                ->orderByDesc('id')
                ->get()
                ->groupBy('payroll_run_line_id'),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Payroll — ' . $run->periodLabel()]);
    }
}
