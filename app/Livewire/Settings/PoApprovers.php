<?php

namespace App\Livewire\Settings;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\PoApprover;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PoApprovers extends Component
{
    public bool $requirePoApproval = true;

    public bool $showModal = false;
    public ?int $editingOutletId = null;
    public ?int $selectedUserId = null;

    public function mount(): void
    {
        $this->requirePoApproval = Auth::user()->company?->require_po_approval ?? true;
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

    public function openAssign(int $outletId): void
    {
        $this->editingOutletId = $outletId;
        $this->selectedUserId = null;
        $this->showModal = true;
    }

    public function assign(): void
    {
        $this->validate([
            'editingOutletId' => 'required|exists:outlets,id',
            'selectedUserId'  => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($this->selectedUserId);

        // Ensure user has an eligible role
        if (! $user->hasRole(['Operations Manager', 'Branch Manager', 'Chef'])) {
            session()->flash('error', 'Only Operations Manager, Branch Manager, or Chef roles can be appointed as PO approvers.');
            return;
        }

        PoApprover::updateOrCreate(
            ['outlet_id' => $this->editingOutletId, 'user_id' => $this->selectedUserId],
            ['company_id' => Auth::user()->company_id, 'assigned_by' => Auth::id()]
        );

        session()->flash('success', "Appointed {$user->name} as PO approver.");
        $this->closeModal();
    }

    public function remove(int $id): void
    {
        PoApprover::findOrFail($id)->delete();
        session()->flash('success', 'Approver removed.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editingOutletId = null;
        $this->selectedUserId = null;
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;

        $outlets = Outlet::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $approvers = PoApprover::with(['user', 'outlet', 'assignedBy'])
            ->get()
            ->groupBy('outlet_id');

        // Eligible users: Operations Manager, Branch Manager, Chef within this company
        $eligibleUsers = User::where('company_id', $companyId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Operations Manager', 'Branch Manager', 'Chef']))
            ->orderBy('name')
            ->get();

        $editingOutlet = $this->editingOutletId ? Outlet::find($this->editingOutletId) : null;

        return view('livewire.settings.po-approvers', compact('outlets', 'approvers', 'eligibleUsers', 'editingOutlet'))
            ->layout('layouts.app', ['title' => 'PO Approvers']);
    }
}
