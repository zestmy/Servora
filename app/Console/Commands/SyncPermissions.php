<?php

namespace App\Console\Commands;

use App\Helpers\PermissionRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reconcile the `permissions` table with config/permissions.php.
 *
 * Creating a permission is safe and happens by default. Deleting one is not — it
 * cascades through role_has_permissions and model_has_permissions, silently revoking
 * access — so orphans are only ever reported unless --prune is passed explicitly, in
 * the same spirit as audit:prune.
 */
class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync
        {--prune : Also delete permissions that exist in the table but not in the registry}
        {--dry-run : Show what would change without writing anything}
        {--force : Skip the confirmation prompt when pruning}';

    protected $description = 'Create any permissions the registry declares but the database is missing.';

    public function handle(): int
    {
        $registry = PermissionRegistry::names();
        $existing = DB::table('permissions')->where('guard_name', 'web')->pluck('name')->all();

        $missing = array_values(array_diff($registry, $existing));
        $orphans = array_values(array_diff($existing, $registry));

        $dryRun = (bool) $this->option('dry-run');

        if ($missing === [] && $orphans === []) {
            $this->info('Permissions are in sync — ' . count($registry) . ' abilities, nothing to do.');
            return self::SUCCESS;
        }

        if ($missing !== []) {
            $this->line('');
            $this->info('Missing from the database (' . count($missing) . '):');
            foreach ($missing as $name) {
                $this->line('  + ' . $name);
            }

            if (! $dryRun) {
                $now = now();
                DB::table('permissions')->insert(array_map(fn (string $name) => [
                    'name'       => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $missing));

                $this->info('Created ' . count($missing) . ' permission(s).');
            }
        }

        if ($orphans !== []) {
            $this->line('');
            $this->warn('In the database but not in the registry (' . count($orphans) . '):');

            foreach ($orphans as $name) {
                $roles = DB::table('role_has_permissions')
                    ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                    ->where('permissions.name', $name)->count();
                $users = DB::table('model_has_permissions')
                    ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
                    ->where('permissions.name', $name)->count();

                $this->line("  - {$name}  (held by {$roles} role(s), {$users} user grant(s))");
            }

            if (! $this->option('prune')) {
                $this->line('');
                $this->line('Left alone. Either add them to config/permissions.php, or re-run with --prune to delete.');
            } elseif (! $dryRun) {
                $confirmed = $this->option('force') || $this->confirm(
                    'Delete ' . count($orphans) . ' permission(s)? This revokes them from every role and user.'
                );

                if ($confirmed) {
                    $ids = DB::table('permissions')->whereIn('name', $orphans)
                        ->where('guard_name', 'web')->pluck('id');

                    DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
                    DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
                    DB::table('permissions')->whereIn('id', $ids)->delete();

                    $this->info('Pruned ' . count($orphans) . ' permission(s).');
                } else {
                    $this->info('Prune aborted — nothing deleted.');
                }
            }
        }

        if ($dryRun) {
            $this->line('');
            $this->info('Dry run — nothing was written.');
            return self::SUCCESS;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return self::SUCCESS;
    }
}
