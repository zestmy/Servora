<?php

namespace App\Livewire\Hr;

use App\Models\OvertimeClaim;
use App\Models\OvertimeClaimApprover;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class OvertimeClaims extends Component
{
    use WithPagination;

    // Filters
    public string $statusFilter = '';
    public string $dateFrom     = '';
    public string $dateTo       = '';
    public int    $perPage      = 25;

    // Form modal
    public bool   $showModal         = false;
    public ?int   $editingId         = null;
    public ?int   $employee_id       = null;
    public string $claim_date        = '';
    public string $ot_time_start     = '';
    public string $ot_time_end       = '';
    public string $total_ot_hours    = '';
    public string $ot_type           = 'normal_day';
    public string $reason            = '';

    // Reject modal
    public bool   $showRejectModal   = false;
    public ?int   $rejectingId       = null;
    public string $rejected_reason   = '';

    protected function rules(): array
    {
        return [
            'employee_id'    => 'required|exists:users,id',
            'claim_date'     => 'required|date',
            'ot_time_start'  => 'required|date_format:H:i',
            'ot_time_end'    => 'required|date_format:H:i|after:ot_time_start',
            'total_ot_hours' => 'required|numeric|min:0.25|max:24',
            'ot_type'        => 'required|in:normal_day,public_holiday,rest_day',
            'reason'         => 'required|string|max:500',
        ];
    }

    protected function messages(): array
    {
        return [
            'employee_id.required'   => 'Please select an employee.',
            'ot_time_end.after'      => 'End time must be after start time.',
            'total_ot_hours.min'     => 'Minimum OT is 0.25 hours (15 minutes).',
            'reason.required'        => 'Please provide a reason for the overtime.',
        ];
    }

    public function updatedOtTimeStart(): void { $this->calcHours(); }
    public function updatedOtTimeEnd(): void   { $this->calcHours(); }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->claim_date = now()->toDateString();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $claim = OvertimeClaim::findOrFail($id);
        if ($claim->status !== 'draft') return;

        $this->editingId      = $claim->id;
        $this->employee_id    = $claim->employee_id;
        $this->claim_date     = $claim->claim_date->toDateString();
        $this->ot_time_start  = substr($claim->ot_time_start, 0, 5);
        $this->ot_time_end    = substr($claim->ot_time_end, 0, 5);
        $this->total_ot_hours = (string) floatval($claim->total_ot_hours);
        $this->ot_type        = $claim->ot_type;
        $this->reason         = $claim->reason;
        $this->showModal      = true;
    }

    public function save(string $action = 'save'): void
    {
        $this->validate();

        $user     = Auth::user();
        $outletId = $user->activeOutletId();

        $data = [
            'company_id'    => $user->company_id,
            'outlet_id'     => $outletId,
            'submitted_by'  => $user->id,
            'employee_id'   => $this->employee_id,
            'claim_date'    => $this->claim_date,
            'ot_time_start' => $this->ot_time_start,
            'ot_time_end'   => $this->ot_time_end,
            'total_ot_hours' => floatval($this->total_ot_hours),
            'ot_type'       => $this->ot_type,
            'reason'        => $this->reason,
            'status'        => $action === 'submit' ? 'submitted' : 'draft',
        ];

        if ($this->editingId) {
            $claim = OvertimeClaim::findOrFail($this->editingId);
            $claim->update($data);
        } else {
            OvertimeClaim::create($data);
        }

        $this->showModal = false;
        $this->resetForm();
        session()->flash('success', $action === 'submit' ? 'OT claim submitted for approval.' : 'OT claim saved as draft.');
    }

    public function submitClaim(int $id): void
    {
        $claim = OvertimeClaim::findOrFail($id);
        if ($claim->status !== 'draft') return;
        $claim->update(['status' => 'submitted']);
        session()->flash('success', 'OT claim submitted for approval.');
    }

    public function approveClaim(int $id): void
    {
        $claim = OvertimeClaim::findOrFail($id);
        if ($claim->status !== 'submitted') return;

        if (! OvertimeClaimApprover::isApproverFor(Auth::id(), $claim->outlet_id) && ! Auth::user()->isSystemRole()) {
            session()->flash('error', 'You are not authorized to approve this claim.');
            return;
        }

        $claim->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        session()->flash('success', 'OT claim approved.');
    }

    public function openReject(int $id): void
    {
        $this->rejectingId    = $id;
        $this->rejected_reason = '';
        $this->showRejectModal = true;
    }

    public function rejectClaim(): void
    {
        $this->validate(['rejected_reason' => 'required|string|max:500']);

        $claim = OvertimeClaim::findOrFail($this->rejectingId);
        if ($claim->status !== 'submitted') return;

        if (! OvertimeClaimApprover::isApproverFor(Auth::id(), $claim->outlet_id) && ! Auth::user()->isSystemRole()) {
            session()->flash('error', 'You are not authorized to reject this claim.');
            return;
        }

        $claim->update([
            'status'          => 'rejected',
            'approved_by'     => Auth::id(),
            'rejected_reason' => $this->rejected_reason,
        ]);

        $this->showRejectModal = false;
        session()->flash('success', 'OT claim rejected.');
    }

    public function deleteClaim(int $id): void
    {
        $claim = OvertimeClaim::findOrFail($id);
        if (! in_array($claim->status, ['draft', 'rejected'])) return;
        $claim->delete();
        session()->flash('success', 'OT claim deleted.');
    }

    public function render()
    {
        $user     = Auth::user();
        $outletId = $user->activeOutletId();
        $isApprover = OvertimeClaimApprover::isApproverFor($user->id, $outletId) || $user->isSystemRole();

        $query = OvertimeClaim::with(['employee', 'submitter', 'approver', 'outlet'])
            ->where('outlet_id', $outletId);

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->dateFrom) {
            $query->where('claim_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('claim_date', '<=', $this->dateTo);
        }

        $claims = $query->orderByDesc('claim_date')->orderByDesc('created_at')->paginate($this->perPage);

        // Staff list for employee dropdown (users in this outlet)
        $staff = User::where('company_id', $user->company_id)
            ->whereHas('outlets', fn ($q) => $q->where('outlets.id', $outletId))
            ->orderBy('name')
            ->get();

        // Stats
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd   = now()->endOfMonth()->toDateString();
        $monthStats = OvertimeClaim::where('outlet_id', $outletId)
            ->whereBetween('claim_date', [$monthStart, $monthEnd]);

        $totalHoursMonth   = (clone $monthStats)->where('status', 'approved')->sum('total_ot_hours');
        $pendingCount      = (clone $monthStats)->where('status', 'submitted')->count();
        $approvedCount     = (clone $monthStats)->where('status', 'approved')->count();

        return view('livewire.hr.overtime-claims', compact(
            'claims', 'staff', 'isApprover', 'totalHoursMonth', 'pendingCount', 'approvedCount'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Overtime Claims']);
    }

    private function calcHours(): void
    {
        if (! $this->ot_time_start || ! $this->ot_time_end) return;
        try {
            $start = \Carbon\Carbon::createFromFormat('H:i', $this->ot_time_start);
            $end   = \Carbon\Carbon::createFromFormat('H:i', $this->ot_time_end);
            if ($end->gt($start)) {
                $this->total_ot_hours = (string) round($end->diffInMinutes($start) / 60, 2);
            }
        } catch (\Exception) {}
    }

    private function resetForm(): void
    {
        $this->editingId      = null;
        $this->employee_id    = null;
        $this->claim_date     = '';
        $this->ot_time_start  = '';
        $this->ot_time_end    = '';
        $this->total_ot_hours = '';
        $this->ot_type        = 'normal_day';
        $this->reason         = '';
    }
}
