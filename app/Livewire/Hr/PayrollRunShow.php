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
    public bool   $showUnlock  = false;
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

    /**
     * Narrows the employee picker, not the adjustment.
     *
     * A company-wide run is ninety names in a dropdown, and the corrections
     * that come up in practice are usually aimed at one group — the agency
     * heads, or the leavers. Same vocabulary as the Generate panel one screen
     * back, including "own staff only", so the words mean the same thing in
     * both places.
     */
    public string  $adj_employment    = '';
    public string  $adj_employee_id   = '';
    public string  $adj_label         = '';
    public string  $adj_amount        = '';
    public string  $adj_direction     = \App\Models\PayrollRunAdjustment::DEDUCTION;
    public bool    $adj_affects_statutory = false;
    public string  $adj_notes         = '';

    /*
     * THE SAME CORRECTION ACROSS THE WHOLE RUN, priced per head.
     *
     * A company shutdown or a festive day is one decision and forty different
     * amounts, because a day of somebody's salary is their salary. This takes
     * the decision once and writes an ordinary adjustment per employee — see
     * BulkDayAdjustment for why it does not invent a bulk record.
     */
    public bool   $showBulk                = false;
    public string $bulk_direction          = \App\Models\PayrollRunAdjustment::DEDUCTION;
    public string $bulk_days               = '1';
    public string $bulk_basis              = \App\Services\Payroll\BulkDayAdjustment::BASIS_WORKING;
    public bool   $bulk_include_allowances = false;
    public string $bulk_label              = '';
    public bool   $bulk_affects_statutory  = false;
    public string $bulk_notes              = '';

    /** @var array<int, int> Employee ids ticked in the list. */
    public array  $bulk_selected           = [];

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
        if ($this->cachedRun) {
            return $this->cachedRun;
        }

        $this->cachedRun = PayrollRun::with('outlet:id,name', 'section:id,name', 'generatedBy:id,name', 'approvedBy:id,name')
            ->where('uuid', $this->runUuid)
            ->firstOrFail();

        // The company scope got this far; the OUTLET is what was never asked.
        // A uuid in a link or a guess would otherwise open another branch's
        // run in full — every salary on it, and the payslip buttons with it.
        abort_unless(
            $this->cachedRun->isWithinOutlets(Auth::user()),
            403,
        );

        return $this->cachedRun;
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

        // Cleared so the person being edited is always in the picker. A filter
        // left over from a previous search could hide them, and a select whose
        // current value is not among its options renders blank — which reads
        // as the employee having been lost.
        $this->adj_employment        = '';
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
     * The lines the picker offers, narrowed by the employment filter.
     *
     * Resolved through the EMPLOYEES table rather than the lines, because a
     * line is a snapshot and does not carry an employment status — it carries
     * the pay, which is the point of it being a snapshot. That means the
     * filter reads the person's standing as it is NOW, which is the right
     * answer for "who am I looking for", even on an old run.
     *
     * Lines whose employee has since been deleted are dropped: employee_id is
     * null on those, and an adjustment needs somebody to attach to.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\PayrollRunLine>
     */
    public function adjustmentCandidates(): \Illuminate\Support\Collection
    {
        $lines = $this->run()->lines()
            ->whereNotNull('employee_id')
            ->orderBy('employee_name')
            ->get();

        if ($this->adj_employment === '' || $lines->isEmpty()) {
            return $lines;
        }

        $allowed = PayrollRun::applyEmploymentStatus(
            \App\Models\Employee::query()->whereIn('id', $lines->pluck('employee_id')),
            $this->adj_employment,
        )->pluck('id')->flip();

        return $lines->filter(fn ($l) => $allowed->has($l->employee_id))->values();
    }

    /**
     * Changing the filter drops a selection it no longer shows.
     *
     * Leaving it set would let somebody pick a name, narrow the list past it,
     * and save an adjustment against an employee who is no longer on screen —
     * the money would go somewhere they had stopped looking.
     */
    public function updatedAdjEmployment(): void
    {
        if ($this->adj_employee_id === '') {
            return;
        }

        $stillThere = $this->adjustmentCandidates()
            ->contains(fn ($l) => (string) $l->employee_id === $this->adj_employee_id);

        if (! $stillThere) {
            $this->adj_employee_id = '';
        }
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

    // ── The same adjustment across the whole run ──────────────────────────

    public function openBulk(): void
    {
        $this->assertMayAdjust();
        $this->resetBulkForm();
        // Opened with everybody ticked, because "all of them" is the case this
        // exists for; untick from there rather than build the list up.
        $this->bulk_selected = $this->bulkCandidates()->pluck('employee_id')->all();
        $this->showBulk = true;
    }

    /** @return \Illuminate\Support\Collection<int, \App\Models\PayrollRunLine> */
    public function bulkCandidates(): \Illuminate\Support\Collection
    {
        return app(\App\Services\Payroll\BulkDayAdjustment::class)
            ->candidates($this->run(), $this->bulk_direction);
    }

    /**
     * Switching direction re-selects, because the list itself changes.
     *
     * Daily and hourly staff are offered for an addition and not for a
     * deduction, so a selection made under one direction can contain people
     * the other will not accept. Left alone, the count on the button would
     * promise more than the apply could deliver.
     */
    public function updatedBulkDirection(string $value): void
    {
        $this->bulk_affects_statutory =
            \App\Models\PayrollRunAdjustment::defaultAffectsStatutory($value);

        $allowed = $this->bulkCandidates()->pluck('employee_id')->all();

        $this->bulk_selected = array_values(array_intersect($this->bulk_selected, $allowed));
    }

    public function selectAllBulk(): void
    {
        $this->bulk_selected = $this->bulkCandidates()->pluck('employee_id')->all();
    }

    public function selectNoneBulk(): void
    {
        $this->bulk_selected = [];
    }

    /**
     * What applying would do, without doing it.
     *
     * The confirmation is the whole safety of this feature: forty adjustments
     * written from one button press is not something to find out about
     * afterwards. It runs the same code path as the apply, so the figures
     * shown cannot disagree with the figures written.
     */
    public function bulkPreview(): array
    {
        if (! $this->showBulk) {
            return ['rows' => collect(), 'skipped' => collect(), 'total' => 0.0, 'divisorLabel' => ''];
        }

        return app(\App\Services\Payroll\BulkDayAdjustment::class)->preview(
            $this->run(),
            array_map('intval', $this->bulk_selected),
            (float) ($this->bulk_days ?: 0),
            $this->bulk_direction,
            $this->bulk_basis,
            $this->bulk_include_allowances,
        );
    }

    public function saveBulkAdjustment(): void
    {
        $run = $this->assertMayAdjust();

        $this->validate([
            'bulk_label'     => 'required|string|max:120',
            // Half days are real — a half-day shutdown is an ordinary case for
            // this — but zero is not an adjustment, and a number larger than a
            // month is a mistake with a decimal point in it.
            'bulk_days'      => 'required|numeric|min:0.5|max:31',
            'bulk_direction' => 'required|in:' . implode(',', array_keys(\App\Models\PayrollRunAdjustment::DIRECTIONS)),
            'bulk_basis'     => 'required|in:' . implode(',', array_keys(\App\Services\Payroll\BulkDayAdjustment::BASES)),
            'bulk_notes'     => 'nullable|string|max:180',
        ], [], [
            'bulk_label' => 'description',
            'bulk_days'  => 'number of days',
        ]);

        if ($this->bulk_selected === []) {
            $this->addError('bulk_selected', 'Select at least one employee.');
            return;
        }

        $result = app(\App\Services\Payroll\BulkDayAdjustment::class)->apply(
            $run,
            array_map('intval', $this->bulk_selected),
            (float) $this->bulk_days,
            $this->bulk_direction,
            $this->bulk_basis,
            $this->bulk_include_allowances,
            $this->bulk_label,
            $this->bulk_affects_statutory,
            Auth::id(),
            $this->bulk_notes ?: null,
        );

        if ($result['rows']->isEmpty()) {
            $this->addError('bulk_selected', 'Nothing could be applied: '
                . $result['skipped']->pluck('reason')->unique()->implode(', ') . '.');
            return;
        }

        $this->showBulk = false;
        $this->resetBulkForm();

        /*
         * SAID IN FULL, including who was missed.
         *
         * A bulk action reporting "applied to 37 employees" when 40 were
         * ticked, without naming the three, is how somebody goes un-deducted
         * for a month. The skipped names are in the message, not their count.
         */
        $message = 'Applied to ' . $result['rows']->count() . ' employee(s) — RM'
            . number_format($result['total'], 2) . ' in total. Payroll recalculated.';

        if ($result['skipped']->isNotEmpty()) {
            $message .= ' Skipped ' . $result['skipped']->count() . ': '
                . $result['skipped']->map(fn ($x) => $x['name'] . ' (' . $x['reason'] . ')')->implode('; ') . '.';
        }

        $this->rebuildAfterAdjustment($message);
    }

    private function resetBulkForm(): void
    {
        $this->bulk_direction          = \App\Models\PayrollRunAdjustment::DEDUCTION;
        $this->bulk_days               = '1';
        $this->bulk_basis              = \App\Services\Payroll\BulkDayAdjustment::BASIS_WORKING;
        $this->bulk_include_allowances = false;
        $this->bulk_label              = '';
        $this->bulk_affects_statutory  = \App\Models\PayrollRunAdjustment::defaultAffectsStatutory(
            \App\Models\PayrollRunAdjustment::DEDUCTION
        );
        $this->bulk_notes              = '';
        $this->bulk_selected           = [];
        $this->resetValidation();
    }

    private function resetAdjustForm(): void
    {
        $this->adjustmentId          = null;
        $this->adj_employment        = '';
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

    /**
     * Put an approved run back to a draft so the figures can be corrected.
     *
     * WHY THIS EXISTS AT ALL, given that approval is described everywhere else
     * as the point of no return: it is the point of no return for the FIGURES,
     * not for the paperwork. An approved run that turns out to be wrong had,
     * until now, no route back except somebody editing the database — which is
     * the worst version of this, because it leaves the overtime claims stamped
     * as paid by a run that is being rebuilt underneath them.
     *
     * It needs the APPROVER's permission, not the clerk's. The same hand that
     * committed the company to these figures is the one that may un-commit
     * them; letting whoever runs payroll quietly reopen an approved run would
     * dissolve the two-hands control that approval exists to create.
     *
     * REFUSED ONCE THE RUN IS PAID. At that point money has left the company
     * and the answer is a correction on the next run, not a rewrite of the one
     * that paid it.
     */
    public function unlock(): void
    {
        abort_unless(Auth::user()->can('hr.payroll.approve'), 403);

        $run = $this->run();

        if (! $run->isApproved()) {
            session()->flash('error', 'This run is already a draft.');
            return;
        }

        if ($run->paid_at !== null) {
            session()->flash('error', 'This run has been marked paid — the money has already moved. '
                . 'Correct it with an adjustment on the next run rather than reopening this one.');
            return;
        }

        $released = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($run, &$released) {
            // Released FIRST and inside the transaction: a run reopened with
            // its overtime still stamped is the inconsistent state this whole
            // action exists to avoid.
            $released = $run->releaseOvertime();

            $run->update([
                'status'      => PayrollRun::DRAFT,
                'approved_by' => null,
                'approved_at' => null,
            ]);
        });

        $this->showUnlock = false;
        $this->forgetRun();

        session()->flash('success', 'Payroll unlocked and returned to draft. '
            . ($released ? "{$released} overtime claim(s) released back to unpaid. " : '')
            . 'Regenerate or adjust it, then approve again.');
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

    /**
     * Overtime claimed inside this run's period that nobody has decided on.
     *
     * Scoped to the employees ON THE RUN, not the whole company: a segmented
     * run pays part of the outlet, and a claim belonging to somebody it does
     * not cover is not this run's problem to report.
     *
     * Rejected and approved claims are both settled answers and are left out —
     * approved ones are already in the figures, rejected ones are meant to be
     * absent. Only the undecided are a surprise.
     *
     * @return array{submitted: int, draft: int, names: string}
     */
    private function pendingOvertime(PayrollRun $run): array
    {
        $employeeIds = $run->lines()->whereNotNull('employee_id')->pluck('employee_id');

        if ($employeeIds->isEmpty() || ! $run->period_start || ! $run->period_end) {
            return ['submitted' => 0, 'draft' => 0, 'names' => ''];
        }

        $claims = \App\Models\OvertimeClaim::withoutGlobalScopes()
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('status', ['submitted', 'draft'])
            // The OVERTIME period: this has to describe the same window the
            // run paid from, or it reports claims the run was never going to
            // include and stays quiet about ones it was.
            ->whereBetween('claim_date', $this->overtimeWindow($run))
            ->with('employee:id,name')
            ->get();

        $submitted = $claims->where('status', 'submitted');

        return [
            'submitted' => $submitted->count(),
            'draft'     => $claims->where('status', 'draft')->count(),
            // The names are the actionable part — "12 claims" sends somebody
            // hunting through the whole approval queue.
            'names' => $submitted->map(fn ($c) => $c->employee?->name)
                ->filter()->unique()->sort()->values()->join(', '),
        ];
    }

    /**
     * The window this run pays overtime over — its own period on an ordinary
     * run, and always the same expression the run paid and settled with.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    private function overtimeWindow(PayrollRun $run): array
    {
        return $run->periodFor(\App\Services\Payroll\RunPeriods::OVERTIME);
    }

    /**
     * Approved overtime in this period that a DIFFERENT run already paid.
     *
     * The companion to the guard in CompensationSummary: that one stops the
     * hours being paid twice, this one says out loud that they were left out
     * and by whom. Same shape and same scoping as pendingOvertime() above —
     * the employees actually on this run, over this run's period.
     *
     * @return array{count: int, names: string, runs: string}
     */
    private function overtimeSettledElsewhere(PayrollRun $run): array
    {
        $employeeIds = $run->lines()->whereNotNull('employee_id')->pluck('employee_id');

        if ($employeeIds->isEmpty() || ! $run->period_start || ! $run->period_end) {
            return ['count' => 0, 'names' => '', 'runs' => ''];
        }

        $claims = \App\Models\OvertimeClaim::withoutGlobalScopes()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->where('settlement', \App\Models\OvertimeClaim::SETTLE_PAYROLL)
            ->whereNotNull('paid_at')
            // Not this run's own: those ARE on it, and naming them would
            // report a problem that does not exist.
            ->where(fn ($q) => $q->whereNull('paid_in_run_id')->orWhere('paid_in_run_id', '!=', $run->id))
            ->whereBetween('claim_date', $this->overtimeWindow($run))
            ->with('employee:id,name', 'paidInRun:id,reference')
            ->get();

        if ($claims->isEmpty()) {
            return ['count' => 0, 'names' => '', 'runs' => ''];
        }

        return [
            'count' => $claims->count(),
            'names' => $claims->map(fn ($c) => $c->employee?->name)
                ->filter()->unique()->sort()->values()->join(', '),
            // Named, because "an earlier run" is not something anybody can go
            // and look at. A claim whose run has since been deleted still says
            // something rather than printing an empty bracket.
            'runs' => $claims->map(fn ($c) => $c->paidInRun?->reference)
                ->filter()->unique()->sort()->values()->join(', ') ?: 'run since deleted',
        ];
    }

    /**
     * A service charge pool exists over these dates but not FOR them.
     *
     * Returns the warning text, or null when there is nothing to say — which
     * is every company that does not levy a service charge, and every run
     * whose pool matched.
     */
    private function serviceChargePoolMismatch(PayrollRun $run): ?string
    {
        if (! $run->period_start || ! $run->period_end) {
            return null;
        }

        // THE SERVICE CHARGE PERIOD: the pool is looked up over it, so it is
        // the one that either matches or does not. Reporting the run's own
        // period here would name the wrong dates as the problem on exactly the
        // runs this feature exists for.
        [$from, $to] = array_map(
            fn ($d) => $d->toDateString(),
            $run->periodFor(\App\Services\Payroll\RunPeriods::SERVICE_CHARGE),
        );

        // Pools that OVERLAP this run's period. The overlap is what says the
        // company levies a service charge over roughly this time; the exact
        // match below is what says this run could actually be paid from one.
        $overlapping = \App\Models\ServiceChargePeriod::withoutGlobalScopes()
            ->where('company_id', $run->company_id)
            ->whereDate('period_from', '<=', $to)
            ->whereDate('period_to', '>=', $from)
            ->with('outlet:id,name')
            ->orderBy('period_from')
            ->get();

        if ($overlapping->isEmpty()) {
            return null;
        }

        $matches = $overlapping->filter(
            fn ($p) => $p->period_from->isSameDay($from) && $p->period_to->isSameDay($to)
        );

        if ($matches->isNotEmpty()) {
            return null;
        }

        // Named individually, with their outlet: the fix is either to save a
        // pool for these dates or to run this payroll over the pool's, and
        // neither decision can be made without seeing which dates exist.
        $named = $overlapping->take(4)->map(function ($p) {
            $label = $p->period_from->format('j M') . ' – ' . $p->period_to->format('j M Y');

            return $p->outlet?->name ? $p->outlet->name . ' ' . $label : $label;
        })->join('; ');

        $covers = $run->periods()->label(\App\Services\Payroll\RunPeriods::SERVICE_CHARGE)
            ?? $run->rangeLabel();

        return 'No service charge was paid by this run. A pool is matched on its exact dates, and this run looks for one covering '
            . $covers . ' while the saved pool(s) cover ' . $named
            . ($overlapping->count() > 4 ? ' and others' : '')
            . '. Either save a pool for this run\'s dates, or generate the run over the pool\'s.';
    }

    /**
     * What one service point was worth on this run.
     *
     * RM/point is the pool divided by everybody's points, so it is the figure
     * staff actually check — "I have two points, so I should have got twice
     * what a one-point colleague did". It is snapshotted per line at
     * generation time, which is why it is read back off the lines rather than
     * recomputed from a pool that may since have been edited.
     *
     * A COMPANY-WIDE RUN SPANS SEVERAL POOLS, each with its own rate — KLCC
     * collecting more than IOI is the entire reason they are separate — so
     * there is no single answer and this returns null rather than picking one
     * outlet's rate and presenting it as the run's. A tiny spread is treated
     * as one rate: rounding a pool across a few dozen people leaves sen-level
     * differences that are noise, not two different rates.
     *
     * @param  \Illuminate\Support\Collection  $lines
     */
    private function servicePointValue($lines): ?float
    {
        $rates = $lines
            ->map(fn ($l) => (float) ($l->service_charge_detail['per_point'] ?? 0))
            ->filter(fn ($r) => $r > 0)
            ->values();

        if ($rates->isEmpty()) {
            return null;
        }

        return round($rates->max() - $rates->min(), 2) <= 0.01
            ? round($rates->first(), 2)
            : null;
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

        /*
         * OVERTIME STILL WAITING FOR A DECISION IN THIS PERIOD.
         *
         * CompensationSummary pays APPROVED claims only, which is correct — an
         * unapproved claim is not yet an amount owed. The failure is that it
         * does so SILENTLY: a claim sitting in someone's approval queue is
         * simply absent from the run, so payroll is approved, the month closes,
         * and the employee is short with nothing anywhere saying why.
         *
         * Found on live data while this was being written: the approved July
         * run had twelve submitted claims and one draft inside its period, none
         * of them paid and none of them mentioned.
         *
         * Named before approval because that is the moment it is still free to
         * fix — approve the claims, regenerate, and they are in. Afterwards
         * they have to be carried to next month as an adjustment.
         *
         * Submitted and draft are counted separately because they need
         * different people: a submitted claim is waiting for an APPROVER, while
         * a draft is still with the employee and nobody else can move it.
         */
        $pendingOt = $this->pendingOvertime($run);

        if ($pendingOt['submitted'] > 0) {
            $warnings[] = $pendingOt['submitted'] . ' overtime claim(s) in this period are still awaiting approval and are NOT in this run: '
                . $pendingOt['names']
                . '. Approve them and regenerate, or they will have to be paid next month.';
        }

        if ($pendingOt['draft'] > 0) {
            $warnings[] = $pendingOt['draft'] . ' overtime claim(s) in this period are still a draft with the employee, '
                . 'so they cannot be approved or paid in this run.';
        }

        /*
         * HOURS THIS RUN DID NOT PAY BECAUSE ANOTHER RUN ALREADY DID.
         *
         * CompensationSummary now leaves them out, which is right — they were
         * paid once. Silently leaving them out is not: an employee whose
         * overtime is missing from a payslip asks why, and "another run paid
         * it" is an answer somebody has to be able to give without going
         * through the claims table by hand.
         */
        $settledElsewhere = $this->overtimeSettledElsewhere($run);

        if ($settledElsewhere['count'] > 0) {
            $warnings[] = $settledElsewhere['count'] . ' approved overtime claim(s) in this period were NOT paid by this run '
                . 'because an earlier run already paid them (' . $settledElsewhere['runs'] . '): '
                . $settledElsewhere['names'] . '. This is not an error — it is what stops the same hours being paid twice.';
        }

        /*
         * A SERVICE CHARGE POOL THAT EXISTS BUT DOES NOT FIT.
         *
         * A pool is matched on BOTH its exact dates, so a company that
         * distributes by calendar month while running payroll 26th–25th gets
         * no match at all — and forRun() returns null rather than an error, so
         * the run pays RM0.00 of service charge and says nothing. On an F&B
         * payroll that is the largest line after basic.
         *
         * Only fires where the company actually levies one: the test is that
         * a pool overlapping this period EXISTS and none matches it exactly.
         * A company that has never saved a pool never sees this, which is what
         * keeps it from becoming a warning nobody can clear.
         */
        $poolMismatch = $this->serviceChargePoolMismatch($run);

        if ($poolMismatch !== null) {
            $warnings[] = $poolMismatch;
        }

        // Nothing on this run was computed from the rates, so the caveat about
        // them describes no figure anybody is looking at.
        if (! $run->rates_were_confirmed && $statutoryLines->isNotEmpty()) {
            $warnings[] = 'Statutory rates were not confirmed when this run was generated — EPF, SOCSO, EIS and PCB are estimates.';
        }

        return view('livewire.hr.payroll-run-show', [
            'run'        => $run,
            'lines'      => $lines,
            // Whether this run charged SKBBK at all, which decides its column.
            // Read off the LINES rather than the company setting: the run's
            // figures are frozen at generation, so a scheme switched on last
            // week must not grow a column of dashes on a run that predates it.
            'hasSkbbk'   => (float) $lines->sum('skbbk') > 0,
            'warnings'   => $warnings,
            // How many rows each listing would hold, so a button with nothing
            // behind it says so instead of failing when pressed. Only worth
            // the work once the run is approved, which is when they appear.
            'exportCounts' => $run->isApproved()
                ? app(\App\Services\Payroll\PayrollExports::class)->rowCounts($run)
                : [],
            'perPoint' => $this->servicePointValue($lines),
            'canApprove' => Auth::user()->can('hr.payroll.approve'),
            'canAdjust'  => $run->isEditable() && Auth::user()->can('hr.payroll'),
            'adjustments' => \App\Models\PayrollRunAdjustment::with('employee:id,name', 'createdBy:id,name')
                ->where('payroll_run_id', $run->id)
                ->orderBy('id')
                ->get(),
            // Only computed while the form is open — it is an extra query for
            // a picker nobody is looking at otherwise.
            'adjustCandidates' => $this->showAdjust ? $this->adjustmentCandidates() : collect(),
            // Only while their panel is open — an extra query each otherwise.
            'bulkCandidates' => $this->showBulk ? $this->bulkCandidates() : collect(),
            'bulkPreview'    => $this->bulkPreview(),
            'bulkBases'      => \App\Services\Payroll\BulkDayAdjustment::BASES,
            'bulkDivisor'    => $this->showBulk
                ? app(\App\Services\Payroll\BulkDayAdjustment::class)->divisor($run, $this->bulk_basis)
                : [0, ''],
            'employmentSegments' => PayrollRun::employmentSegments(),
            'audience'   => $this->emailAudience(),
            'deliveries' => PayslipDelivery::where('payroll_run_id', $run->id)
                ->orderByDesc('id')
                ->get()
                ->groupBy('payroll_run_line_id'),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Payroll — ' . $run->periodLabel()]);
    }
}
