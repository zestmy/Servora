<?php

namespace App\Livewire\Billing;

use App\Models\Plan;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Checkout extends Component
{
    public ?Plan $selectedPlan = null;
    public string $billing_cycle = 'monthly';

    public function mount(string $planSlug): void
    {
        $this->selectedPlan = Plan::where('slug', $planSlug)->active()->firstOrFail();
    }

    public function subscribe(): void
    {
        $company = Auth::user()->company;
        $subscriptionService = app(SubscriptionService::class);

        $existing = $subscriptionService->getActiveSubscription($company);

        if ($existing) {
            // Change plan
            $subscriptionService->changePlan($existing, $this->selectedPlan);
            session()->flash('success', "Switched to {$this->selectedPlan->name} plan.");
        } else {
            // Create new trial (or activate directly when CHIP-IN is integrated)
            $subscriptionService->createTrial($company, $this->selectedPlan, $this->billing_cycle);
            session()->flash('success', "Subscribed to {$this->selectedPlan->name} plan.");
        }

        $this->redirect(route('billing.index'), navigate: true);
    }

    public function render()
    {
        $company = Auth::user()->company;
        $currentSubscription = app(SubscriptionService::class)->getActiveSubscription($company);

        return view('livewire.billing.checkout', compact('currentSubscription'))
            ->layout('layouts.app', ['title' => 'Checkout — ' . $this->selectedPlan->name]);
    }
}
