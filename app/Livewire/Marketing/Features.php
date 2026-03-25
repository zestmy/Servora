<?php

namespace App\Livewire\Marketing;

use App\Models\Plan;
use Livewire\Component;

class Features extends Component
{
    public function render()
    {
        $trialDays = Plan::active()->ordered()->value('trial_days') ?? 30;

        return view('livewire.marketing.features', compact('trialDays'))
            ->layout('layouts.marketing', ['title' => 'Features']);
    }
}
