<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * inventory.stock_takes.reopen — put a completed count back in progress for editing.
 *
 * Its own ability rather than riding on inventory.stock_takes.delete: reopening
 * is a lighter, non-destructive override (the record stays, just editable again),
 * while delete removes it outright. Different acts, so a company should be able
 * to grant one without the other on the Roles tab.
 *
 * BACKFILL — every role and direct user grant that already holds
 * inventory.stock_takes.delete also gets reopen, so nobody's access changes on
 * deploy: this is a brand-new capability, and "whoever could already wipe a
 * completed count outright can also unlock one for a fix" is the least
 * surprising default. It is one untick in Settings › Roles if a company wants
 * to split the two apart.
 */
return new class extends Migration
{
    private const SOURCE = 'inventory.stock_takes.delete';
    private const TARGET = 'inventory.stock_takes.reopen';

    public function up(): void
    {
        $now = now();

        $targetId = DB::table('permissions')
            ->where('name', self::TARGET)->where('guard_name', 'web')->value('id');

        if (! $targetId) {
            $targetId = DB::table('permissions')->insertGetId([
                'name' => self::TARGET, 'guard_name' => 'web',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $sourceId = DB::table('permissions')
            ->where('name', self::SOURCE)->where('guard_name', 'web')->value('id');

        if ($sourceId) {
            $roleIds = DB::table('role_has_permissions')->where('permission_id', $sourceId)->pluck('role_id');
            foreach ($roleIds as $roleId) {
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
        $id = DB::table('permissions')
            ->where('name', self::TARGET)->where('guard_name', 'web')->value('id');

        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permission_denials')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
