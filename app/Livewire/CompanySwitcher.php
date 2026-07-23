<?php

namespace App\Livewire;

use App\Models\Plan;
use App\Services\CompanyRegistrationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CompanySwitcher extends Component
{
    // Create-new-company modal
    public bool    $showCreateModal = false;
    public string  $new_company_name = '';
    public ?int    $new_plan_id = null;
    public string  $new_billing_cycle = 'monthly';

    public function switchCompany(int $companyId): void
    {
        $user = Auth::user();

        if (! $user->switchToCompany($companyId)) {
            return;
        }

        // Workspace state belongs to the previous company — reset it.
        session()->forget(['workspace_mode', 'active_kitchen_id']);

        $this->redirect(route('dashboard'));
    }

    public function openCreate(): void
    {
        if (! $this->canCreateCompany()) {
            return;
        }

        $this->new_company_name  = '';
        $this->new_billing_cycle = 'monthly';
        $this->new_plan_id       = Plan::active()->ordered()->value('id');
        $this->resetValidation();
        $this->showCreateModal   = true;
    }

    public function closeCreate(): void
    {
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    public function createCompany(): void
    {
        abort_unless($this->canCreateCompany(), 403);

        $this->validate([
            'new_company_name'  => 'required|string|max:200',
            'new_plan_id'       => 'required|exists:plans,id',
            'new_billing_cycle' => 'required|in:monthly,yearly',
        ], [], [
            'new_company_name' => 'company name',
            'new_plan_id'      => 'plan',
        ]);

        $user = Auth::user();

        $result = app(CompanyRegistrationService::class)->registerAdditionalCompany($user, [
            'company_name'  => $this->new_company_name,
            'plan_id'       => $this->new_plan_id,
            'billing_cycle' => $this->new_billing_cycle,
        ]);

        // Move into the new company and start its onboarding
        $user->switchToCompany($result['company']->id);
        session()->forget(['workspace_mode', 'active_kitchen_id']);
        session()->flash('success', 'Company "' . $result['company']->name . '" created — your free trial has started.');

        $this->redirect(route('onboarding'));
    }

    /** Company creation is for admins (Manage Users capability) only. */
    private function canCreateCompany(): bool
    {
        $user = Auth::user();

        return $user && ($user->isSystemRole() || $user->hasCapability('can_manage_users'));
    }

    public function render()
    {
        $user = Auth::user();

        $companies = $user->companies()->orderBy('name')->get();

        return view('livewire.company-switcher', [
            'companies'       => $companies,
            'activeCompanyId' => (int) $user->company_id,
            'canCreate'       => $this->canCreateCompany(),
            'plans'           => $this->showCreateModal ? Plan::active()->ordered()->get() : collect(),
        ]);
    }
}
