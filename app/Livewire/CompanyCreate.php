<?php

namespace App\Livewire;

use App\Models\Coupon;
use App\Models\Plan;
use App\Services\CompanyRegistrationService;
use App\Services\CouponService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Full-page "Create New Company" for an existing, logged-in admin:
 * a separate company under the same login, with its own trial
 * subscription (optionally upgraded right away via a coupon).
 */
class CompanyCreate extends Component
{
    public string $company_name = '';
    public ?int   $plan_id = null;
    public string $billing_cycle = 'monthly';
    public string $coupon_code = '';

    public function mount(): void
    {
        abort_unless($this->authorized(), 403);

        $this->plan_id = Plan::active()->ordered()->value('id');
    }

    public function create(): void
    {
        abort_unless($this->authorized(), 403);

        $this->coupon_code = strtoupper(trim($this->coupon_code));

        $this->validate([
            'company_name'  => 'required|string|max:200',
            'plan_id'       => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
        ], [], [
            'company_name' => 'company name',
            'plan_id'      => 'plan',
        ]);

        // Pre-validate the coupon so the company isn't created with a bad code
        $coupon = null;
        if ($this->coupon_code) {
            $coupon = Coupon::where('code', $this->coupon_code)->first();
            if (! $coupon || ! $coupon->isRedeemable()) {
                $this->addError('coupon_code', 'Invalid or expired coupon code.');
                return;
            }
        }

        $user = Auth::user();

        $result = app(CompanyRegistrationService::class)->registerAdditionalCompany($user, [
            'company_name'  => $this->company_name,
            'plan_id'       => $this->plan_id,
            'billing_cycle' => $this->billing_cycle,
        ]);

        $message = 'Company "' . $result['company']->name . '" created — your free trial has started.';
        if ($coupon) {
            try {
                app(CouponService::class)->redeem($coupon, $result['company'], $user->id);
                $message = 'Company "' . $result['company']->name . '" created — your ' . $coupon->grantLabel() . ' free subscription has been activated.';
            } catch (\Throwable $e) {
                $message = 'Company "' . $result['company']->name . '" created, but the coupon could not be applied: ' . $e->getMessage();
            }
        }

        // Move into the new company and start its onboarding
        $user->switchToCompany($result['company']->id);
        session()->forget(['workspace_mode', 'active_kitchen_id']);
        session()->flash('success', $message);

        $this->redirect(route('onboarding'));
    }

    /** Company creation is for admins (Manage Users capability) only. */
    private function authorized(): bool
    {
        $user = Auth::user();

        return $user && ($user->isSystemRole() || $user->hasCapability('can_manage_users'));
    }

    public function render()
    {
        return view('livewire.company-create', [
            'plans' => Plan::active()->ordered()->get(),
        ])->layout('layouts.app', ['title' => 'Create New Company']);
    }
}
