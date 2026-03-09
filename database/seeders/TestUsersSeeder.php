<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Get company & outlet from existing admin
        $admin = User::where('email', 'like', 'admin@%')->first();
        $companyId = $admin?->company_id ?? 1;
        $outletId = $admin?->outlet_id ?? 1;

        $testUsers = [
            [
                'name'  => 'System Admin',
                'email' => 'sysadmin@servora.test',
                'role'  => 'System Admin',
                'company_id' => $companyId,
                'outlet_id'  => null,
            ],
            [
                'name'  => 'Business Manager',
                'email' => 'manager@servora.test',
                'role'  => 'Business Manager',
                'company_id' => $companyId,
                'outlet_id'  => $outletId,
            ],
            [
                'name'  => 'Operations Manager',
                'email' => 'opsmanager@servora.test',
                'role'  => 'Operations Manager',
                'company_id' => $companyId,
                'outlet_id'  => $outletId,
            ],
            [
                'name'  => 'Branch Manager',
                'email' => 'branchmanager@servora.test',
                'role'  => 'Branch Manager',
                'company_id' => $companyId,
                'outlet_id'  => $outletId,
            ],
            [
                'name'  => 'Head Chef',
                'email' => 'chef@servora.test',
                'role'  => 'Chef',
                'company_id' => $companyId,
                'outlet_id'  => $outletId,
            ],
            [
                'name'  => 'Purchasing Officer',
                'email' => 'purchasing@servora.test',
                'role'  => 'Purchasing',
                'company_id' => $companyId,
                'outlet_id'  => $outletId,
            ],
            [
                'name'  => 'Finance Manager',
                'email' => 'finance@servora.test',
                'role'  => 'Finance',
                'company_id' => $companyId,
                'outlet_id'  => $outletId,
            ],
        ];

        foreach ($testUsers as $data) {
            $role = $data['role'];
            unset($data['role']);

            $user = User::firstOrCreate(
                ['email' => $data['email']],
                array_merge($data, [
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                ])
            );

            if (! $user->hasRole($role)) {
                $user->syncRoles([$role]);
            }
        }
    }
}
