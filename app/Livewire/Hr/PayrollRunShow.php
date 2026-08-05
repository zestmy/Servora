<?php

namespace App\Livewire\Hr;

use App\Models\PayrollRun;
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
    public int $runId;

    public bool   $showApprove = false;
    public bool   $showPaid    = false;
    public string $paymentDate = '';
    public string $notes       = '';

    public function mount(int $run): void
    {
        $this->runId = $run;

        $model = $this->run();
        $this->notes       = (string) $model->notes;
        $this->paymentDate = now()->format('Y-m-d');
    }

    /** Company-scoped by the global scope, so another tenant's id 404s. */
    public function run(): PayrollRun
    {
        return PayrollRun::with('outlet:id,name', 'generatedBy:id,name', 'approvedBy:id,name')
            ->findOrFail($this->runId);
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
        session()->flash('success', 'Payroll marked as paid.');
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
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Payroll — ' . $run->periodLabel()]);
    }
}
