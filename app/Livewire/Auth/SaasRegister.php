<?php

namespace App\Livewire\Auth;

use App\Models\Coupon;
use App\Models\Plan;
use App\Services\CompanyRegistrationService;
use App\Services\CouponService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SaasRegister extends Component
{
    public string $company_name  = '';
    public string $name          = '';
    public string $email         = '';
    public string $password      = '';
    public string $password_confirmation = '';
    // Always the trial plan (mount); not the browser's to choose.
    #[\Livewire\Attributes\Locked]
    public ?int   $plan_id       = null;
    public string $billing_cycle = 'monthly';
    public string $coupon_code   = '';

    public function mount(): void
    {
        // Pre-fill coupon from query string
        if (request()->has('coupon')) {
            $this->coupon_code = strtoupper(trim((string) request('coupon')));
        }

        // The trial runs on Full: the whole product (docs/pricing-model.md).
        // Falls back to any active plan on an install without the new plans.
        $this->plan_id = Plan::where('slug', 'full')->active()->value('id')
            ?? Plan::active()->ordered()->value('id');
    }

    protected function rules(): array
    {
        return [
            'company_name'          => 'required|string|max:200',
            'name'                  => 'required|string|max:200',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|string|min:8|confirmed',
            'plan_id'               => 'required|exists:plans,id',
            'billing_cycle'         => 'required|in:monthly,yearly',
        ];
    }

    protected function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered. Please log in instead.',
        ];
    }

    public function register(): void
    {
        $this->coupon_code = strtoupper(trim($this->coupon_code));
        $this->validate();

        // Pre-validate coupon (if provided) so registration doesn't proceed with invalid code
        $coupon = null;
        if ($this->coupon_code) {
            $coupon = Coupon::where('code', $this->coupon_code)->first();
            if (! $coupon || ! $coupon->isRedeemable()) {
                $this->addError('coupon_code', 'Invalid or expired coupon code.');
                return;
            }
        }

        $result = app(CompanyRegistrationService::class)->register([
            'company_name'  => $this->company_name,
            'name'          => $this->name,
            'email'         => $this->email,
            'password'      => $this->password,
            'plan_id'       => $this->plan_id,
            'billing_cycle' => $this->billing_cycle,
            'billing_country' => app(\App\Services\GeoIp::class)->country(request()),
        ]);

        // Apply coupon after company/subscription is created
        if ($coupon) {
            try {
                app(CouponService::class)->redeem($coupon, $result['company'], $result['user']->id);
                session()->flash('success', 'Welcome! Your ' . $coupon->grantLabel() . ' free subscription has been activated.');
            } catch (\Throwable $e) {
                // Non-fatal: continue with trial even if coupon fails
                session()->flash('warning', 'Coupon could not be applied: ' . $e->getMessage());
            }
        }

        // Log in the new user
        Auth::login($result['user']);

        // Redirect to onboarding
        $this->redirect(route('onboarding'));
    }

    public function render()
    {
        $trialDays = Plan::find($this->plan_id)?->trial_days ?? 14;

        return view('livewire.auth.saas-register', compact('trialDays'))
            ->layout('layouts.marketing', ['title' => 'Start Your Free Trial']);
    }
}
