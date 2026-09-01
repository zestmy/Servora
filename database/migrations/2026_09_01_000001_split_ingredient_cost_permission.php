<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * What an ingredient costs gets its own ability, split out of `ingredients.manage`.
 *
 * Same reasoning that gave selling prices `recipes.price`: whoever maintains the
 * catalogue is not necessarily whoever decides what a thing is worth. Renaming an
 * item, filing it under a category or fixing its UOM is housekeeping. Changing its
 * cost moves stock value, every recipe costing built on it and the margin on
 * everything it appears in — and it does so with no invoice behind it, which is the
 * one price change in the product that no document can be checked against.
 *
 * The stock forms already stopped accepting a typed cost, so the Market List is now
 * the only screen where a price can be set by hand. That makes it worth a key of its
 * own rather than a side effect of catalogue access.
 *
 * BACKFILL — preserve access exactly. Everyone who can manage ingredients today can
 * already set costs today, so the new ability is mirrored onto every role and direct
 * grant that holds `ingredients.manage`. Nobody gains or loses anything on deploy;
 * cost becomes independently revocable from that point on.
 *
 * @see database/migrations/2026_08_10_000040_split_sales_recipes_ingredients_writes.php
 */
return new class extends Migration
{
    private const SOURCE = 'ingredients.manage';
    private const TARGET = 'ingredients.cost';

    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore([
            'name' => self::TARGET, 'guard_name' => 'web',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $sourceId = DB::table('permissions')
            ->where('name', self::SOURCE)->where('guard_name', 'web')->value('id');
        $targetId = DB::table('permissions')
            ->where('name', self::TARGET)->where('guard_name', 'web')->value('id');

        if (! $sourceId || ! $targetId) {
            return;
        }

        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $sourceId)->pluck('role_id');

        $rows = $roleIds->map(fn ($roleId) => ['role_id' => $roleId, 'permission_id' => $targetId])->all();
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('role_has_permissions')->insertOrIgnore($chunk);
        }

        $direct = DB::table('model_has_permissions')
            ->where('permission_id', $sourceId)->where('model_type', User::class)
            ->get(['model_id', 'team_id']);

        $rows = $direct->map(fn ($row) => [
            'permission_id' => $targetId,
            'model_type'    => User::class,
            'model_id'      => $row->model_id,
            'team_id'       => $row->team_id,
        ])->all();
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('model_has_permissions')->insertOrIgnore($chunk);
        }

        // A denial is a deliberate "this role, but not that one bit" — mirror those
        // too, or splitting the ability would hand back something taken away.
        if (DB::getSchemaBuilder()->hasTable('permission_denials')) {
            $denied = DB::table('permission_denials')
                ->where('permission_id', $sourceId)
                ->get(['model_type', 'model_id', 'team_id']);

            $rows = $denied->map(fn ($row) => [
                'permission_id' => $targetId,
                'model_type'    => $row->model_type,
                'model_id'      => $row->model_id,
                'team_id'       => $row->team_id,
                'created_at'    => $now,
                'updated_at'    => $now,
            ])->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('permission_denials')->insertOrIgnore($chunk);
            }
        }

        logger()->info('[rbac] mirrored ' . self::SOURCE . ' onto ' . self::TARGET
            . ' for ' . $roleIds->count() . ' roles and ' . $direct->count() . ' user grants');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $id = DB::table('permissions')
            ->where('name', self::TARGET)->where('guard_name', 'web')->value('id');

        if (! $id) {
            return;
        }

        DB::table('role_has_permissions')->where('permission_id', $id)->delete();
        DB::table('model_has_permissions')->where('permission_id', $id)->delete();
        if (DB::getSchemaBuilder()->hasTable('permission_denials')) {
            DB::table('permission_denials')->where('permission_id', $id)->delete();
        }
        DB::table('permissions')->where('id', $id)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
