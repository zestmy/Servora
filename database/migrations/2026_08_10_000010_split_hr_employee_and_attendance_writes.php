<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 4c of docs/rbac-revamp-proposal.md — F3 for HR.
 *
 * Most of HR was already split by earlier phases: payroll, leave and compensation each
 * carry their own approve ability, clock has manage and delete, documents have view and
 * manage. Two coarse permissions were left.
 *
 * `hr.view` gated the employee list AND creating, editing and deleting employee records —
 * which carry IC numbers, bank details and the link to payroll. Worse, Employees::delete()
 * checked only that the employee belonged to an outlet the user could reach; there was no
 * permission check on the deletion itself.
 *
 * `hr.attendance` gated reading the attendance grid AND editing it — marking present or
 * absent, filling and clearing ranges, and managing attendance codes. Attendance feeds
 * payroll, so that is a financially material write sitting behind a read permission.
 * (Service charge is not touched here: saveServiceCharge() already aborts unless the user
 * passes Employee::canViewPay(), which is the stricter compensation gate.)
 *
 * BACKFILL — preserve today's access exactly:
 *   holders of `hr.view`       -> hr.employees.manage, hr.employees.delete
 *   holders of `hr.attendance` -> hr.attendance.record
 *
 * Nobody gains or loses anything on deploy. What changes is that "read the staff list" and
 * "edit staff records" are now separable, and deleting an employee is finally an ability
 * someone has to be given.
 */
return new class extends Migration
{
    private const MAP = [
        'hr.view'       => ['hr.employees.manage', 'hr.employees.delete'],
        'hr.attendance' => ['hr.attendance.record'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::MAP as $targets) {
            foreach ($targets as $name) {
                DB::table('permissions')->insertOrIgnore([
                    'name' => $name, 'guard_name' => 'web',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        foreach (self::MAP as $source => $targets) {
            $sourceId = DB::table('permissions')
                ->where('name', $source)->where('guard_name', 'web')->value('id');

            if (! $sourceId) {
                continue;
            }

            $targetIds = DB::table('permissions')
                ->whereIn('name', $targets)->where('guard_name', 'web')->pluck('id');

            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $sourceId)->pluck('role_id');

            $rows = [];
            foreach ($roleIds as $roleId) {
                foreach ($targetIds as $permId) {
                    $rows[] = ['role_id' => $roleId, 'permission_id' => $permId];
                }
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('role_has_permissions')->insertOrIgnore($chunk);
            }

            $direct = DB::table('model_has_permissions')
                ->where('permission_id', $sourceId)->where('model_type', User::class)
                ->get(['model_id', 'team_id']);

            $rows = [];
            foreach ($direct as $row) {
                foreach ($targetIds as $permId) {
                    $rows[] = [
                        'permission_id' => $permId,
                        'model_type'    => User::class,
                        'model_id'      => $row->model_id,
                        'team_id'       => $row->team_id,
                    ];
                }
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('model_has_permissions')->insertOrIgnore($chunk);
            }

            logger()->info("[rbac phase 4c] mirrored {$source} onto " . implode(', ', $targets)
                . ' for ' . $roleIds->count() . ' roles and ' . $direct->count() . ' user grants');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $names = collect(self::MAP)->flatten()->unique();

        $ids = DB::table('permissions')
            ->whereIn('name', $names)->where('guard_name', 'web')->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permission_denials')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
