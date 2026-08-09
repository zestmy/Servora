<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 3a of docs/rbac-revamp-proposal.md — two changes that together make a role
 * definition actually mean something.
 *
 * 1. `permission_denials` — "this role, but not that one bit".
 *
 *    The proposal specified a `type` enum on `model_has_permissions`. That is unsafe:
 *    Spatie's HasPermissions::permissions() relation filters on `team_id` and nothing
 *    else (see the trait), so a row marked `type = 'deny'` sitting in that table would be
 *    read by Spatie as a GRANT — the precise inverse of what it means. Denials therefore
 *    get their own table, where Spatie cannot mistake them for anything, and are applied
 *    in a Gate::before hook so they cover `can:` middleware, @can and can() alike.
 *
 * 2. Dropping direct grants that merely duplicate the holder's role (finding F6).
 *
 *    Settings > Users used to merge a role's permissions into the user's DIRECT
 *    permissions on every save. The effect was that role templates could only ever add:
 *    remove a module from a role and every existing holder kept it, because they had
 *    their own copy. The Role Templates screen said the change applied to "every user
 *    holding this role, in every company", and for removals that was false.
 *
 *    Deleting the duplicates cannot change anyone's effective access — the role still
 *    grants exactly those abilities, which is why they were duplicates. What it changes
 *    is where the grant COMES FROM, so that editing the role now reaches its holders.
 *    Grants the role does not carry are left alone: those are genuine per-user extras.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permission_denials')) {
            Schema::create('permission_denials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                // The company, matching Spatie's teams key. A denial is per-company for
                // the same reason a grant is: one login can work for several companies.
                $table->unsignedBigInteger('team_id');
                $table->timestamps();

                $table->unique(['team_id', 'permission_id', 'model_id', 'model_type'], 'permission_denials_unique');
                $table->index(['model_id', 'model_type']);
            });
        }

        // ---- F6: drop direct grants the holder's role already provides ----------
        $rolePermsByUserTeam = [];

        $assignments = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->get(['role_id', 'model_id', 'team_id']);

        $permsByRole = DB::table('role_has_permissions')
            ->get(['role_id', 'permission_id'])
            ->groupBy('role_id')
            ->map(fn ($rows) => $rows->pluck('permission_id')->all());

        foreach ($assignments as $a) {
            $key = $a->model_id . ':' . $a->team_id;
            $rolePermsByUserTeam[$key] = array_merge(
                $rolePermsByUserTeam[$key] ?? [],
                $permsByRole[$a->role_id] ?? []
            );
        }

        $deleted = 0;

        foreach ($rolePermsByUserTeam as $key => $permissionIds) {
            [$userId, $teamId] = explode(':', $key);

            $deleted += DB::table('model_has_permissions')
                ->where('model_type', User::class)
                ->where('model_id', (int) $userId)
                ->where('team_id', (int) $teamId)
                ->whereIn('permission_id', array_unique($permissionIds))
                ->delete();
        }

        logger()->info("[rbac phase 3a] dropped {$deleted} direct grants duplicated by a role");

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The dropped duplicates are NOT restored. They were redundant by definition — the
     * role still grants every one of them — so re-creating them would only reinstate the
     * bug this migration exists to remove. Dropping the table is enough to undo the part
     * that added behaviour.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_denials');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
