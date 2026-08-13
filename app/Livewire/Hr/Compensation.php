<?php

namespace App\Livewire\Hr;

use App\Models\CompensationSetting;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\SalaryRevision;
use App\Models\Section;
use App\Services\Hr\CompensationSummary;
use App\Traits\RemembersOutletFilter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Monthly compensation: what each employee is owed, and the queue of salary
 * changes waiting to be signed off.
 *
 * The whole screen sits behind hr.compensation (route middleware) — the same
 * gate that already hides salary on the Employees list and the exports, so
 * pay visibility cannot drift between them.
 */
class Compensation extends Component
{
    use RemembersOutletFilter;

    public string $month        = '';
    public string $outletFilter = '';
    public string $sectionFilter = '';
    public string $search       = '';

    // Review a pending revision
    public bool   $showReview   = false;
    public ?int   $reviewingId  = null;
    public string $reviewNote   = '';

    public function mount(): void
    {
        /*
         * Come back on the outlet AND the month this screen was left on.
         *
         * Opening someone's compensation and pressing "<" returned a list
         * reset to the user's own outlet and to the current month — the same
         * fault reported on Employees, on a screen where the month matters
         * more, because a payroll list showing a different period than the one
         * you were working through is not obviously the same list.
         *
         * The month is remembered beside the outlet rather than threaded
         * through the form, for the reason RemembersOutletFilter gives: the
         * form should not have to know it is part of somebody's place in a
         * list.
         */
        $this->month = (string) session(self::MONTH_KEY, now()->format('Y-m'));

        // "All outlets" is a real choice, so this asks whether anything was
        // STORED, not whether the value is empty — an empty string means All
        // and must not be overruled by the default below.
        if ($this->bootRememberedOutlet()) {
            return;
        }

        $activeOutletId = Auth::user()?->activeOutletId();
        if ($activeOutletId) $this->outletFilter = (string) $activeOutletId;
    }

    /** Where the month above is kept. Per screen, like the outlet. */
    private const MONTH_KEY = 'compensation.month';

    protected function accessibleOutletIds(): array
    {
        return Auth::user()->accessibleOutletIds();
    }

    /** The month being viewed, falling back to this month on a bad value. */
    public function period(): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        } catch (\Throwable $e) {
            $this->month = now()->format('Y-m');
            return now()->startOfMonth();
        }
    }

    public function previousMonth(): void
    {
        $this->month = $this->period()->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->period()->addMonthNoOverflow()->format('Y-m');
    }

    // ── Salary revision review ────────────────────────────────────────────

    public function openReview(int $id): void
    {
        $this->reviewingId = $id;
        $this->reviewNote  = '';
        $this->showReview  = true;
    }

    public function approveRevision(): void
    {
        $this->reviewRevision(SalaryRevision::APPROVED);
    }

    public function rejectRevision(): void
    {
        $this->reviewRevision(SalaryRevision::REJECTED);
    }

    private function reviewRevision(string $status): void
    {
        if (! SalaryRevision::canApprove()) {
            session()->flash('error', 'You are not authorised to approve salary changes.');
            return;
        }

        $revision = SalaryRevision::findOrFail($this->reviewingId);
        if ($revision->status !== SalaryRevision::PENDING) {
            session()->flash('error', 'That request has already been reviewed.');
            $this->showReview = false;
            return;
        }

        if ($status === SalaryRevision::REJECTED && trim($this->reviewNote) === '') {
            $this->addError('reviewNote', 'Please say why it is being rejected.');
            return;
        }

        $revision->update([
            'status'      => $status,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_note' => trim($this->reviewNote) ?: null,
        ]);

        // A raise dated today or earlier takes effect immediately; a
        // future-dated one waits for the daily sweep to reach its date.
        $applied = $status === SalaryRevision::APPROVED && $revision->applyIfDue();

        $this->showReview = false;
        session()->flash('success', match (true) {
            $status === SalaryRevision::REJECTED => 'Salary change rejected.',
            $applied                             => 'Salary change approved and applied.',
            default                              => 'Salary change approved. It takes effect on ' . $revision->effective_on->format('d M Y') . '.',
        });
    }

    public function render()
    {
        $this->rememberOutlet();
        session([self::MONTH_KEY => $this->month]);

        $user       = Auth::user();
        $companyId  = $user->company_id;
        $accessible = $this->accessibleOutletIds();

        $outlets = Outlet::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('id', $accessible)
            ->orderBy('name')
            ->get();

        $sections = Section::active()->ordered()->get();

        $query = Employee::query()->whereIn('outlet_id', $accessible ?: [0]);
        if ($this->outletFilter !== '')  $query->where('outlet_id', (int) $this->outletFilter);
        if ($this->sectionFilter !== '') $query->where('section_id', (int) $this->sectionFilter);
        if ($this->search !== '') {
            $s = '%' . $this->search . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $s)->orWhere('staff_id', 'like', $s));
        }

        $summary = app(CompensationSummary::class)->forMonth($query, $companyId, $this->period());

        $pending = SalaryRevision::pending()
            ->with(['employee:id,name,outlet_id', 'requester:id,name'])
            ->whereIn('employee_id', function ($sub) use ($accessible) {
                $sub->select('id')->from('employees')->whereIn('outlet_id', $accessible ?: [0]);
            })
            ->orderBy('effective_on')
            ->get();

        return view('livewire.hr.compensation', [
            'summary'    => $summary,
            'pending'    => $pending,
            'outlets'    => $outlets,
            'sections'   => $sections,
            'canApprove' => SalaryRevision::canApprove(),
            'settings'   => $summary['settings'],
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Compensation']);
    }
}
