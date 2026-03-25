<?php

namespace App\Livewire\Billing;

use App\Models\Referral;
use App\Models\ReferralCode;
use App\Services\ReferralService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ReferralDashboard extends Component
{
    public bool $copiedLink = false;

    public function generateReferralCode(): void
    {
        app(ReferralService::class)->generateCode(Auth::user());
        session()->flash('referral_success', 'Referral link generated!');
    }

    public function markCopied(): void
    {
        $this->copiedLink = true;
    }

    public function render()
    {
        $user = Auth::user();

        $referralCode = ReferralCode::where('referrer_type', 'user')
            ->where('referrer_id', $user->id)
            ->first();

        $referralStats = null;
        if ($referralCode) {
            $referrals = Referral::where('referral_code_id', $referralCode->id)
                ->with('referredCompany')
                ->latest()
                ->get();

            $totalEarned = \App\Models\Commission::whereHas('referral', fn ($q) => $q->where('referral_code_id', $referralCode->id))
                ->where('status', '!=', 'rejected')
                ->sum('amount');

            $referralStats = [
                'clicks'      => $referralCode->total_clicks,
                'signups'     => $referralCode->total_signups,
                'conversions' => $referralCode->total_conversions,
                'earned'      => $totalEarned,
                'referrals'   => $referrals,
            ];
        }

        return view('livewire.billing.referral-dashboard', compact('referralCode', 'referralStats'))
            ->layout('layouts.app', ['title' => 'Refer & Earn']);
    }
}
