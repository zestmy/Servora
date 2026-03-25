<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showRegister()
    {
        if (Auth::guard('affiliate')->check()) {
            return redirect()->route('affiliate.dashboard');
        }

        return view('affiliate.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:affiliates,email',
            'password' => 'required|string|min:8|confirmed',
            'phone'    => 'nullable|string|max:50',
        ]);

        $affiliate = Affiliate::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'phone'    => $request->phone,
        ]);

        // Auto-generate referral code
        app(ReferralService::class)->generateCodeForAffiliate($affiliate);

        Auth::guard('affiliate')->login($affiliate);
        $request->session()->regenerate();

        return redirect()->route('affiliate.dashboard');
    }

    public function showLogin()
    {
        if (Auth::guard('affiliate')->check()) {
            return redirect()->route('affiliate.dashboard');
        }

        return view('affiliate.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::guard('affiliate')->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
        }

        $request->session()->regenerate();

        return redirect()->route('affiliate.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('affiliate')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('affiliate.login');
    }
}
