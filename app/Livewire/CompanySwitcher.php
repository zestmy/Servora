<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CompanySwitcher extends Component
{
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

    public function render()
    {
        $user = Auth::user();

        $companies = $user->companies()->orderBy('name')->get();

        return view('livewire.company-switcher', [
            'companies'       => $companies,
            'activeCompanyId' => (int) $user->company_id,
            // "Create New Company" (full page) is for admins only
            'canCreate'       => $user->isSystemRole() || $user->hasCapability('can_manage_users'),
        ]);
    }
}
