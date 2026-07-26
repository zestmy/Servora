<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles get an editable display layer: `name` stays the stable internal
     * key (referenced by code: role checks, registration, access templates)
     * while `display_name` + `description` are what admins see and edit.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('display_name', 100)->nullable()->after('name');
            $table->string('description', 255)->nullable()->after('display_name');
        });

        $descriptions = [
            'Super Admin'        => 'Platform owner — bypasses every permission check.',
            'System Admin'       => 'Platform administration: companies, subscriptions, all users.',
            'Company Admin'      => 'Full access: every module, all settings, billing and user management.',
            'Business Manager'   => 'Runs the business day-to-day: all operational modules, reports, rosters, billing and user management.',
            'Operations Manager' => 'Operational oversight: purchasing, inventory, recipes, sales, reports and audit logs.',
            'Branch Manager'     => 'Runs outlets: sales, purchasing, inventory, reports and audit logs.',
            'Outlet Manager'     => 'Duty roster planning: create and edit rosters for their outlets.',
            'Chef'               => 'Kitchen-focused: recipes, ingredients, inventory and purchasing.',
            'Purchasing'         => 'Creates and processes purchase orders.',
            'Finance'            => 'Reviews the numbers: sales, purchasing, inventory and reports.',
            'HR Manager'         => 'People operations: employees, attendance, HR documents and duty rosters.',
            'Staff'              => 'No modules by default — add only what this person needs.',
        ];
        foreach ($descriptions as $name => $desc) {
            DB::table('roles')->where('name', $name)->whereNull('description')->update(['description' => $desc]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description']);
        });
    }
};
