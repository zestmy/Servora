<?php

namespace App\Livewire\Hr;

use App\Models\Outlet;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollRunBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Payroll runs: the list, and generating a new one.
 *
 * Compensation is where a month is REVIEWED — it recomputes every time it is
 * opened, which is what you want while allowances and claims are still moving.
 * A run is where a month is COMMITTED: the figures are snapshotted so a
 * payslip, a bank file and a statutory submission all describe the same
 * payroll, and still will next year.
 */
class Payroll extends Component
{
    use WithPagination;

    public string $newMonth  = '';
    public string $newOutlet = '';
    public bool   $showNew   = false;

    public function mount(): void
    {
        // Default to LAST month: payroll for August is run at the end of
        // August or in early September, so this month is rarely the answer
        // on the day someone opens this screen.
        $this->newMonth = now()->subMonthNoOverflow()->format('Y-m');
    }

    protected function accessibleOutletIds(): array
    {
        return Auth::user()->accessibleOutletIds();
    }

    public function openNew(): void
    {
        $this->showNew = true;
        $this->resetErrorBag();
    }

    public function generate(): void
    {
        $this->validate([
            'newMonth'  => 'required|date_format:Y-m',
            'newOutlet' => 'nullable|integer',
        ], [], ['newMonth' => 'month']);

        $user     = Auth::user();
        $outletId = $this->newOutlet !== '' ? (int) $this->newOutlet : null;

        // The outlet arrives from a select, so it is re-checked here rather
        // than trusted — a run scoped to an outlet the user cannot see would
        // expose pay for the whole of it.
        if ($outletId !== null && ! in_array($outletId, $this->accessibleOutletIds(), true)) {
            $this->addError('newOutlet', 'You do not have access to that outlet.');
            return;
        }

        try {
            $run = app(PayrollRunBuilder::class)->generate(
                $user->company_id,
                $this->accessibleOutletIds(),
                Carbon::createFromFormat('Y-m', $this->newMonth)->startOfMonth(),
                $outletId,
                $user->id,
            );
        } catch (\RuntimeException $e) {
            $this->addError('newMonth', $e->getMessage());
            return;
        }

        $this->showNew = false;
        session()->flash('success', "Payroll generated for {$run->periodLabel()}: {$run->employee_count} employee(s).");

        $this->redirectRoute('hr.payroll.show', ['run' => $run->uuid], navigate: true);
    }

    public function deleteRun(int $id): void
    {
        $run = PayrollRun::findOrFail($id);

        // Only a draft. An approved run is a record of a decision, and a paid
        // one is a record of money that moved.
        if (! $run->isEditable()) {
            session()->flash('error', 'An approved payroll run cannot be deleted.');
            return;
        }

        $run->delete();
        session()->flash('success', 'Draft payroll run deleted.');
    }

    /**
     * The dates the chosen month will actually cover, resolved live so the
     * range is visible BEFORE generating rather than discovered after.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function newMonthRange(): ?array
    {
        try {
            $month = Carbon::createFromFormat('Y-m', $this->newMonth)->startOfMonth();
        } catch (\Throwable $e) {
            return null;
        }

        return \App\Models\CompensationSetting::forCompany(Auth::user()->company_id)->cycleFor($month);
    }

    public function render()
    {
        $user = Auth::user();

        $runs = PayrollRun::with('outlet:id,name', 'approvedBy:id,name')
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->paginate(15);

        $outlets = Outlet::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->whereIn('id', $this->accessibleOutletIds())
            ->orderBy('name')
            ->get();

        return view('livewire.hr.payroll', [
            'runs'     => $runs,
            'outlets'  => $outlets,
            'newRange' => $this->newMonthRange(),
            'settings' => \App\Models\CompensationSetting::forCompany($user->company_id),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Payroll']);
    }
}
