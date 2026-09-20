<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The nine abilities of the Assets module.
 *
 * BACKFILLED FROM THE NEAREST EXISTING ABILITY, so nobody has to go hunting
 * through Settings ▸ Roles on deploy day to find a module that is invisible to
 * every role in the company — including the one the owner is on. Each new
 * ability is granted to exactly the roles and direct grants that already hold
 * its source, because in every case the source is the same act on the food
 * side of the product:
 *
 *   assets.view              ← ingredients.view               (see a catalogue and its costs)
 *   assets.manage            ← ingredients.manage             (curate that catalogue)
 *   assets.cost              ← ingredients.cost               (set what things cost)
 *   assets.delete            ← ingredients.delete
 *   assets.counts.record     ← inventory.stock_takes.record   (walk the floor and count)
 *   assets.counts.delete     ← inventory.stock_takes.delete
 *   assets.counts.reopen     ← inventory.stock_takes.reopen
 *   assets.movements.record  ← inventory.purchases.record     (book in what arrived)
 *   assets.movements.delete  ← inventory.purchases.delete
 *
 * Whoever already curates and prices the Market List gets the Asset List;
 * whoever already counts the walk-in gets the asset count. Any company that
 * wants the two apart unticks one column on the Roles tab — which is the whole
 * point of splitting the abilities this finely in the first place.
 */
return new class extends Migration
{
    /** target => source it is backfilled from */
    private const ABILITIES = [
        'assets.view'             => 'ingredients.view',
        'assets.manage'           => 'ingredients.manage',
        'assets.cost'             => 'ingredients.cost',
        'assets.delete'           => 'ingredients.delete',
        'assets.counts.record'    => 'inventory.stock_takes.record',
        'assets.counts.delete'    => 'inventory.stock_takes.delete',
        'assets.counts.reopen'    => 'inventory.stock_takes.reopen',
        'assets.movements.record' => 'inventory.purchases.record',
        'assets.movements.delete' => 'inventory.purchases.delete',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ABILITIES as $target => $source) {
            $targetId = DB::table('permissions')
                ->where('name', $target)->where('guard_name', 'web')->value('id');

            if (! $targetId) {
                $targetId = DB::table('permissions')->insertGetId([
                    'name' => $target, 'guard_name' => 'web',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $sourceId = DB::table('permissions')
                ->where('name', $source)->where('guard_name', 'web')->value('id');

            if (! $sourceId) {
                continue;
            }

            foreach (DB::table('role_has_permissions')->where('permission_id', $sourceId)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'role_id' => $roleId, 'permission_id' => $targetId,
                ]);
            }

            $direct = DB::table('model_has_permissions')
                ->where('permission_id', $sourceId)->where('model_type', User::class)
                ->get(['model_id', 'team_id']);

            foreach ($direct as $row) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $targetId, 'model_type' => User::class,
                    'model_id' => $row->model_id, 'team_id' => $row->team_id,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', array_keys(self::ABILITIES))
            ->where('guard_name', 'web')
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permission_denials')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
