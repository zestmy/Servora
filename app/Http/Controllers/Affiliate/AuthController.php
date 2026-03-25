<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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

    public function showForgotPassword()
    {
        return view('affiliate.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $affiliate = Affiliate::where('email', $request->email)->first();

        if ($affiliate) {
            $token = Str::random(64);
            DB::table('affiliate_password_resets')->updateOrInsert(
                ['email' => $request->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $resetUrl = route('affiliate.reset-password', ['token' => $token, 'email' => $request->email]);

            Mail::raw("Reset your Servora affiliate password:\n\n{$resetUrl}\n\nThis link expires in 60 minutes.", function ($msg) use ($request) {
                $msg->to($request->email)->subject('Reset Your Affiliate Password — Servora');
            });
        }

        // Always return success to prevent email enumeration
        return back()->with('status', 'If an account exists with that email, a reset link has been sent.');
    }

    public function showResetPassword(Request $request)
    {
        return view('affiliate.reset-password', [
            'token' => $request->token,
            'email' => $request->email,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('affiliate_password_resets')->where('email', $request->email)->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return back()->withErrors(['email' => 'Invalid or expired reset link.']);
        }

        // Check expiry (60 minutes)
        if (now()->diffInMinutes($record->created_at) > 60) {
            return back()->withErrors(['email' => 'This reset link has expired. Please request a new one.']);
        }

        Affiliate::where('email', $request->email)->update(['password' => Hash::make($request->password)]);
        DB::table('affiliate_password_resets')->where('email', $request->email)->delete();

        return redirect()->route('affiliate.login')->with('status', 'Password reset successfully. You can now log in.');
    }

    public function logout(Request $request)
    {
        Auth::guard('affiliate')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('affiliate.login');
    }
}
