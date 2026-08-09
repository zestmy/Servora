<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 4d — F3 for the three modules deferred earlier: Sales, Recipes, Ingredients.
 *
 * They were left until last because none of their writes commits money the way a purchase
 * order or an attendance mark does. Auditing them turned up two things that change that
 * reading:
 *
 *  - `recipes.view` let anyone change SELLING PRICES (`Recipes\Index::updatePrice`), which
 *    drives revenue and every margin report. That gets its own ability, for the same
 *    reason salary is separate from employee records: whoever maintains a method is not
 *    necessarily whoever decides what it sells for.
 *
 *  - deleting a recipe or an ingredient was guarded only by `assertUnlocked()`, which is a
 *    company-wide toggle in Company Details, not a permission. With the list unlocked —
 *    the normal state — `recipes.view` and `ingredients.view` were enough to delete.
 *
 * Imports are separated too: a bad import overwrites a period or the catalogue in one
 * action, which is a different class of mistake from editing one row.
 *
 * BACKFILL — preserve access exactly. Everything each `*.view` already permitted is
 * mirrored onto the new abilities, so nobody gains or loses anything on deploy; the
 * abilities become independently revocable from that point on.
 */
return new class extends Migration
{
    private const MAP = [
        'sales.view'       => ['sales.record', 'sales.import'],
        'recipes.view'     => ['recipes.manage', 'recipes.price', 'recipes.delete', 'recipes.import'],
        'ingredients.view' => ['ingredients.manage', 'ingredients.delete', 'ingredients.import'],
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

            logger()->info("[rbac phase 4d] mirrored {$source} onto " . implode(', ', $targets)
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
