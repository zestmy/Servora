<?php

namespace App\Livewire\Admin\Referrals;

use App\Models\Plan;
use App\Models\ReferralProgram;
use Livewire\Component;

class Programs extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public ?int   $plan_id          = null;
    public string $commission_type  = 'percentage';
    public string $commission_value = '10';
    public bool   $is_recurring     = false;
    public string $max_payouts      = '';
    public bool   $is_active        = true;

    protected function rules(): array
    {
        return [
            'plan_id'          => 'nullable|exists:plans,id',
            'commission_type'  => 'required|in:percentage,flat',
            'commission_value' => 'required|numeric|min:0.01',
            'max_payouts'      => 'nullable|integer|min:1',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $program = ReferralProgram::findOrFail($id);
        $this->editingId        = $program->id;
        $this->plan_id          = $program->plan_id;
        $this->commission_type  = $program->commission_type;
        $this->commission_value = (string) $program->commission_value;
        $this->is_recurring     = $program->is_recurring;
        $this->max_payouts      = (string) ($program->max_payouts ?? '');
        $this->is_active        = $program->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'plan_id'          => $this->plan_id ?: null,
            'commission_type'  => $this->commission_type,
            'commission_value' => $this->commission_value,
            'is_recurring'     => $this->is_recurring,
            'max_payouts'      => $this->max_payouts !== '' ? (int) $this->max_payouts : null,
            'is_active'        => $this->is_active,
        ];

        if ($this->editingId) {
            ReferralProgram::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Referral program updated.');
        } else {
            ReferralProgram::create($data);
            session()->flash('success', 'Referral program created.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        ReferralProgram::findOrFail($id)->delete();
        session()->flash('success', 'Referral program deleted.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->plan_id = null;
        $this->commission_type = 'percentage';
        $this->commission_value = '10';
        $this->is_recurring = false;
        $this->max_payouts = '';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        $programs = ReferralProgram::with('plan')->latest()->get();
        $plans = Plan::active()->ordered()->get();

        return view('livewire.admin.referrals.programs', compact('programs', 'plans'))
            ->layout('layouts.app', ['title' => 'Referral Programs']);
    }
}
