<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Follow-up to Phase 4b — the deliberate tightening it left for the account owner.
 *
 * 4b found that `deleteWastage()`, `deleteStaffMeal()`, `deletePurchase()` and
 * `deletePrepItem()` had no permission check at all: anyone who could open Inventory
 * could delete those records. Gating them without moving anyone's access meant granting
 * all four to every `inventory.view` holder — which closed the hole for the future but
 * carried the old breadth forward.
 *
 * Two of the four are now tightened to match `inventory.stock_takes.delete`:
 *
 *   inventory.purchases.delete    a captured purchase feeds costing
 *   inventory.prep_items.delete   deleting one also soft-deletes its synced ingredient
 *
 * Wastage and staff meals are deliberately left broad. Voiding a mistyped wastage line
 * is a same-day correction by the person who recorded it — routine kitchen work, and
 * making a chef find an admin for it would push them towards not recording wastage at
 * all, which is worse than the risk being managed.
 *
 * THIS REMOVES ACCESS from people who have it today, which no earlier migration in this
 * project did. The set is mirrored exactly from `inventory.stock_takes.delete`, so after
 * this the rule is simply: whoever may delete a stock take may also delete a captured
 * purchase or a prep item. Measured beforehand on production — 8 users lose these two,
 * 9 are unaffected because they already hold the stock-take delete.
 */
return new class extends Migration
{
    private const TIGHTENED = ['inventory.purchases.delete', 'inventory.prep_items.delete'];
    private const REFERENCE = 'inventory.stock_takes.delete';

    public function up(): void
    {
        $referenceId = DB::table('permissions')
            ->where('name', self::REFERENCE)->where('guard_name', 'web')->value('id');

        $targetIds = DB::table('permissions')
            ->whereIn('name', self::TIGHTENED)->where('guard_name', 'web')->pluck('id');

        if (! $referenceId || $targetIds->isEmpty()) {
            return;
        }

        // Clear the broad 4b grants, then mirror the reference ability's grants exactly —
        // at role level and user level alike, so the two abilities end up with an
        // identical distribution rather than an approximation of one.
        $removedRoles = DB::table('role_has_permissions')->whereIn('permission_id', $targetIds)->delete();
        $removedUsers = DB::table('model_has_permissions')->whereIn('permission_id', $targetIds)->delete();

        $roleIds = DB::table('role_has_permissions')->where('permission_id', $referenceId)->pluck('role_id');
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
            ->where('permission_id', $referenceId)->where('model_type', User::class)
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

        // A denial of one of these is now meaningless for anyone who no longer holds it,
        // and would resurface confusingly on the Effective Access tab.
        DB::table('permission_denials')->whereIn('permission_id', $targetIds)->delete();

        logger()->info(sprintf(
            '[rbac tighten] purchases/prep deletes: cleared %d role and %d user grants, '
            . 'mirrored %s onto %d roles and %d user grants',
            $removedRoles, $removedUsers, self::REFERENCE, $roleIds->count(), $direct->count()
        ));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Restores the pre-tightening breadth: both abilities back to everyone holding
     * `inventory.view`, which is what Phase 4b's backfill produced.
     */
    public function down(): void
    {
        $viewId = DB::table('permissions')
            ->where('name', 'inventory.view')->where('guard_name', 'web')->value('id');

        $targetIds = DB::table('permissions')
            ->whereIn('name', self::TIGHTENED)->where('guard_name', 'web')->pluck('id');

        if (! $viewId || $targetIds->isEmpty()) {
            return;
        }

        foreach (DB::table('role_has_permissions')->where('permission_id', $viewId)->pluck('role_id') as $roleId) {
            foreach ($targetIds as $permId) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permId]);
            }
        }

        foreach (DB::table('model_has_permissions')->where('permission_id', $viewId)->get(['model_id', 'team_id']) as $row) {
            foreach ($targetIds as $permId) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $permId, 'model_type' => User::class,
                    'model_id' => $row->model_id, 'team_id' => $row->team_id,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
