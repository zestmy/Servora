<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed system UOMs
        $uoms = [
            // Weight
            ['name' => 'Kilogram',    'abbreviation' => 'kg',    'type' => 'weight',  'is_base_unit' => true,  'base_unit_factor' => 1.0,       'is_system' => true],
            ['name' => 'Gram',        'abbreviation' => 'g',     'type' => 'weight',  'is_base_unit' => false, 'base_unit_factor' => 0.001,     'is_system' => true],
            ['name' => 'Milligram',   'abbreviation' => 'mg',    'type' => 'weight',  'is_base_unit' => false, 'base_unit_factor' => 0.000001,  'is_system' => true],
            ['name' => 'Pound',       'abbreviation' => 'lb',    'type' => 'weight',  'is_base_unit' => false, 'base_unit_factor' => 0.453592,  'is_system' => true],
            ['name' => 'Ounce',       'abbreviation' => 'oz',    'type' => 'weight',  'is_base_unit' => false, 'base_unit_factor' => 0.028350,  'is_system' => true],
            // Volume
            ['name' => 'Litre',       'abbreviation' => 'L',     'type' => 'volume',  'is_base_unit' => true,  'base_unit_factor' => 1.0,       'is_system' => true],
            ['name' => 'Millilitre',  'abbreviation' => 'ml',    'type' => 'volume',  'is_base_unit' => false, 'base_unit_factor' => 0.001,     'is_system' => true],
            ['name' => 'Gallon',      'abbreviation' => 'gal',   'type' => 'volume',  'is_base_unit' => false, 'base_unit_factor' => 3.785411,  'is_system' => true],
            ['name' => 'Fluid Ounce', 'abbreviation' => 'fl oz', 'type' => 'volume',  'is_base_unit' => false, 'base_unit_factor' => 0.029574,  'is_system' => true],
            // Count
            ['name' => 'Piece',       'abbreviation' => 'pcs',   'type' => 'count',   'is_base_unit' => true,  'base_unit_factor' => 1.0,       'is_system' => true],
            ['name' => 'Dozen',       'abbreviation' => 'doz',   'type' => 'count',   'is_base_unit' => false, 'base_unit_factor' => 12.0,      'is_system' => true],
            ['name' => 'Pack',        'abbreviation' => 'pack',  'type' => 'count',   'is_base_unit' => false, 'base_unit_factor' => 1.0,       'is_system' => true],
            ['name' => 'Box',         'abbreviation' => 'box',   'type' => 'count',   'is_base_unit' => false, 'base_unit_factor' => 1.0,       'is_system' => true],
            ['name' => 'Carton',      'abbreviation' => 'ctn',   'type' => 'count',   'is_base_unit' => false, 'base_unit_factor' => 1.0,       'is_system' => true],
            ['name' => 'Tray',        'abbreviation' => 'tray',  'type' => 'count',   'is_base_unit' => false, 'base_unit_factor' => 1.0,       'is_system' => true],
            // Length
            ['name' => 'Metre',       'abbreviation' => 'm',     'type' => 'length',  'is_base_unit' => true,  'base_unit_factor' => 1.0,       'is_system' => true],
            ['name' => 'Centimetre',  'abbreviation' => 'cm',    'type' => 'length',  'is_base_unit' => false, 'base_unit_factor' => 0.01,      'is_system' => true],
        ];

        foreach ($uoms as $uom) {
            UnitOfMeasure::firstOrCreate(['abbreviation' => $uom['abbreviation']], $uom);
        }

        // 2. Create Spatie roles
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Company Admin',    'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Outlet Manager',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Staff',           'guard_name' => 'web']);

        // 3. Create demo Company
        $company = Company::firstOrCreate(
            ['slug' => 'demo-restaurant-co'],
            [
                'name'      => 'Demo Restaurant Co.',
                'email'     => 'info@demo.test',
                'phone'     => '+60-3-0000-0000',
                'currency'  => 'MYR',
                'is_active' => true,
            ]
        );

        // 4. Create demo Outlet
        $outlet = Outlet::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Main Branch'],
            [
                'company_id' => $company->id,
                'name'       => 'Main Branch',
                'code'       => 'MAIN',
                'is_active'  => true,
            ]
        );

        // 5. Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@servora.test'],
            [
                'name'       => 'Admin User',
                'email'      => 'admin@servora.test',
                'password'   => Hash::make('password'),
                'company_id' => $company->id,
                'outlet_id'  => $outlet->id,
            ]
        );

        $admin->assignRole($superAdmin);
    }
}
