<?php

namespace App\Livewire\Onboarding;

use App\Models\OnboardingStep;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Wizard extends Component
{
    public string $currentStep = 'company_details';

    // Company details
    public string $company_phone   = '';
    public string $company_address = '';
    public string $currency        = 'MYR';

    // First outlet
    public string $outlet_name    = '';
    public string $outlet_code    = '';
    public string $outlet_phone   = '';
    public string $outlet_address = '';

    // Invite team
    public array $invites = [];

    public function mount(): void
    {
        $company = Auth::user()->company;
        $steps = OnboardingStep::where('company_id', $company->id)->get()->keyBy('step');

        // Find first incomplete step
        foreach (OnboardingStep::STEPS as $step) {
            if (!isset($steps[$step]) || !$steps[$step]->isComplete()) {
                $this->currentStep = $step;
                break;
            }
        }

        // Pre-fill from existing data
        $this->company_phone = $company->phone ?? '';
        $this->company_address = $company->address ?? '';
        $this->currency = $company->currency ?? 'MYR';

        $outlet = $company->outlets()->first();
        if ($outlet) {
            $this->outlet_name = $outlet->name;
            $this->outlet_code = $outlet->code;
            $this->outlet_phone = $outlet->phone ?? '';
            $this->outlet_address = $outlet->address ?? '';
        }
    }

    public function saveCompanyDetails(): void
    {
        $this->validate([
            'company_phone'   => 'nullable|string|max:50',
            'company_address' => 'nullable|string|max:500',
            'currency'        => 'required|string|size:3',
        ]);

        $company = Auth::user()->company;
        $company->update([
            'phone'    => $this->company_phone ?: null,
            'address'  => $this->company_address ?: null,
            'currency' => $this->currency,
        ]);

        $this->completeStep('company_details');
        $this->currentStep = 'first_outlet';
    }

    public function saveFirstOutlet(): void
    {
        $this->validate([
            'outlet_name'    => 'required|string|max:200',
            'outlet_code'    => 'required|string|max:20',
            'outlet_phone'   => 'nullable|string|max:50',
            'outlet_address' => 'nullable|string|max:500',
        ]);

        $company = Auth::user()->company;
        $outlet = $company->outlets()->first();

        if ($outlet) {
            $outlet->update([
                'name'    => $this->outlet_name,
                'code'    => strtoupper($this->outlet_code),
                'phone'   => $this->outlet_phone ?: null,
                'address' => $this->outlet_address ?: null,
            ]);
        }

        $this->completeStep('first_outlet');
        $this->currentStep = 'invite_team';
    }

    public function addInvite(): void
    {
        $this->invites[] = ['name' => '', 'email' => '', 'role' => 'Staff'];
    }

    public function removeInvite(int $index): void
    {
        unset($this->invites[$index]);
        $this->invites = array_values($this->invites);
    }

    public function saveInviteTeam(): void
    {
        // Filter out empty invites
        $validInvites = array_filter($this->invites, fn ($i) => !empty($i['email']));

        if (!empty($validInvites)) {
            $this->validate([
                'invites.*.name'  => 'required_with:invites.*.email|string|max:200',
                'invites.*.email' => 'required|email|distinct',
                'invites.*.role'  => 'required|in:Company Admin,Outlet Manager,Staff',
            ]);

            $company = Auth::user()->company;
            $outlet = $company->outlets()->first();

            foreach ($validInvites as $invite) {
                $exists = User::where('email', $invite['email'])->exists();
                if ($exists) {
                    continue;
                }

                $user = User::create([
                    'name'       => $invite['name'],
                    'email'      => $invite['email'],
                    'password'   => Hash::make('changeme123'),
                    'company_id' => $company->id,
                ]);
                $user->assignRole($invite['role']);

                if ($outlet) {
                    $user->outlets()->attach($outlet->id);
                }
            }
        }

        $this->completeStep('invite_team');
        $this->currentStep = 'explore_features';
    }

    public function finishOnboarding(): void
    {
        $this->completeStep('explore_features');

        $company = Auth::user()->company;
        $company->update(['onboarding_completed_at' => now()]);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function skipStep(): void
    {
        $this->completeStep($this->currentStep);

        $steps = OnboardingStep::STEPS;
        $currentIndex = array_search($this->currentStep, $steps);

        if ($currentIndex !== false && isset($steps[$currentIndex + 1])) {
            $this->currentStep = $steps[$currentIndex + 1];
        } else {
            $this->finishOnboarding();
        }
    }

    private function completeStep(string $step): void
    {
        OnboardingStep::where('company_id', Auth::user()->company_id)
            ->where('step', $step)
            ->whereNull('completed_at')
            ->update(['completed_at' => now()]);
    }

    public function render()
    {
        $company = Auth::user()->company;
        $steps = OnboardingStep::where('company_id', $company->id)->get()->keyBy('step');

        $subscription = $company->activeSubscription;
        $plan = $subscription?->plan;

        return view('livewire.onboarding.wizard', compact('steps', 'plan'))
            ->layout('layouts.app', ['title' => 'Get Started']);
    }
}
