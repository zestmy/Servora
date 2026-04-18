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
    /**
     * Resolve the company from subdomain (container) or from the route parameter.
     */
    private function resolveCompany(?string $companySlug = null): Company
    {
        // Prefer subdomain-resolved company
        if (app()->bound('currentCompany')) {
            return app('currentCompany');
        }

        // Fall back to slug parameter
        if ($companySlug) {
            return Company::where('slug', $companySlug)->where('is_active', true)->firstOrFail();
        }

        abort(404, 'Company not found.');
    }

    /**
     * Build the LMS login redirect URL.
     * Uses subdomain if currently on one, otherwise uses path-based route.
     */
    private function loginUrl(Company $company): string
    {
        // If already on a company subdomain, stay on it
        if (app()->bound('currentCompany')) {
            return url('/lms/login');
        }

        return route('lms.login', $company->slug);
    }

    public function showLogin(?string $companySlug = null)
    {
        $company = $this->resolveCompany($companySlug);

        return $this->noStore(response()->view('lms.auth.login', compact('company')));
    }

    public function login(Request $request, ?string $companySlug = null)
    {
        $company = $this->resolveCompany($companySlug);

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
        session(['lms_company_slug' => $company->slug]);

        $intended = session()->pull('intended', route('lms.dashboard'));
        return redirect($intended);
    }

    public function showRegister(?string $companySlug = null)
    {
        $company = $this->resolveCompany($companySlug);
        $outlets = Outlet::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get();

        return $this->noStore(response()->view('lms.auth.register', compact('company', 'outlets')));
    }

    /**
     * CSRF tokens are per-session; if a login/register page gets cached
     * (browser HTTP cache or service worker), a form POST will 419. Force
     * these auth pages to always come from the network.
     */
    private function noStore($response)
    {
        return $response
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function register(Request $request, ?string $companySlug = null)
    {
        $company = $this->resolveCompany($companySlug);

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

        return redirect($this->loginUrl($company))
            ->with('success', 'Registration submitted! Please wait for approval from your manager.');
    }

    public function logout(Request $request)
    {
        $lmsUser = Auth::guard('lms')->user();
        $company = $lmsUser?->company;

        Auth::guard('lms')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($company) {
            return redirect($this->loginUrl($company));
        }

        return redirect('/');
    }
}
