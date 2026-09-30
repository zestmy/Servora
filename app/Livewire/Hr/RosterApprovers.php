<?php

namespace App\Livewire\Hr;

use App\Livewire\Hr\Concerns\ScopesRosterOutlet;
use App\Models\Outlet;
use App\Models\RosterApprover;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RosterApprovers extends Component
{
    use ScopesRosterOutlet;
    public ?int $outletId = null;
    public ?int $selectedUserId = null;

    public function mount(): void
    {
        $this->outletId = Auth::user()?->activeOutletId();
    }

    public function updatedOutletId(): void
    {
        $this->normaliseRosterOutlet();
        $this->selectedUserId = null;
    }

    public function addApprover(): void
    {
        if (!$this->outletId || !$this->selectedUserId) {
            return;
        }

        $this->assertRosterOutlet($this->outletId);

        // Only someone from this company can approve its rosters.
        abort_unless(
            User::where('company_id', Auth::user()->company_id)->whereKey($this->selectedUserId)->exists(),
            404
        );

        // Check if already exists
        $exists = RosterApprover::where('outlet_id', $this->outletId)
            ->where('user_id', $this->selectedUserId)
            ->exists();

        if ($exists) {
            session()->flash('error', 'This user is already an approver for this outlet.');
            return;
        }

        RosterApprover::create([
            'outlet_id' => $this->outletId,
            'user_id' => $this->selectedUserId,
        ]);

        $this->selectedUserId = null;
        session()->flash('success', 'Approver added.');
    }

    public function removeApprover(int $id): void
    {
        RosterApprover::whereIn('outlet_id', $this->rosterOutletIds())->findOrFail($id)->delete();
        session()->flash('success', 'Approver removed.');
    }

    protected function accessibleOutlets()
    {
        return Auth::user()->accessibleOutlets()->orderBy('name')->get();
    }

    protected function getAvailableUsers()
    {
        if (!$this->outletId) {
            return collect();
        }

        $companyId = Auth::user()->company_id;

        // Get IDs of users already set as approvers for this outlet
        $existingIds = RosterApprover::where('outlet_id', $this->outletId)->pluck('user_id');

        // Return company users not already approvers
        return User::where('company_id', $companyId)
            ->whereNotIn('id', $existingIds)
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        $this->normaliseRosterOutlet();

        $approvers = collect();
        if ($this->outletId) {
            $approvers = RosterApprover::with('user')
                ->where('outlet_id', $this->outletId)
                ->get();
        }

        return view('livewire.hr.roster-approvers', [
            'outlets' => $this->accessibleOutlets(),
            'approvers' => $approvers,
            'availableUsers' => $this->getAvailableUsers(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Roster Approvers']);
    }
}
