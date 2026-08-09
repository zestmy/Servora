<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 4a of docs/rbac-revamp-proposal.md — finding F3, for Purchasing.
 *
 * `purchasing.view` granted 19 routes: raising purchase orders and requests, converting
 * and consolidating them, creating stock transfers, and maintaining the supplier
 * directory, price alerts and order form templates. There was no way to let someone read
 * the numbers without also letting them commit the company to spending money.
 *
 * The writes now split out. `purchasing.view` keeps its name and becomes genuinely
 * read-only — deliberately NOT renamed, because it is held broadly in production and a
 * rename is a grant migration with nothing to gain here.
 *
 * View stays module-level rather than splitting per document type: the Purchasing index
 * is a single tabbed Livewire component rendering orders, requests, GRNs and invoices
 * together, so "read orders but not invoices" cannot be enforced without splitting that
 * component first.
 *
 * BACKFILL — preserve today's access exactly (the agreed posture). Every role and every
 * user that holds `purchasing.view` today receives all six new write abilities, because
 * today that is precisely what `purchasing.view` lets them do. Nobody gains or loses
 * anything on deploy; admins tighten afterwards, deliberately, on the Roles tab.
 */
return new class extends Migration
{
    private const NEW_ABILITIES = [
        'purchasing.orders.create',
        'purchasing.orders.edit',
        'purchasing.requests.create',
        'purchasing.requests.edit',
        'purchasing.transfers.create',
        'purchasing.suppliers.manage',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::NEW_ABILITIES as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name, 'guard_name' => 'web',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $newIds = DB::table('permissions')
            ->whereIn('name', self::NEW_ABILITIES)->where('guard_name', 'web')
            ->pluck('id');

        $viewId = DB::table('permissions')
            ->where('name', 'purchasing.view')->where('guard_name', 'web')->value('id');

        if (! $viewId) {
            return; // nothing to mirror
        }

        // Roles holding purchasing.view
        $roleIds = DB::table('role_has_permissions')->where('permission_id', $viewId)->pluck('role_id');
        $roleRows = [];
        foreach ($roleIds as $roleId) {
            foreach ($newIds as $permId) {
                $roleRows[] = ['role_id' => $roleId, 'permission_id' => $permId];
            }
        }
        foreach (array_chunk($roleRows, 500) as $chunk) {
            DB::table('role_has_permissions')->insertOrIgnore($chunk);
        }

        // Users holding purchasing.view directly, per company (teams mode)
        $direct = DB::table('model_has_permissions')
            ->where('permission_id', $viewId)
            ->where('model_type', User::class)
            ->get(['model_id', 'team_id']);

        $userRows = [];
        foreach ($direct as $row) {
            foreach ($newIds as $permId) {
                $userRows[] = [
                    'permission_id' => $permId,
                    'model_type'    => User::class,
                    'model_id'      => $row->model_id,
                    'team_id'       => $row->team_id,
                ];
            }
        }
        foreach (array_chunk($userRows, 500) as $chunk) {
            DB::table('model_has_permissions')->insertOrIgnore($chunk);
        }

        logger()->info('[rbac phase 4a] mirrored purchasing.view onto ' . count(self::NEW_ABILITIES)
            . ' write abilities for ' . $roleIds->count() . ' roles and ' . $direct->count() . ' user grants');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', self::NEW_ABILITIES)->where('guard_name', 'web')->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permission_denials')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
