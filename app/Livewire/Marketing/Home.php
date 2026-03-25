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

        return view('livewire.marketing.home', compact('trialDays'))
            ->layout('layouts.marketing', ['title' => 'F&B Management Platform']);
    }
}
