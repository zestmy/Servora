<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 1 of docs/rbac-revamp-proposal.md: the six capability flags become ordinary
 * permissions, and the global `can_delete_records` splits per module.
 *
 * Both systems are already per-company — the flags live on the `company_user` pivot and
 * Spatie is in teams mode with `team_id` = company — so this is a straight row-for-row
 * copy of the pivot into `model_has_permissions`, one grant per (user, company, ability).
 *
 * Deliberately NOT a dual-read cutover. The proposal originally called for
 * `can($perm) || $flag` during a transition, but that traps the revoke: an admin unticks
 * "Approve orders", the permission goes, the stale pivot flag stays `true`, and the check
 * still passes. A full copy misses nothing, so there is nothing to fall back to.
 *
 * `can_view_all_outlets` is untouched. It says which outlets a user's abilities apply to,
 * not what they may do, so it stays a flag next to the outlet picker.
 *
 * Roles are untouched too. The flags were always per-user, and the agreed backfill posture
 * is to preserve today's effective access exactly — writing these onto roles would hand
 * them to people who had the flag unticked.
 */
return new class extends Migration
{
    /** capability flag => permissions it becomes */
    private const MAP = [
        'can_approve_po'      => ['purchasing.approve'],
        'can_approve_pr'      => ['purchasing.request'],
        'can_receive_grn'     => ['purchasing.receive'],
        'can_manage_invoices' => ['purchasing.invoice'],
        'can_manage_users'    => ['users.manage'],
        'can_delete_records'  => [
            'purchasing.delete', 'sales.delete', 'inventory.delete',
            'hr.clock.delete', 'hr.claims.delete',
        ],
    ];

    public function up(): void
    {
        $now = now();

        // The registry declares these, but permissions:sync may not have run yet on a
        // given environment — and this migration must not depend on the order.
        $names = collect(self::MAP)->flatten()->unique();

        foreach ($names as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name, 'guard_name' => 'web',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $permIds = DB::table('permissions')
            ->whereIn('name', $names)->where('guard_name', 'web')
            ->pluck('id', 'name');

        $rows = [];

        foreach (self::MAP as $flag => $permissions) {
            $holders = DB::table('company_user')->where($flag, true)
                ->get(['user_id', 'company_id']);

            foreach ($holders as $holder) {
                foreach ($permissions as $permission) {
                    $rows[] = [
                        'permission_id' => $permIds[$permission],
                        'model_type'    => \App\Models\User::class,
                        'model_id'      => $holder->user_id,
                        'team_id'       => $holder->company_id,
                    ];
                }
            }
        }

        // insertOrIgnore, not insert: users.manage is already granted as a direct
        // permission by the old Settings > Users save path (it wrote both the flag and
        // the permission), so roughly half of those rows already exist.
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('model_has_permissions')->insertOrIgnore($chunk);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Removes only what up() could have created. `users.manage` is deliberately left in
     * place: it predates this migration as a real grant, and dropping it here would lock
     * admins out of Settings > Users on rollback.
     */
    public function down(): void
    {
        $names = collect(self::MAP)->flatten()->unique()->reject(fn ($n) => $n === 'users.manage');

        $permIds = DB::table('permissions')
            ->whereIn('name', $names)->where('guard_name', 'web')
            ->pluck('id');

        DB::table('model_has_permissions')->whereIn('permission_id', $permIds)->delete();
        DB::table('role_has_permissions')->whereIn('permission_id', $permIds)->delete();
        DB::table('permissions')->whereIn('id', $permIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
