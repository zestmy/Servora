<?php

namespace App\Livewire\Hr;

use App\Models\Outlet;
use App\Models\RosterEmailRecipient;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RosterEmailRecipients extends Component
{
    public ?int $outletId = null;

    // Form
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $f_email = '';
    public string $f_name = '';
    public string $f_role_label = '';
    public bool $f_is_active = true;

    protected function rules(): array
    {
        return [
            'f_email' => 'required|email|max:255',
            'f_name' => 'nullable|string|max:255',
            'f_role_label' => 'nullable|string|max:100',
            'f_is_active' => 'boolean',
        ];
    }

    public function mount(): void
    {
        $this->outletId = Auth::user()?->activeOutletId();
    }

    public function updatedOutletId(): void
    {
        $this->closeForm();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $recipient = RosterEmailRecipient::findOrFail($id);
        $this->editingId = $recipient->id;
        $this->f_email = $recipient->email;
        $this->f_name = $recipient->name ?? '';
        $this->f_role_label = $recipient->role_label ?? '';
        $this->f_is_active = $recipient->is_active;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->f_email = '';
        $this->f_name = '';
        $this->f_role_label = '';
        $this->f_is_active = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId) {
            $recipient = RosterEmailRecipient::findOrFail($this->editingId);
            $recipient->update([
                'email' => $this->f_email,
                'name' => $this->f_name ?: null,
                'role_label' => $this->f_role_label ?: null,
                'is_active' => $this->f_is_active,
            ]);
            session()->flash('success', 'Recipient updated.');
        } else {
            RosterEmailRecipient::create([
                'outlet_id' => $this->outletId,
                'email' => $this->f_email,
                'name' => $this->f_name ?: null,
                'role_label' => $this->f_role_label ?: null,
                'is_active' => $this->f_is_active,
            ]);
            session()->flash('success', 'Recipient added.');
        }

        $this->closeForm();
    }

    public function toggleActive(int $id): void
    {
        $recipient = RosterEmailRecipient::findOrFail($id);
        $recipient->update(['is_active' => !$recipient->is_active]);
    }

    public function delete(int $id): void
    {
        RosterEmailRecipient::findOrFail($id)->delete();
        session()->flash('success', 'Recipient deleted.');
    }

    protected function accessibleOutlets()
    {
        $user = Auth::user();
        if ($user->canViewAllOutlets()) {
            return Outlet::where('company_id', $user->company_id)->orderBy('name')->get();
        }
        return $user->outlets()->orderBy('name')->get();
    }

    public function render()
    {
        $recipients = collect();
        if ($this->outletId) {
            $recipients = RosterEmailRecipient::where('outlet_id', $this->outletId)
                ->orderBy('role_label')
                ->orderBy('name')
                ->get();
        }

        return view('livewire.hr.roster-email-recipients', [
            'outlets' => $this->accessibleOutlets(),
            'recipients' => $recipients,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Roster Email Recipients']);
    }
}
