<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Referral;
use App\Models\ReferralCode;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $affiliate = Auth::guard('affiliate')->user();

        $referralCode = ReferralCode::where('referrer_type', 'affiliate')
            ->where('referrer_id', $affiliate->id)
            ->first();

        $referralStats = null;
        $referrals = collect();
        $commissions = collect();

        if ($referralCode) {
            $referrals = Referral::where('referral_code_id', $referralCode->id)
                ->with('referredCompany')
                ->latest()
                ->get();

            $commissions = Commission::whereHas('referral', fn ($q) => $q->where('referral_code_id', $referralCode->id))
                ->with('referral.referredCompany')
                ->latest()
                ->get();

            $referralStats = [
                'clicks'      => $referralCode->total_clicks,
                'signups'     => $referralCode->total_signups,
                'conversions' => $referralCode->total_conversions,
                'pending'     => $commissions->where('status', 'pending')->sum('amount') + $commissions->where('status', 'approved')->sum('amount'),
                'paid'        => $commissions->where('status', 'paid')->sum('amount'),
            ];
        }

        return view('affiliate.dashboard', compact('affiliate', 'referralCode', 'referralStats', 'referrals', 'commissions'));
    }
}
