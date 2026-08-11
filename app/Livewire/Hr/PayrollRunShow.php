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

    /*
     * One-off corrections on this run — an overpayment being recovered, a
     * previous month's shortfall being settled. Entered here rather than on
     * the employee's compensation screen because they belong to THIS run and
     * nothing else; see PayrollRunAdjustment.
     */
    public bool    $showAdjust        = false;
    public ?int    $adjustmentId      = null;
    public string  $adj_employee_id   = '';
    public string  $adj_label         = '';
    public string  $adj_amount        = '';
    public string  $adj_direction     = \App\Models\PayrollRunAdjustment::DEDUCTION;
    public bool    $adj_affects_statutory = false;
    public string  $adj_notes         = '';

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
        return $this->cachedRun ??= PayrollRun::with('outlet:id,name', 'section:id,name', 'generatedBy:id,name', 'approvedBy:id,name')
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
                null,
                null,
                // Passed back, not defaulted. A segmented run regenerated
                // without its segment would find no existing run to rebuild,
                // create a second one over the whole outlet, and leave the
                // company holding two runs paying overlapping people.
                $run->section_id,
                $run->employment_status,
            );
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        $this->forgetRun();
        session()->flash('success', 'Payroll regenerated from current figures.');
    }

    // ── One-off adjustments ───────────────────────────────────────────────

    /**
     * Entering a correction is part of RUNNING payroll, not of approving it.
     *
     * The person processing finds the overpayment and enters it; the approver
     * sees it listed above the Approve button and signs the run off. Requiring
     * the approver at the keyboard for a RM20 fix would collapse those two
     * hands into one, which is the control this separation exists to keep.
     */
    private function assertMayAdjust(): PayrollRun
    {
        abort_unless(Auth::user()->can('hr.payroll'), 403);

        $run = $this->run();

        // Draft only. An approved run is what the company committed to paying,
        // and a paid one is money that has already moved.
        abort_unless($run->isEditable(), 403, 'This payroll run can no longer be changed.');

        return $run;
    }

    public function openAdjust(): void
    {
        $this->assertMayAdjust();
        $this->resetAdjustForm();
        $this->showAdjust = true;
    }

    public function editAdjustment(int $id): void
    {
        $run = $this->assertMayAdjust();

        $a = \App\Models\PayrollRunAdjustment::where('payroll_run_id', $run->id)->findOrFail($id);

        $this->adjustmentId          = $a->id;
        $this->adj_employee_id       = (string) $a->employee_id;
        $this->adj_label             = $a->label;
        $this->adj_amount            = (string) (float) $a->amount;
        $this->adj_direction         = $a->direction;
        $this->adj_affects_statutory = (bool) $a->affects_statutory;
        $this->adj_notes             = (string) $a->notes;
        $this->showAdjust            = true;
    }

    /**
     * Switching direction re-suggests the statutory treatment.
     *
     * A recovery is normally net-only and arrears normally are not, so the
     * field follows the direction until somebody sets it themselves — see
     * PayrollRunAdjustment::defaultAffectsStatutory().
     */
    public function updatedAdjDirection(string $value): void
    {
        $this->adj_affects_statutory =
            \App\Models\PayrollRunAdjustment::defaultAffectsStatutory($value);
    }

    public function saveAdjustment(): void
    {
        $run = $this->assertMayAdjust();

        $this->validate([
            'adj_employee_id' => 'required|integer',
            'adj_label'       => 'required|string|max:120',
            // Zero is not a correction, and a negative one would fight the
            // direction field for the sign.
            'adj_amount'      => 'required|numeric|min:0.01|max:9999999.99',
            'adj_direction'   => 'required|in:' . implode(',', array_keys(\App\Models\PayrollRunAdjustment::DIRECTIONS)),
            'adj_notes'       => 'nullable|string|max:255',
        ], [], [
            'adj_employee_id' => 'employee',
            'adj_label'       => 'description',
            'adj_amount'      => 'amount',
        ]);

        // The employee must actually be ON this run. Adjusting somebody who is
        // not would create a row that silently does nothing on rebuild, and
        // the money would appear to have been handled when it had not.
        $onRun = $run->lines()->where('employee_id', (int) $this->adj_employee_id)->exists();

        if (! $onRun) {
            $this->addError('adj_employee_id', 'That employee is not on this payroll run.');
            return;
        }

        $data = [
            'company_id'        => $run->company_id,
            'payroll_run_id'    => $run->id,
            'employee_id'       => (int) $this->adj_employee_id,
            'label'             => $this->adj_label,
            'amount'            => round((float) $this->adj_amount, 2),
            'direction'         => $this->adj_direction,
            'affects_statutory' => $this->adj_affects_statutory,
            'notes'             => $this->adj_notes ?: null,
        ];

        if ($this->adjustmentId) {
            \App\Models\PayrollRunAdjustment::where('payroll_run_id', $run->id)
                ->findOrFail($this->adjustmentId)
                ->update($data);
        } else {
            \App\Models\PayrollRunAdjustment::create($data + ['created_by' => Auth::id()]);
        }

        $this->showAdjust = false;
        $this->resetAdjustForm();

        // Rebuilt immediately, so the figures on screen are the figures the
        // adjustment produces. Leaving the run stale until somebody remembered
        // to press Regenerate is how a correction gets approved without ever
        // having been applied.
        $this->rebuildAfterAdjustment('Adjustment saved and payroll recalculated.');
    }

    public function removeAdjustment(int $id): void
    {
        $run = $this->assertMayAdjust();

        \App\Models\PayrollRunAdjustment::where('payroll_run_id', $run->id)
            ->findOrFail($id)
            ->delete();

        $this->rebuildAfterAdjustment('Adjustment removed and payroll recalculated.');
    }

    private function rebuildAfterAdjustment(string $message): void
    {
        $run = $this->run();

        try {
            app(PayrollRunBuilder::class)->generate(
                $run->company_id,
                $this->accessibleOutletIds(),
                $run->period_month,
                $run->outlet_id,
                Auth::id(),
                null,
                null,
                $run->section_id,
                $run->employment_status,
            );
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        $this->forgetRun();
        session()->flash('success', $message);
    }

    private function resetAdjustForm(): void
    {
        $this->adjustmentId          = null;
        $this->adj_employee_id       = '';
        $this->adj_label             = '';
        $this->adj_amount            = '';
        $this->adj_direction         = \App\Models\PayrollRunAdjustment::DEDUCTION;
        $this->adj_affects_statutory = \App\Models\PayrollRunAdjustment::defaultAffectsStatutory(
            \App\Models\PayrollRunAdjustment::DEDUCTION
        );
        $this->adj_notes             = '';
        $this->resetValidation();
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

        // The overtime this run pays is now spoken for and can no longer be
        // taken as time off — see PayrollRun::settleOvertime().
        $settled = $run->settleOvertime(Auth::id());

        $this->showApprove = false;
        $this->forgetRun();
        session()->flash('success', 'Payroll approved. The figures are now locked.'
            . ($settled ? " {$settled} overtime claim(s) marked paid." : ''));
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

        /*
         * Outsourced heads are outside every statutory check below.
         *
         * They appear in no submission this company files — EPF, SOCSO, EIS
         * and PCB are the agent's — so warning that their IC is missing, or
         * that the rates behind their (zero) contributions were unconfirmed,
         * is a false alarm that fires on every run, every month. A warning
         * nobody can ever clear is worse than no warning: it is what teaches
         * people to approve past the whole list without reading it.
         */
        $statutoryLines = $lines->reject(fn ($l) => $l->isOutsourced());

        $noIc = $statutoryLines->whereNull('ic_number');
        if ($noIc->isNotEmpty()) {
            $warnings[] = $noIc->count() . ' employee(s) have no IC number — statutory submissions require it.';
        }

        $unpriced = $lines->filter(fn ($l) => collect($l->statutory_notes ?? [])
            ->contains(fn ($n) => str_contains($n, 'could not be priced')));
        if ($unpriced->isNotEmpty()) {
            $warnings[] = $unpriced->count() . ' employee(s) have overtime that could not be priced: no salary on record.';
        }

        /*
         * An employee paid off the grid — by hours or by days — who came out
         * at nothing.
         *
         * Zero is a legitimate figure — somebody who did not work this period
         * is owed nothing — but it looks identical to an attendance grid that
         * nobody filled in, and only one of those is safe to approve. It is
         * named here rather than blocked because a real zero has to stay
         * payable; the run is stopped by a person, not by the software.
         *
         * Named individually. "3 employees have no hours" sends somebody
         * hunting through a list of forty; the names are the actionable part.
         */
        $zeroHours = $lines->filter(fn ($l) => $l->zeroHourReason() !== null);

        if ($zeroHours->isNotEmpty()) {
            // "hourly" no longer covers it: daily staff are priced off the same
            // grid and fail the same way when nobody has filled it in.
            $warnings[] = $zeroHours->count() . ' employee(s) paid by hours or days are being paid nothing — '
                . $zeroHours->map(fn ($l) => $l->employee_name . ' (' . $l->zeroHourReason() . ')')->join('; ')
                . '. Check the attendance record for this period, then regenerate.';
        }

        /*
         * A line that has gone NEGATIVE — the adjustments took more than the
         * month paid.
         *
         * Not blocked and not capped. Capping would silently under-recover and
         * hide the mistake; blocking would stop a genuine case where the whole
         * month is legitimately swallowed. But it is nearly always a typo —
         * 99999 for 999 — and it is the one error here that hands somebody a
         * payslip demanding money from them, so it is named before approval
         * with the amount that would have to be spread over later months.
         */
        $negative = $lines->filter(fn ($l) => (float) $l->net < 0);

        if ($negative->isNotEmpty()) {
            $warnings[] = $negative->count() . ' employee(s) have a NEGATIVE net — an adjustment is larger than the month pays: '
                . $negative->map(fn ($l) => $l->employee_name . ' (' . number_format((float) $l->net, 2) . ')')->join('; ')
                . '. Check the amount, or recover it over several months instead.';
        }

        // Nothing on this run was computed from the rates, so the caveat about
        // them describes no figure anybody is looking at.
        if (! $run->rates_were_confirmed && $statutoryLines->isNotEmpty()) {
            $warnings[] = 'Statutory rates were not confirmed when this run was generated — EPF, SOCSO, EIS and PCB are estimates.';
        }

        return view('livewire.hr.payroll-run-show', [
            'run'        => $run,
            'lines'      => $lines,
            'warnings'   => $warnings,
            'canApprove' => Auth::user()->can('hr.payroll.approve'),
            'canAdjust'  => $run->isEditable() && Auth::user()->can('hr.payroll'),
            'adjustments' => \App\Models\PayrollRunAdjustment::with('employee:id,name', 'createdBy:id,name')
                ->where('payroll_run_id', $run->id)
                ->orderBy('id')
                ->get(),
            'audience'   => $this->emailAudience(),
            'deliveries' => PayslipDelivery::where('payroll_run_id', $run->id)
                ->orderByDesc('id')
                ->get()
                ->groupBy('payroll_run_line_id'),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Payroll — ' . $run->periodLabel()]);
    }
}
