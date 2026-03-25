<?php

namespace App\Http\Controllers;

use App\Services\ReferralService;
use Illuminate\Http\Request;

class ReferralTrackingController extends Controller
{
    public function __invoke(Request $request, string $code)
    {
        $referralCode = app(ReferralService::class)->trackClick($code);

        if (!$referralCode) {
            return redirect()->route('saas.register');
        }

        // Set referral cookie (30 days)
        return redirect()->route('saas.register')
            ->withCookie(cookie('referral_code', $code, 60 * 24 * 30));
    }
}
