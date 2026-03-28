<?php

namespace App\Services;

use App\Models\Company;
use App\Models\OnboardingStep;
use App\Models\Outlet;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanyRegistrationService
{
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $plan = Plan::findOrFail($data['plan_id']);

            // Generate unique slug
            $slug = $this->generateUniqueSlug($data['company_name']);

            // Create company
            $company = Company::create([
                'name'           => $data['company_name'],
                'slug'           => $slug,
                'email'          => $data['email'],
                'currency'       => 'MYR',
                'is_active'      => true,
                'registered_via' => 'self_signup',
            ]);

            // Create admin user
            $user = User::create([
                'name'       => $data['name'],
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']),
                'company_id' => $company->id,
            ]);
            $user->assignRole('Company Admin');

            // Set Company Admin capabilities and permissions
            $user->update([
                'designation'          => 'Company Admin',
                'can_manage_users'     => true,
                'can_approve_po'       => true,
                'can_approve_pr'       => true,
                'can_delete_records'   => true,
                'can_view_all_outlets' => true,
            ]);
            $user->givePermissionTo([
                'ingredients.view', 'recipes.view', 'sales.view',
                'inventory.view', 'purchasing.view', 'reports.view',
                'settings.view', 'users.manage',
            ]);

            // Create default outlet
            $outlet = Outlet::create([
                'company_id' => $company->id,
                'name'       => 'Main Outlet',
                'code'       => 'MAIN',
                'is_active'  => true,
            ]);

            // Assign user to outlet
            $user->outlets()->attach($outlet->id);

            // Create trial subscription
            $subscription = app(SubscriptionService::class)->createTrial($company, $plan, $data['billing_cycle'] ?? 'monthly');

            // Create onboarding steps
            foreach (OnboardingStep::STEPS as $step) {
                OnboardingStep::create([
                    'company_id' => $company->id,
                    'step'       => $step,
                ]);
            }

            // Track referral if cookie present
            $referralCode = request()->cookie('referral_code');
            if ($referralCode) {
                app(ReferralService::class)->recordSignup($company, $referralCode);
            }

            return [
                'company'      => $company,
                'user'         => $user,
                'outlet'       => $outlet,
                'subscription' => $subscription,
            ];
        });
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);

        if (!$baseSlug) {
            $baseSlug = 'company';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (Company::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }
}
