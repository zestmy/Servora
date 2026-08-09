<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 4b of docs/rbac-revamp-proposal.md — F3 and the rest of F4, for Inventory.
 *
 * `inventory.view` granted recording of stock takes, wastage, transfers, staff meals,
 * prep items and captured purchases. Recording splits per document type here, because
 * each type already has its own form component — a cleaner seam than Purchasing had.
 *
 * It also closes a hole the original audit missed. `inventory.delete` existed, but only
 * deleteStockTake() and deleteTransfer() ever consulted it: deleteWastage(),
 * deleteStaffMeal(), deletePurchase() and deletePrepItem() had NO permission check at
 * all, so anyone who could open Inventory could delete those outright. All six are
 * guarded now, one ability each.
 *
 * BACKFILL — preserve today's access exactly, which here means mirroring two different
 * realities:
 *
 *   holders of `inventory.view`    -> all six *.record abilities        (what view allowed)
 *                                  -> wastage/staff_meals/purchases/prep_items *.delete
 *                                     because those deletes were UNGUARDED, so every
 *                                     viewer could already do them
 *   holders of `inventory.delete`  -> stock_takes.delete, transfers.delete
 *                                     the only two the old permission actually gated
 *
 * Nobody gains or loses a capability on deploy. What changes is that four destructive
 * actions which were previously ungated are now visible and revocable on the Roles tab —
 * see the note in the proposal about tightening them deliberately.
 *
 * `inventory.delete` is retired once its grants have been mirrored.
 */
return new class extends Migration
{
    private const RECORD = [
        'inventory.stock_takes.record', 'inventory.wastage.record', 'inventory.transfers.record',
        'inventory.staff_meals.record', 'inventory.prep_items.record', 'inventory.purchases.record',
    ];

    /** Deletes that were previously ungated — every `inventory.view` holder could do them. */
    private const DELETE_FROM_VIEW = [
        'inventory.wastage.delete', 'inventory.staff_meals.delete',
        'inventory.purchases.delete', 'inventory.prep_items.delete',
    ];

    /** Deletes the old `inventory.delete` actually gated. */
    private const DELETE_FROM_DELETE = [
        'inventory.stock_takes.delete', 'inventory.transfers.delete',
    ];

    public function up(): void
    {
        $now = now();
        $all = array_merge(self::RECORD, self::DELETE_FROM_VIEW, self::DELETE_FROM_DELETE);

        foreach ($all as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name, 'guard_name' => 'web',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $id = fn (string $n) => DB::table('permissions')
            ->where('name', $n)->where('guard_name', 'web')->value('id');

        $this->mirror($id('inventory.view'), array_merge(self::RECORD, self::DELETE_FROM_VIEW));
        $this->mirror($id('inventory.delete'), self::DELETE_FROM_DELETE);

        // Retire the module-wide delete now its grants have been carried across.
        if ($oldId = $id('inventory.delete')) {
            DB::table('role_has_permissions')->where('permission_id', $oldId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $oldId)->delete();
            DB::table('permission_denials')->where('permission_id', $oldId)->delete();
            DB::table('permissions')->where('id', $oldId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Copy every grant of $sourceId onto each of $targetNames, for roles and users alike. */
    private function mirror(?int $sourceId, array $targetNames): void
    {
        if (! $sourceId) {
            return;
        }

        $targetIds = DB::table('permissions')
            ->whereIn('name', $targetNames)->where('guard_name', 'web')->pluck('id');

        $roleIds = DB::table('role_has_permissions')->where('permission_id', $sourceId)->pluck('role_id');
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

        logger()->info('[rbac phase 4b] mirrored permission ' . $sourceId . ' onto '
            . count($targetNames) . ' abilities for ' . $roleIds->count() . ' roles and '
            . $direct->count() . ' user grants');
    }

    /**
     * Recreates `inventory.delete` and gives it back to anyone holding the two abilities
     * it used to gate, so a rollback lands where it started.
     */
    public function down(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'inventory.delete', 'guard_name' => 'web',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $oldId = DB::table('permissions')->where('name', 'inventory.delete')->value('id');

        $stockDeleteId = DB::table('permissions')->where('name', 'inventory.stock_takes.delete')->value('id');
        if ($stockDeleteId) {
            foreach (DB::table('role_has_permissions')->where('permission_id', $stockDeleteId)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $oldId]);
            }
            foreach (DB::table('model_has_permissions')->where('permission_id', $stockDeleteId)->get(['model_id', 'team_id']) as $r) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $oldId, 'model_type' => User::class,
                    'model_id' => $r->model_id, 'team_id' => $r->team_id,
                ]);
            }
        }

        $ids = DB::table('permissions')
            ->whereIn('name', array_merge(self::RECORD, self::DELETE_FROM_VIEW, self::DELETE_FROM_DELETE))
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permission_denials')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
