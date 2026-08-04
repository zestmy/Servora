<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * staff.pins — issue and revoke the PIN that opens the staff apps.
     *
     * The screen moved from Labels to HR when one PIN started opening both
     * the label app and clock-in. Its old gate, labels.manage, is now the
     * wrong question: issuing a PIN grants attendance access, which is a
     * people decision, not a printer one.
     *
     * Inherited from BOTH labels.manage and hr.view — a union, deliberately.
     * Gating it on hr.view alone would silently strip the ability from every
     * labels manager who has been issuing PINs since the label app shipped,
     * and a permission migration that quietly takes something away is how a
     * kitchen discovers on a Monday morning that nobody can grant access.
     */
    private const SOURCES = ['labels.manage', 'hr.view'];

    public function up(): void
    {
        $now = now();

        $permId = DB::table('permissions')
            ->where('name', 'staff.pins')->where('guard_name', 'web')->value('id');

        if (! $permId) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => 'staff.pins', 'guard_name' => 'web',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (self::SOURCES as $source) {
            $this->inherit($permId, $source);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Grant the new permission everywhere the source one is already held —
     * both role grants and the direct per-user grants Settings > Users hands
     * out, so nobody has to be re-ticked one by one.
     *
     * model_has_permissions carries a team column in Spatie's teams mode; it
     * is copied verbatim so a per-company grant stays scoped to that company.
     */
    private function inherit(int $permId, string $sourceName): void
    {
        $sourceId = DB::table('permissions')
            ->where('name', $sourceName)->where('guard_name', 'web')->value('id');

        if (! $sourceId) {
            return;
        }

        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $sourceId)->pluck('role_id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permId, 'role_id' => $roleId,
            ]);
        }

        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
        $hasTeam = DB::getSchemaBuilder()->hasColumn('model_has_permissions', $teamKey);

        foreach (DB::table('model_has_permissions')->where('permission_id', $sourceId)->get() as $row) {
            $insert = [
                'permission_id' => $permId,
                'model_type'    => $row->model_type,
                'model_id'      => $row->model_id,
            ];

            if ($hasTeam) {
                $insert[$teamKey] = $row->{$teamKey};
            }

            DB::table('model_has_permissions')->insertOrIgnore($insert);
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')
            ->where('name', 'staff.pins')->where('guard_name', 'web')->value('id');

        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
