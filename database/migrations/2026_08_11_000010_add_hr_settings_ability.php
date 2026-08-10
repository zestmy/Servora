<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Configuring HR becomes its own ability, separate from doing HR.
 *
 * HR Settings was reachable by 16 of the 17 people in the product, including
 * five Branch Managers, four Chefs and someone in Purchasing. Not because
 * anybody granted it, but because its screens were gated on OPERATIONAL
 * abilities:
 *
 *   Employee Particulars  → hr.view          (every Chef has it)
 *   Leave Types           → hr.leave.approve (approving leave is not the same
 *   Public Holidays       → hr.leave.approve  as deciding what leave exists)
 *   Leave Approvers       → hr.leave.approve
 *   Clock-In Settings     → hr.clock.manage  (Chef, Purchasing, Branch Manager)
 *
 * Approving a leave request and defining the company's leave types are
 * different powers, and the second is company-wide. `settings.hr` is the second
 * one, granted to the roles that already configure things rather than to
 * everyone who works with people.
 *
 * OPERATIONS MANAGER IS INCLUDED deliberately: they already hold
 * settings.sections, settings.certifications and settings.ot_approvers, which
 * are HR configuration by another name. Taking that away was not asked for.
 * Dropping them is one line here if it should be Company Admin and HR Manager
 * alone.
 */
return new class extends Migration
{
    private const ROLES = ['Company Admin', 'HR Manager', 'Operations Manager'];

    public function up(): void
    {
        $id = DB::table('permissions')->where('name', 'settings.hr')->where('guard_name', 'web')->value('id');

        if (! $id) {
            $id = DB::table('permissions')->insertGetId([
                'name'       => 'settings.hr',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Declarative rather than additive: re-running must not leave the
        // ability on a role that has since been taken off the list.
        DB::table('role_has_permissions')->where('permission_id', $id)->delete();

        $roleIds = DB::table('roles')->whereIn('name', self::ROLES)->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $id,
                'role_id'       => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('name', 'settings.hr')->where('guard_name', 'web')->value('id');

        if (! $id) {
            return;
        }

        DB::table('role_has_permissions')->where('permission_id', $id)->delete();
        DB::table('model_has_permissions')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();
    }
};
