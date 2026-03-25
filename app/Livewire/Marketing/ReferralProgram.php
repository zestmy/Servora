<?php

namespace App\Livewire\Marketing;

use App\Models\Plan;
use App\Models\ReferralProgram as ReferralProgramModel;
use Livewire\Component;

class ReferralProgram extends Component
{
    public function render()
    {
        $programs = ReferralProgramModel::active()->with('plan')->get();
        $plans = Plan::active()->ordered()->get();

        return view('livewire.marketing.referral-program', compact('programs', 'plans'))
            ->layout('layouts.marketing', ['title' => 'Referral Program']);
    }
}
