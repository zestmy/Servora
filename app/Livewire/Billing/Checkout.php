<?php

namespace App\Livewire\Billing;

use App\Models\Plan;
use App\Services\ChipInService;
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

        if ($existing && $existing->isTrial()) {
            // Trialing — initiate payment to upgrade
            $this->initiatePayment($company, $existing);
            return;
        }

        if ($existing) {
            // Active subscription — change plan (payment handled on next billing cycle)
            $subscriptionService->changePlan($existing, $this->selectedPlan);
            session()->flash('success', "Switched to {$this->selectedPlan->name} plan.");
            $this->redirect(route('billing.index'), navigate: true);
            return;
        }

        // No subscription — create trial first
        $subscription = $subscriptionService->createTrial($company, $this->selectedPlan, $this->billing_cycle);
        session()->flash('success', "Started {$this->selectedPlan->trial_days}-day free trial on {$this->selectedPlan->name} plan.");
        $this->redirect(route('billing.index'), navigate: true);
    }

    public function payNow(): void
    {
        $company = Auth::user()->company;
        $subscriptionService = app(SubscriptionService::class);
        $existing = $subscriptionService->getActiveSubscription($company);

        if (!$existing) {
            // Create subscription first
            $existing = $subscriptionService->createTrial($company, $this->selectedPlan, $this->billing_cycle);
        }

        $this->initiatePayment($company, $existing);
    }

    private function initiatePayment($company, $subscription): void
    {
        $amount = $this->billing_cycle === 'yearly'
            ? (float) $this->selectedPlan->price_yearly
            : (float) $this->selectedPlan->price_monthly;

        // Check if CHIP-IN is configured
        if (!config('chipin.api_key')) {
            session()->flash('error', 'Payment gateway is not configured yet. Please contact support to activate your subscription.');
            $this->redirect(route('billing.index'), navigate: true);
            return;
        }

        $result = app(ChipInService::class)->createPurchase(
            $company,
            $subscription,
            $amount,
            $this->selectedPlan->currency,
        );

        if ($result['success'] && !empty($result['checkout_url'])) {
            // Redirect to CHIP-IN payment page
            $this->redirect($result['checkout_url']);
        } else {
            session()->flash('error', $result['message'] ?? 'Payment creation failed. Please try again.');
            $this->redirect(route('billing.index'), navigate: true);
        }
    }

    public function render()
    {
        $company = Auth::user()->company;
        $currentSubscription = $company ? app(SubscriptionService::class)->getActiveSubscription($company) : null;
        $chipInConfigured = !empty(config('chipin.api_key'));

        return view('livewire.billing.checkout', compact('currentSubscription', 'chipInConfigured'))
            ->layout('layouts.app', ['title' => 'Checkout — ' . $this->selectedPlan->name]);
    }
}
