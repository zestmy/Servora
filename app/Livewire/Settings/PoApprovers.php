<?php

namespace App\Livewire\Settings;

use App\Models\Company;
use App\Models\Department;
use App\Models\Outlet;
use App\Models\PoApprover;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PoApprovers extends Component
{
    public bool $requirePoApproval = true;
    public string $orderingMode = 'direct';
    public bool $requirePrApproval = false;

    public bool $showModal = false;
    public ?int $editingOutletId = null;
    public ?int $selectedUserId = null;
    public array $selectedDepartmentIds = [];

    public function mount(): void
    {
        $company = Auth::user()->company;
        $this->requirePoApproval = $company?->require_po_approval ?? true;
        $this->orderingMode = $company?->ordering_mode ?? 'direct';
        $this->requirePrApproval = $company?->require_pr_approval ?? false;
    }

    public function updatedRequirePoApproval(): void
    {
        $company = Auth::user()->company;
        $company->update(['require_po_approval' => $this->requirePoApproval]);

        $msg = $this->requirePoApproval
            ? 'PO approval is now required.'
            : 'PO approval is no longer required — POs will be auto-approved on submission.';
        session()->flash('success', $msg);
    }

    public function updatedOrderingMode(): void
    {
        $company = Auth::user()->company;
        $company->update(['ordering_mode' => $this->orderingMode]);

        $msg = $this->orderingMode === 'cpu'
            ? 'Ordering mode set to CPU — outlets will create Purchase Requests instead of direct POs.'
            : 'Ordering mode set to Direct — outlets create POs directly to suppliers.';
        session()->flash('success', $msg);
    }

    public function updatedRequirePrApproval(): void
    {
        $company = Auth::user()->company;
        $company->update(['require_pr_approval' => $this->requirePrApproval]);

        $msg = $this->requirePrApproval
            ? 'PR approval is now required.'
            : 'PR approval is no longer required — PRs will be auto-approved on submission.';
        session()->flash('success', $msg);
    }

    public function openAssign(int $outletId): void
    {
        $this->editingOutletId = $outletId;
        $this->selectedUserId = null;
        $this->selectedDepartmentIds = [];
        $this->showModal = true;
    }

    public function assign(): void
    {
        $this->validate([
            'editingOutletId'        => 'required|exists:outlets,id',
            'selectedUserId'         => 'required|exists:users,id',
            'selectedDepartmentIds'  => 'required|array|min:1',
            'selectedDepartmentIds.*' => 'exists:departments,id',
        ], [
            'selectedDepartmentIds.required' => 'Select at least one department.',
            'selectedDepartmentIds.min'      => 'Select at least one department.',
        ]);

        $user = User::findOrFail($this->selectedUserId);

        if (! $user->hasRole(['Operations Manager', 'Branch Manager', 'Chef'])) {
            session()->flash('error', 'Only Operations Manager, Branch Manager, or Chef roles can be appointed as PO approvers.');
            return;
        }

        $companyId = Auth::user()->company_id;

        foreach ($this->selectedDepartmentIds as $deptId) {
            PoApprover::updateOrCreate(
                [
                    'outlet_id'     => $this->editingOutletId,
                    'department_id' => $deptId,
                    'user_id'       => $this->selectedUserId,
                ],
                ['company_id' => $companyId, 'assigned_by' => Auth::id()]
            );
        }

        $count = count($this->selectedDepartmentIds);
        session()->flash('success', "Appointed {$user->name} as PO approver for {$count} department(s).");
        $this->closeModal();
    }

    public function removeDept(int $id): void
    {
        PoApprover::findOrFail($id)->delete();
        session()->flash('success', 'Department assignment removed.');
    }

    public function removeUser(int $outletId, int $userId): void
    {
        PoApprover::where('outlet_id', $outletId)
            ->where('user_id', $userId)
            ->delete();
        session()->flash('success', 'Approver removed from this outlet.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editingOutletId = null;
        $this->selectedUserId = null;
        $this->selectedDepartmentIds = [];
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;

        $outlets = Outlet::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Group approvers: outlet_id → user_id → collection of records
        $rawApprovers = PoApprover::with(['user', 'outlet', 'department', 'assignedBy'])->get();
        $approversByOutlet = $rawApprovers->groupBy('outlet_id')->map(function ($group) {
            return $group->groupBy('user_id');
        });

        $eligibleUsers = User::where('company_id', $companyId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Operations Manager', 'Branch Manager', 'Chef']))
            ->orderBy('name')
            ->get();

        $departments = Department::active()->ordered()->get();

        $editingOutlet = $this->editingOutletId ? Outlet::find($this->editingOutletId) : null;

        return view('livewire.settings.po-approvers', compact('outlets', 'approversByOutlet', 'eligibleUsers', 'departments', 'editingOutlet'))
            ->layout('layouts.app', ['title' => 'PO Approvers']);
    }
}
