<?php

namespace App\Livewire\Marketing;

use App\Models\Plan;
use Livewire\Component;

class Pricing extends Component
{
    public string $cycle = 'monthly';

    public function render()
    {
        $plans = Plan::active()->ordered()->get();

        return view('livewire.marketing.pricing', compact('plans'))
            ->layout('layouts.marketing', ['title' => 'Pricing']);
    }
}
