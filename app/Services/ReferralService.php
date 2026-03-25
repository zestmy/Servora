<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralProgram;
use App\Models\User;
use Illuminate\Support\Str;

class ReferralService
{
    public function generateCode(User $user): ReferralCode
    {
        // Check if user already has a code
        $existing = ReferralCode::where('referrer_type', 'user')
            ->where('referrer_id', $user->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Short 6-char alphanumeric code (e.g. X4K2MN)
        do {
            $code = strtoupper(Str::random(6));
        } while (ReferralCode::where('code', $code)->exists());

        $baseUrl = config('app.domain') ? 'https://' . config('app.domain') : url('/');

        return ReferralCode::create([
            'referrer_type' => 'user',
            'referrer_id'   => $user->id,
            'code'          => $code,
            'url'           => $baseUrl . '/r/' . $code,
            'is_active'     => true,
        ]);
    }

    public function generateCodeForAffiliate(\App\Models\Affiliate $affiliate): ReferralCode
    {
        $existing = ReferralCode::where('referrer_type', 'affiliate')
            ->where('referrer_id', $affiliate->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        do {
            $code = strtoupper(Str::random(6));
        } while (ReferralCode::where('code', $code)->exists());

        $baseUrl = config('app.domain') ? 'https://' . config('app.domain') : url('/');

        return ReferralCode::create([
            'referrer_type' => 'affiliate',
            'referrer_id'   => $affiliate->id,
            'code'          => $code,
            'url'           => $baseUrl . '/r/' . $code,
            'is_active'     => true,
        ]);
    }

    public function trackClick(string $code): ?ReferralCode
    {
        $referralCode = ReferralCode::where('code', $code)->where('is_active', true)->first();

        if ($referralCode) {
            $referralCode->incrementClicks();
        }

        return $referralCode;
    }

    public function recordSignup(Company $company, string $referralCodeStr): ?Referral
    {
        $referralCode = ReferralCode::where('code', $referralCodeStr)->where('is_active', true)->first();

        if (!$referralCode) {
            return null;
        }

        // Don't allow self-referrals
        if ($referralCode->referrer_type === 'user') {
            $referrer = User::find($referralCode->referrer_id);
            if ($referrer && $referrer->company_id === $company->id) {
                return null;
            }
        }

        // Check for existing referral
        $existing = Referral::where('referral_code_id', $referralCode->id)
            ->where('referred_company_id', $company->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $referralCode->incrementSignups();

        return Referral::create([
            'referral_code_id'    => $referralCode->id,
            'referred_company_id' => $company->id,
            'status'              => Referral::STATUS_SIGNED_UP,
        ]);
    }

    public function calculateCommission(Payment $payment): ?Commission
    {
        $company = $payment->company;

        // Find referral for this company
        $referral = Referral::where('referred_company_id', $company->id)
            ->whereIn('status', [Referral::STATUS_SIGNED_UP, Referral::STATUS_CONVERTED])
            ->first();

        if (!$referral) {
            return null;
        }

        // Find applicable referral program
        $subscription = $payment->subscription;
        $program = ReferralProgram::active()
            ->where(function ($q) use ($subscription) {
                $q->where('plan_id', $subscription->plan_id)
                  ->orWhereNull('plan_id');
            })
            ->orderByRaw('plan_id IS NULL') // specific plan first
            ->first();

        if (!$program) {
            return null;
        }

        // Check max payouts
        if ($program->max_payouts) {
            $existingPayouts = Commission::where('referral_id', $referral->id)->count();
            if ($existingPayouts >= $program->max_payouts) {
                return null;
            }
        }

        // Check if recurring or first-time only
        if (!$program->is_recurring) {
            $hasExisting = Commission::where('referral_id', $referral->id)->exists();
            if ($hasExisting) {
                return null;
            }
        }

        $amount = $program->calculateCommission((float) $payment->amount);

        if ($amount <= 0) {
            return null;
        }

        // Update referral status
        $referral->update([
            'status'       => Referral::STATUS_CONVERTED,
            'converted_at' => now(),
        ]);

        $referral->referralCode->incrementConversions();

        return Commission::create([
            'referral_id' => $referral->id,
            'payment_id'  => $payment->id,
            'amount'      => $amount,
            'status'      => Commission::STATUS_PENDING,
        ]);
    }
}
