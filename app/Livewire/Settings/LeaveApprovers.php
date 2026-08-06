<?php

namespace App\Livewire\Settings;

use App\Models\LeaveApprover;
use App\Models\Outlet;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Who may approve leave, and who gets told about it.
 *
 * The SECOND of two mechanisms — the first is each employee's own superior on
 * their record, which is what most requests route by. This is the fallback for
 * anyone without a reporting line, and the answer for companies that approve
 * centrally instead of up a chain. Both are notified when both apply.
 *
 * Shaped like OT Approvers on purpose: same columns, same idea, nothing new
 * to learn.
 */
class LeaveApprovers extends Component
{
    public ?int $user_id    = null;
    public ?int $outlet_id  = null;
    public ?int $section_id = null;

    public function add(): void
    {
        $this->validate([
            'user_id'    => 'required|exists:users,id',
            'outlet_id'  => 'nullable|exists:outlets,id',
            'section_id' => 'nullable|exists:sections,id',
        ], [], ['user_id' => 'user', 'outlet_id' => 'outlet', 'section_id' => 'section']);

        $exists = LeaveApprover::where('user_id', $this->user_id)
            ->where('outlet_id', $this->outlet_id)
            ->where('section_id', $this->section_id)
            ->exists();

        if ($exists) {
            session()->flash('error', 'That user already approves leave for this outlet and section.');
            return;
        }

        LeaveApprover::create([
            'company_id' => Auth::user()->company_id,
            'user_id'    => $this->user_id,
            'outlet_id'  => $this->outlet_id,
            'section_id' => $this->section_id,
        ]);

        $this->reset(['user_id', 'outlet_id', 'section_id']);
        session()->flash('success', 'Leave approver added.');
    }

    public function remove(int $id): void
    {
        LeaveApprover::findOrFail($id)->delete();
        session()->flash('success', 'Leave approver removed.');
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;

        return view('livewire.settings.leave-approvers', [
            'approvers' => LeaveApprover::with(['user:id,name,email', 'outlet:id,name', 'section:id,name'])
                ->orderBy('outlet_id')
                ->get(),
            'users'    => User::where('company_id', $companyId)->orderBy('name')->get(),
            'outlets'  => Outlet::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'sections' => Section::active()->ordered()->get(),
            // Staff with nobody to route to are the reason this screen exists,
            // so they are named rather than left to be discovered.
            'unrouted' => \App\Models\Employee::where('is_active', true)
                ->whereNull('reports_to_id')
                ->orderBy('name')
                ->get(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Leave Approvers']);
    }
}
