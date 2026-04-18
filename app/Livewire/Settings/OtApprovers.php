<?php

namespace App\Livewire\Settings;

use App\Models\Outlet;
use App\Models\OvertimeClaimApprover;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OtApprovers extends Component
{
    public ?int $user_id    = null;
    public ?int $outlet_id  = null;
    public ?int $section_id = null;

    protected function rules(): array
    {
        return [
            'user_id'    => 'required|exists:users,id',
            'outlet_id'  => 'nullable|exists:outlets,id',
            'section_id' => 'nullable|exists:sections,id',
        ];
    }

    public function addApprover(): void
    {
        $this->validate();

        $exists = OvertimeClaimApprover::where('user_id', $this->user_id)
            ->where('outlet_id', $this->outlet_id)
            ->where('section_id', $this->section_id)
            ->exists();

        if ($exists) {
            session()->flash('error', 'This user is already an approver for that outlet + section scope.');
            return;
        }

        OvertimeClaimApprover::create([
            'company_id' => Auth::user()->company_id,
            'user_id'    => $this->user_id,
            'outlet_id'  => $this->outlet_id,
            'section_id' => $this->section_id,
        ]);

        $this->user_id    = null;
        $this->outlet_id  = null;
        $this->section_id = null;
        session()->flash('success', 'OT approver added.');
    }

    public function removeApprover(int $id): void
    {
        OvertimeClaimApprover::findOrFail($id)->delete();
        session()->flash('success', 'OT approver removed.');
    }

    public function render()
    {
        $approvers = OvertimeClaimApprover::with(['user', 'outlet', 'section'])
            ->orderBy('created_at', 'desc')
            ->get();

        $users = User::where('company_id', Auth::user()->company_id)
            ->orderBy('name')
            ->get();

        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $sections = Section::active()->ordered()->get();

        return view('livewire.settings.ot-approvers', compact('approvers', 'users', 'outlets', 'sections'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'OT Approvers']);
    }
}
