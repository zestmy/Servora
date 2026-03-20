<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\LmsUser;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin(string $companySlug)
    {
        $company = Company::where('slug', $companySlug)->where('is_active', true)->firstOrFail();

        return view('lms.auth.login', compact('company'));
    }

    public function login(Request $request, string $companySlug)
    {
        $company = Company::where('slug', $companySlug)->where('is_active', true)->firstOrFail();

        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = LmsUser::where('company_id', $company->id)
            ->where('email', $request->email)
            ->first();

        if (! $user) {
            return back()->withErrors(['email' => 'No account found with this email.'])->withInput();
        }

        if ($user->status === 'pending') {
            return back()->withErrors(['email' => 'Your registration is pending approval. Please contact your manager.'])->withInput();
        }

        if ($user->status === 'rejected') {
            return back()->withErrors(['email' => 'Your registration has been rejected. Please contact your manager.'])->withInput();
        }

        if (! Auth::guard('lms')->attempt(['email' => $request->email, 'password' => $request->password], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
        }

        $request->session()->regenerate();

        return redirect()->route('lms.dashboard');
    }

    public function showRegister(string $companySlug)
    {
        $company = Company::where('slug', $companySlug)->where('is_active', true)->firstOrFail();
        $outlets = Outlet::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get();

        return view('lms.auth.register', compact('company', 'outlets'));
    }

    public function register(Request $request, string $companySlug)
    {
        $company = Company::where('slug', $companySlug)->where('is_active', true)->firstOrFail();

        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:lms_users,email',
            'password'  => 'required|string|min:6|confirmed',
            'phone'     => 'nullable|string|max:50',
            'outlet_id' => 'nullable|exists:outlets,id',
        ]);

        LmsUser::create([
            'company_id' => $company->id,
            'outlet_id'  => $request->outlet_id,
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => $request->password,
            'phone'      => $request->phone,
            'status'     => 'pending',
        ]);

        return redirect()->route('lms.login', $companySlug)
            ->with('success', 'Registration submitted! Please wait for approval from your manager.');
    }

    public function logout(Request $request)
    {
        $companySlug = Auth::guard('lms')->user()?->company?->slug ?? 'app';

        Auth::guard('lms')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('lms.login', $companySlug);
    }
}
