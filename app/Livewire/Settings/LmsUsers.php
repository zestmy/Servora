<?php

namespace App\Livewire\Settings;

use App\Models\LmsUser;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class LmsUsers extends Component
{
    use WithPagination;

    public string $statusFilter = 'pending';
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function approve(int $id): void
    {
        $user = LmsUser::where('company_id', Auth::user()->company_id)->findOrFail($id);
        $user->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        session()->flash('success', "{$user->name} has been approved.");
    }

    public function reject(int $id): void
    {
        $user = LmsUser::where('company_id', Auth::user()->company_id)->findOrFail($id);
        $user->update(['status' => 'rejected']);
        session()->flash('success', "{$user->name} has been rejected.");
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;

        $users = LmsUser::where('company_id', $companyId)
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('name', 'like', "%{$this->search}%")
                   ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->with(['outlet', 'approver'])
            ->latest()
            ->paginate(20);

        return view('livewire.settings.lms-users', compact('users'))
            ->layout('layouts.app', ['title' => 'LMS Users']);
    }
}
