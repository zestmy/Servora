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
            $user->companies()->syncWithoutDetaching([$company->id]);

            // Registration runs unauthenticated: scope the role/permission
            // grants below to the new company (Spatie teams mode).
            setPermissionsTeamId($company->id);
            $user->assignRole('Company Admin');

            // Set Company Admin capabilities and permissions. Capabilities are
            // per-company (pivot); this also mirrors to the users-table cache
            // since the new company is the user's active one.
            $user->update(['designation' => 'Company Admin']);
            // Outlet scope is still a flag — it says where these abilities apply, not
            // what they are. Everything else the founder gets is a permission.
            $user->setCapabilitiesForCompany($company->id, ['can_view_all_outlets' => true]);
            $user->givePermissionTo([
                'ingredients.view', 'recipes.view', 'sales.view',
                'inventory.view', 'purchasing.view', 'reports.view',
                'settings.view', 'users.manage', 'hr.view',
                'hr.attendance', 'hr.claims', 'hr.clock', 'hr.clock.manage', 'staff.pins',
                'hr.documents.view', 'hr.documents.manage',
                // Were capability flags before Phase 1: approve PO/PR, delete records.
                'purchasing.approve', 'purchasing.request', 'purchasing.delete',
                'sales.delete', 'hr.clock.delete', 'hr.claims.delete',
                // Phase 4b split inventory.delete per document type.
                'inventory.stock_takes.delete', 'inventory.wastage.delete', 'inventory.transfers.delete',
                'inventory.staff_meals.delete', 'inventory.prep_items.delete', 'inventory.purchases.delete',
                'inventory.stock_takes.record', 'inventory.wastage.record', 'inventory.transfers.record',
                'inventory.staff_meals.record', 'inventory.prep_items.record', 'inventory.purchases.record',
                'purchasing.orders.create', 'purchasing.orders.edit', 'purchasing.requests.create',
                'purchasing.requests.edit', 'purchasing.transfers.create', 'purchasing.suppliers.manage',
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

    /**
     * Create an ADDITIONAL company for an existing, logged-in user
     * (multi-company: same login, new company with its own trial
     * subscription). Same provisioning as register() minus user creation.
     * The caller is responsible for switching the user's active company.
     */
    public function registerAdditionalCompany(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data) {
            $plan = Plan::findOrFail($data['plan_id']);
            $slug = $this->generateUniqueSlug($data['company_name']);

            $company = Company::create([
                'name'           => $data['company_name'],
                'slug'           => $slug,
                'email'          => $user->email,
                'currency'       => 'MYR',
                'is_active'      => true,
                'registered_via' => 'self_signup',
            ]);

            $user->companies()->syncWithoutDetaching([$company->id]);

            // Scope role/permission grants to the new company; the caller's
            // switchToCompany() re-sets the team afterwards.
            setPermissionsTeamId($company->id);
            $user->assignRole('Company Admin');
            // Only outlet scope is still a flag. The other five columns were left here
            // when Phase 1 turned the capability flags into permissions — they are inert
            // now, so this path was quietly handing founders of a SECOND company none of
            // the approve or delete rights that register() grants for their first.
            $user->setCapabilitiesForCompany($company->id, ['can_view_all_outlets' => true]);
            $user->givePermissionTo([
                'ingredients.view', 'recipes.view', 'sales.view',
                'inventory.view', 'purchasing.view', 'reports.view',
                'settings.view', 'users.manage', 'hr.view',
                'hr.attendance', 'hr.claims', 'hr.clock', 'hr.clock.manage', 'staff.pins',
                'hr.documents.view', 'hr.documents.manage',
                'purchasing.approve', 'purchasing.request', 'purchasing.delete',
                'sales.delete', 'hr.clock.delete', 'hr.claims.delete',
                'purchasing.orders.create', 'purchasing.orders.edit', 'purchasing.requests.create',
                'purchasing.requests.edit', 'purchasing.transfers.create', 'purchasing.suppliers.manage',
                'inventory.stock_takes.record', 'inventory.wastage.record', 'inventory.transfers.record',
                'inventory.staff_meals.record', 'inventory.prep_items.record', 'inventory.purchases.record',
                'inventory.stock_takes.delete', 'inventory.wastage.delete', 'inventory.transfers.delete',
                'inventory.staff_meals.delete', 'inventory.prep_items.delete', 'inventory.purchases.delete',
            ]);

            $outlet = Outlet::create([
                'company_id' => $company->id,
                'name'       => 'Main Outlet',
                'code'       => 'MAIN',
                'is_active'  => true,
            ]);
            $user->outlets()->attach($outlet->id);

            $subscription = app(SubscriptionService::class)->createTrial($company, $plan, $data['billing_cycle'] ?? 'monthly');

            foreach (OnboardingStep::STEPS as $step) {
                OnboardingStep::create([
                    'company_id' => $company->id,
                    'step'       => $step,
                ]);
            }

            return [
                'company'      => $company,
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
