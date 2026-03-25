<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'            => 'Starter',
                'slug'            => 'starter',
                'description'     => 'Perfect for single-outlet F&B businesses getting started with digital operations.',
                'price_monthly'   => 99.00,
                'price_yearly'    => 990.00,
                'max_outlets'     => 1,
                'max_users'       => 5,
                'max_recipes'     => 100,
                'max_ingredients' => 200,
                'max_lms_users'   => 10,
                'feature_flags'   => [
                    'lms'       => true,
                    'reports'   => true,
                    'analytics' => false,
                    'ai_analysis' => false,
                ],
                'sort_order' => 1,
                'trial_days' => 14,
            ],
            [
                'name'            => 'Professional',
                'slug'            => 'professional',
                'description'     => 'For growing businesses with multiple outlets needing full operational control.',
                'price_monthly'   => 249.00,
                'price_yearly'    => 2490.00,
                'max_outlets'     => 5,
                'max_users'       => 20,
                'max_recipes'     => null,
                'max_ingredients' => null,
                'max_lms_users'   => 50,
                'feature_flags'   => [
                    'lms'       => true,
                    'reports'   => true,
                    'analytics' => true,
                    'ai_analysis' => false,
                ],
                'sort_order' => 2,
                'trial_days' => 14,
            ],
            [
                'name'            => 'Enterprise',
                'slug'            => 'enterprise',
                'description'     => 'Unlimited access for large F&B operations with advanced analytics and AI.',
                'price_monthly'   => 499.00,
                'price_yearly'    => 4990.00,
                'max_outlets'     => null,
                'max_users'       => null,
                'max_recipes'     => null,
                'max_ingredients' => null,
                'max_lms_users'   => null,
                'feature_flags'   => [
                    'lms'       => true,
                    'reports'   => true,
                    'analytics' => true,
                    'ai_analysis' => true,
                ],
                'sort_order' => 3,
                'trial_days' => 14,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
