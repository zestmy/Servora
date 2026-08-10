<?php

namespace App\Livewire\Marketing;

use App\Models\Plan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Home extends Component
{
    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function render()
    {
        $trialDays = Plan::active()->ordered()->value('trial_days') ?? 30;

        // Cached for an hour inside the service: a marketing page is the most
        // requested URL in the product and this is the only query on it that
        // touches every company's price history.
        $tickerItems = app(\App\Services\Marketing\MarketPriceTicker::class)->items();

        return view('livewire.marketing.home', compact('trialDays', 'tickerItems'))
            ->layout('layouts.marketing', ['title' => 'F&B Management Platform']);
    }
}
