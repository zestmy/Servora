<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Re-runs the Phase 3a cleanup, because six phases of backfill undid it.
 *
 * 3a dropped every direct grant that the holder's role already provided, so that editing a
 * role would reach its holders. Each later phase then mirrored an existing ability onto new
 * ones — and where the source was held *directly* by a user, the mirror wrote new direct
 * rows. Repeated across 4a, 4b, 4c, 4d and 5, per-user pinning grew back until it dominated:
 * 627 direct rows against 385 role rows on production, with a Chef whose role grants 19
 * abilities carrying 55 of their own.
 *
 * The visible symptom is that changing somebody's role barely changes what they can do,
 * which is the whole point of having roles.
 *
 * Dropping a direct grant the role already provides cannot change anyone's effective
 * access — the role still grants it, which is what makes it a duplicate. What changes is
 * where it comes from, so a role edit reaches its holders again. Genuine per-person extras
 * are untouched.
 *
 * This is idempotent and safe to re-run. It is worth running again after any future phase
 * that mirrors abilities onto direct grants — or better, such a phase should mirror onto
 * ROLES where the source was held by a role, which is what keeps the two from diverging.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permsByRole = DB::table('role_has_permissions')->get(['role_id', 'permission_id'])
            ->groupBy('role_id')
            ->map(fn ($rows) => $rows->pluck('permission_id')->all());

        // A user may hold more than one role in a company; the union is what they get.
        $byUserTeam = [];
        foreach (DB::table('model_has_roles')->where('model_type', User::class)->get() as $assignment) {
            $key = $assignment->model_id . ':' . ($assignment->team_id ?? 'null');
            $byUserTeam[$key] = array_merge($byUserTeam[$key] ?? [], $permsByRole[$assignment->role_id] ?? []);
        }

        $deleted = 0;

        foreach ($byUserTeam as $key => $permissionIds) {
            [$userId, $teamId] = explode(':', $key);

            $query = DB::table('model_has_permissions')
                ->where('model_type', User::class)
                ->where('model_id', (int) $userId)
                ->whereIn('permission_id', array_unique($permissionIds));

            $teamId === 'null'
                ? $query->whereNull('team_id')
                : $query->where('team_id', (int) $teamId);

            $deleted += $query->delete();
        }

        logger()->info("[rbac] dropped {$deleted} direct grants already provided by the holder's role");

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Not restored. They were redundant by definition — the role still grants every one —
     * so putting them back would only reinstate the problem this removes.
     */
    public function down(): void
    {
        // no-op
    }
};
