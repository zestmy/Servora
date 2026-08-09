<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spatie teams mode: role/permission assignments become per-company.
 * team_id = companies.id. Role DEFINITIONS stay global (roles.team_id NULL);
 * only the assignment pivots are company-scoped.
 *
 * Unlike Spatie's stock teams schema (NOT NULL team + composite PK), the pivot
 * team_id here is NULLABLE with a unique index: system accounts without a
 * company keep team-NULL assignment rows, and User::isSystemRole() checks the
 * pivot team-agnostically.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── roles: allow (future) team-scoped role definitions ──
        //
        // Every step here is conditional, because `permission.teams` is now TRUE and
        // Spatie's own create_permission_tables migration already builds most of this
        // shape on a fresh database. Unconditionally adding `team_id` made this migration
        // fail with "duplicate column name" on ANY fresh install — MySQL as well as the
        // SQLite connection the tests use. Production never hit it because its database
        // predates the teams flip and this migration has already run there.
        if (! Schema::hasColumn('roles', 'team_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable()->after('id');
                $table->index('team_id', 'roles_team_foreign_key_index');
            });
        }

        if (Schema::hasIndex('roles', 'roles_name_guard_name_unique')) {
            Schema::table('roles', fn (Blueprint $table) => $table->dropUnique('roles_name_guard_name_unique'));
        }

        if (! Schema::hasIndex('roles', 'roles_team_name_guard_unique')) {
            Schema::table('roles', fn (Blueprint $table) =>
                $table->unique(['team_id', 'name', 'guard_name'], 'roles_team_name_guard_unique'));
        }

        // ── assignment pivots ──
        foreach ([
            ['table' => 'model_has_roles', 'pivot' => 'role_id', 'references' => 'roles'],
            ['table' => 'model_has_permissions', 'pivot' => 'permission_id', 'references' => 'permissions'],
        ] as $cfg) {
            $tableName = $cfg['table'];
            $pivot     = $cfg['pivot'];

            if (Schema::hasColumn($tableName, 'team_id')) {
                // Fresh database: Spatie built the teams shape itself, but with team_id
                // NOT NULL and inside the primary key. This app needs it NULLABLE — system
                // accounts hold team-NULL assignment rows, which isSystemRole() reads
                // team-agnostically — and a primary key cannot contain a nullable column.
                //
                // Dropping a primary key is not something SQLite can do in place, so the
                // table is rebuilt into the target shape instead. Only ever reached on a
                // fresh install, and refused outright if there is data to lose.
                if (DB::table($tableName)->exists()) {
                    throw new RuntimeException(
                        "Refusing to rebuild {$tableName}: it already holds rows. This branch is "
                        . 'only meant for a fresh database where Spatie created the teams shape.'
                    );
                }

                Schema::drop($tableName);
                Schema::create($tableName, function (Blueprint $table) use ($tableName, $pivot, $cfg) {
                    $table->unsignedBigInteger($pivot);
                    $table->unsignedBigInteger('team_id')->nullable();
                    $table->string('model_type');
                    $table->unsignedBigInteger('model_id');

                    $table->index(['model_id', 'model_type'], $tableName . '_model_id_model_type_index');
                    $table->index('team_id', $tableName . '_team_foreign_key_index');
                    $table->index($pivot, $tableName . '_' . $pivot . '_index');
                    $table->unique(['team_id', $pivot, 'model_id', 'model_type'], $tableName . '_team_' . $pivot . '_model_unique');

                    $table->foreign($pivot)->references('id')->on($cfg['references'])->cascadeOnDelete();
                });

                continue; // nothing to backfill on a fresh database
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $pivot) {
                $table->unsignedBigInteger('team_id')->nullable()->after($pivot);
                $table->index('team_id', $tableName . '_team_foreign_key_index');
                // The FK on {$pivot} currently rides on the composite PK; give it
                // its own index so the PK can be dropped.
                $table->index($pivot, $tableName . '_' . $pivot . '_index');
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropPrimary();
            });

            // Backfill: one assignment row per company membership, so every
            // company a user could already act in keeps today's effective
            // access (permissions were account-global before this migration).
            DB::table($tableName)->insertUsing(
                [$pivot, 'model_id', 'model_type', 'team_id'],
                DB::table($tableName . ' as src')
                    ->join('company_user as cu', 'cu.user_id', '=', 'src.model_id')
                    ->where('src.model_type', \App\Models\User::class)
                    ->whereNull('src.team_id')
                    ->select('src.' . $pivot, 'src.model_id', 'src.model_type', 'cu.company_id')
            );

            // The old global rows are superseded for users that have at least
            // one membership. Users with none (system accounts without a
            // company) keep their team-NULL rows.
            DB::table($tableName)
                ->whereNull('team_id')
                ->where('model_type', \App\Models\User::class)
                ->whereIn('model_id', DB::table('company_user')->select('user_id'))
                ->delete();

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $pivot) {
                $table->unique(['team_id', $pivot, 'model_id', 'model_type'], $tableName . '_team_' . $pivot . '_model_unique');
            });
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ([
            ['table' => 'model_has_roles', 'pivot' => 'role_id', 'primary' => 'model_has_roles_role_model_type_primary'],
            ['table' => 'model_has_permissions', 'pivot' => 'permission_id', 'primary' => 'model_has_permissions_permission_model_type_primary'],
        ] as $cfg) {
            $tableName = $cfg['table'];
            $pivot     = $cfg['pivot'];

            // Collapse per-company rows back to one global row each
            DB::statement(
                "DELETE t1 FROM {$tableName} t1
                 INNER JOIN {$tableName} t2
                   ON t1.{$pivot} = t2.{$pivot}
                  AND t1.model_id = t2.model_id
                  AND t1.model_type = t2.model_type
                  AND (t1.team_id > t2.team_id OR (t2.team_id IS NULL AND t1.team_id IS NOT NULL))"
            );

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $pivot, $cfg) {
                $table->dropUnique($tableName . '_team_' . $pivot . '_model_unique');
                $table->dropIndex($tableName . '_team_foreign_key_index');
                $table->dropColumn('team_id');
                $table->primary([$pivot, 'model_id', 'model_type'], $cfg['primary']);
            });
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_team_name_guard_unique');
            $table->dropIndex('roles_team_foreign_key_index');
            $table->dropColumn('team_id');
            $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
        });

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
