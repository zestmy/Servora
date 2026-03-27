<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierUser;
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
        if (Auth::guard('supplier')->check()) {
            return redirect()->route('supplier.dashboard');
        }
        return view('supplier.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'name'         => 'required|string|max:100',
            'email'        => 'required|email|unique:supplier_users,email',
            'password'     => 'required|string|min:8|confirmed',
            'phone'        => 'nullable|string|max:20',
        ]);

        DB::transaction(function () use ($request) {
            // Create supplier record (without company_id — they'll be linked when a company maps them)
            $supplier = Supplier::withoutGlobalScopes()->create([
                'company_id'     => null,
                'name'           => $request->company_name,
                'email'          => $request->email,
                'phone'          => $request->phone,
                'portal_enabled' => true,
                'is_active'      => true,
            ]);

            $user = SupplierUser::create([
                'supplier_id' => $supplier->id,
                'name'        => $request->name,
                'email'       => $request->email,
                'password'    => Hash::make($request->password),
                'phone'       => $request->phone,
                'role'        => 'admin',
            ]);

            Auth::guard('supplier')->login($user);
        });

        $request->session()->regenerate();
        return redirect()->route('supplier.dashboard');
    }

    public function showLogin()
    {
        if (Auth::guard('supplier')->check()) {
            return redirect()->route('supplier.dashboard');
        }
        return view('supplier.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::guard('supplier')->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
        }

        $request->session()->regenerate();
        return redirect()->route('supplier.dashboard');
    }

    public function showForgotPassword()
    {
        return view('supplier.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = SupplierUser::where('email', $request->email)->first();
        if ($user) {
            $token = Str::random(64);
            DB::table('supplier_password_resets')->updateOrInsert(
                ['email' => $request->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $resetUrl = route('supplier.reset-password', ['token' => $token, 'email' => $request->email]);
            Mail::raw("Reset your Servora supplier portal password:\n\n{$resetUrl}\n\nThis link expires in 60 minutes.", function ($msg) use ($request) {
                $msg->to($request->email)->subject('Reset Your Supplier Password — Servora');
            });
        }

        return back()->with('status', 'If an account exists with that email, a reset link has been sent.');
    }

    public function showResetPassword(Request $request)
    {
        return view('supplier.reset-password', [
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

        $record = DB::table('supplier_password_resets')->where('email', $request->email)->first();
        if (! $record || ! Hash::check($request->token, $record->token)) {
            return back()->withErrors(['email' => 'Invalid or expired reset link.']);
        }
        if (now()->diffInMinutes($record->created_at) > 60) {
            return back()->withErrors(['email' => 'This reset link has expired.']);
        }

        SupplierUser::where('email', $request->email)->update(['password' => Hash::make($request->password)]);
        DB::table('supplier_password_resets')->where('email', $request->email)->delete();

        return redirect()->route('supplier.login')->with('status', 'Password reset successfully.');
    }

    public function logout(Request $request)
    {
        Auth::guard('supplier')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('supplier.login');
    }
}
