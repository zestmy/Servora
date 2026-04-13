<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class CouponService
{
    /**
     * Validate a coupon code and return the Coupon if redeemable for this company,
     * or throw with a specific reason.
     */
    public function validate(string $code, Company $company): Coupon
    {
        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon) {
            throw new \RuntimeException('Invalid coupon code.');
        }
        if (! $coupon->is_active) {
            throw new \RuntimeException('This coupon is no longer active.');
        }
        if ($coupon->isExpired()) {
            throw new \RuntimeException('This coupon has expired.');
        }
        if ($coupon->isExhausted()) {
            throw new \RuntimeException('This coupon has reached its redemption limit.');
        }
        if (CouponRedemption::where('coupon_id', $coupon->id)->where('company_id', $company->id)->exists()) {
            throw new \RuntimeException('This coupon has already been redeemed by your company.');
        }

        return $coupon;
    }

    /**
     * Redeem a coupon for the given company, updating or creating subscription.
     * Returns the Subscription that was granted/extended.
     */
    public function redeem(Coupon $coupon, Company $company, ?int $userId = null): Subscription
    {
        return DB::transaction(function () use ($coupon, $company, $userId) {
            // Lock coupon row to prevent race on redeemed_count
            $coupon = Coupon::whereKey($coupon->id)->lockForUpdate()->first();

            if (! $coupon->isRedeemable()) {
                throw new \RuntimeException('This coupon is no longer redeemable.');
            }
            if (CouponRedemption::where('coupon_id', $coupon->id)->where('company_id', $company->id)->exists()) {
                throw new \RuntimeException('This coupon has already been redeemed by your company.');
            }

            $plan = $coupon->plan_id ? Plan::find($coupon->plan_id) : null;

            // Get or create subscription
            $subscription = Subscription::where('company_id', $company->id)
                ->orderBy('created_at', 'desc')->first();

            // Compute new period end based on grant
            $start = now();
            $end = match ($coupon->grant_type) {
                'lifetime' => now()->addYears(100),
                'months'   => now()->addMonths($coupon->grant_value ?: 1),
                'days'     => now()->addDays($coupon->grant_value ?: 1),
            };

            if ($subscription) {
                // Extend existing subscription: if already active, add time onto current period end
                if ($subscription->isActive() && $subscription->current_period_end && $subscription->current_period_end->isFuture()) {
                    $baseEnd = $subscription->current_period_end;
                    $end = match ($coupon->grant_type) {
                        'lifetime' => $baseEnd->copy()->addYears(100),
                        'months'   => $baseEnd->copy()->addMonths($coupon->grant_value ?: 1),
                        'days'     => $baseEnd->copy()->addDays($coupon->grant_value ?: 1),
                    };
                }

                $update = [
                    'status'               => Subscription::STATUS_ACTIVE,
                    'trial_ends_at'        => null,
                    'current_period_start' => $subscription->current_period_start ?: $start,
                    'current_period_end'   => $end,
                    'cancelled_at'         => null,
                ];
                if ($plan) $update['plan_id'] = $plan->id;
                $subscription->update($update);
            } else {
                $subscription = Subscription::create([
                    'company_id'           => $company->id,
                    'plan_id'              => $plan?->id,
                    'status'               => Subscription::STATUS_ACTIVE,
                    'billing_cycle'        => 'monthly',
                    'trial_ends_at'        => null,
                    'current_period_start' => $start,
                    'current_period_end'   => $end,
                ]);
            }

            // Clear company trial
            $company->update(['trial_ends_at' => null]);

            // Record redemption
            CouponRedemption::create([
                'coupon_id'       => $coupon->id,
                'company_id'      => $company->id,
                'user_id'         => $userId,
                'subscription_id' => $subscription->id,
                'redeemed_at'     => now(),
            ]);

            $coupon->increment('redeemed_count');

            return $subscription->fresh();
        });
    }
}
