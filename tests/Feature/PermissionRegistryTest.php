<?php

namespace Tests\Feature;

use App\Helpers\PermissionRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * The guardrail behind the permission registry.
 *
 * The bug this exists to prevent: `hr.payroll` and nine other permissions were
 * enforced in routes and controllers but absent from the 23-entry const that drove
 * both admin screens, so nobody could grant or revoke them without writing a
 * migration. Nothing failed — the two lists just drifted apart quietly over five
 * months.
 *
 * These tests close that loop in both directions:
 *   - enforce something the registry does not declare  → fail
 *   - declare something nothing enforces               → fail (unless declared unenforced, with a note)
 *
 * No database: the registry is a config file and enforcement sites are source text,
 * so this is pure static analysis and runs in milliseconds.
 */
class PermissionRegistryTest extends TestCase
{
    /**
     * Files that talk *about* permissions rather than enforcing them. Scanning these
     * would make every test tautological.
     *
     * @var list<string>
     */
    private const EXCLUDED = [
        'config/permissions.php',
        'app/Helpers/PermissionRegistry.php',
        'app/Console/Commands/SyncPermissions.php',
        'tests/Feature/PermissionRegistryTest.php',
    ];

    /**
     * How a permission is actually enforced in this codebase. Each pattern captures
     * the permission name in group 1.
     *
     * Every name in the registry contains a dot, and the patterns require one — which
     * is what keeps policy-style checks such as `$user->can('update', $post)` out of
     * the results without needing to know the model list.
     *
     * @var list<string>
     */
    private const PATTERNS = [
        // Route middleware: ->middleware('can:sales.view') / ['can:sales.view']
        "/can:([a-z][a-z_]*(?:\.[a-z_]+)+)/",
        // Blade: @can('audit.view')
        "/@can\(\s*'([a-z][a-z_]*(?:\.[a-z_]+)+)'/",
        // PHP: ->can('hr.payroll'), hasPermissionTo('settings.view'), hasAnyPermission([...])
        "/(?:->|\b)can\(\s*'([a-z][a-z_]*(?:\.[a-z_]+)+)'\s*[,)]/",
        "/hasPermissionTo\(\s*'([a-z][a-z_]*(?:\.[a-z_]+)+)'/",
        // Nav arrays: 'permission' => 'labels.print'
        "/'permission'\s*=>\s*'([a-z][a-z_]*(?:\.[a-z_]+)+)'/",
        // Settings tiles: 'can' => 'hr.leave.approve'
        "/'can'\s*=>\s*'([a-z][a-z_]*(?:\.[a-z_]+)+)'/",
    ];

    /** @return array<string, list<string>> permission name => files that enforce it */
    private function enforcementSites(): array
    {
        $sites = [];

        foreach ($this->sourceFiles() as $relative => $path) {
            $contents = file_get_contents($path);

            foreach (self::PATTERNS as $pattern) {
                if (! preg_match_all($pattern, $contents, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $name) {
                    $sites[$name][] = $relative;
                    $sites[$name]   = array_values(array_unique($sites[$name]));
                }
            }
        }

        return $sites;
    }

    /** @return array<string, string> relative path => absolute path */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (['app', 'routes', 'resources/views'] as $dir) {
            $base     = base_path($dir);
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                if (! in_array($file->getExtension(), ['php'], true)) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));

                if (in_array($relative, self::EXCLUDED, true)) {
                    continue;
                }

                $files[$relative] = $file->getPathname();
            }
        }

        return $files;
    }

    public function test_every_enforced_permission_is_declared_in_the_registry(): void
    {
        $undeclared = [];

        foreach ($this->enforcementSites() as $name => $files) {
            if (! PermissionRegistry::has($name)) {
                $undeclared[$name] = $files;
            }
        }

        $this->assertSame([], $undeclared, $undeclared === [] ? '' : sprintf(
            "These permissions are enforced but not declared in config/permissions.php, so no admin "
            . "screen can grant or revoke them:\n%s\nAdd them to the registry, then run `php artisan permissions:sync`.",
            collect($undeclared)->map(fn ($f, $n) => "  {$n}\n    " . implode("\n    ", $f))->implode("\n")
        ));
    }

    public function test_every_declared_permission_is_enforced_somewhere(): void
    {
        $sites      = $this->enforcementSites();
        $unenforced = PermissionRegistry::unenforced();
        $managed    = PermissionRegistry::capabilityManaged();

        $dead = [];

        foreach (PermissionRegistry::abilities() as $name => $ability) {
            if (isset($sites[$name]) || isset($unenforced[$name]) || isset($managed[$name])) {
                continue;
            }

            $dead[] = $name;
        }

        $this->assertSame([], $dead, $dead === [] ? '' : sprintf(
            "These permissions are declared in config/permissions.php but gate nothing:\n  %s\n"
            . "Either enforce them, delete them, or mark them 'enforced' => false with a note explaining why.",
            implode("\n  ", $dead)
        ));
    }

    public function test_permissions_declared_unenforced_explain_themselves(): void
    {
        foreach (PermissionRegistry::unenforced() as $name => $ability) {
            $this->assertNotEmpty(
                $ability['note'] ?? '',
                "{$name} is declared 'enforced' => false but carries no note. An exception to the "
                . 'drift check has to say why it exists, or it is indistinguishable from a mistake.'
            );
        }
    }

    public function test_capability_managed_permissions_explain_themselves(): void
    {
        foreach (PermissionRegistry::capabilityManaged() as $name => $ability) {
            $this->assertNotEmpty(
                $ability['note'] ?? '',
                "{$name} is declared 'managed_by' => '{$ability['managed_by']}' but carries no note "
                . 'explaining which control grants it.'
            );
        }
    }

    public function test_permission_names_are_unique_across_modules(): void
    {
        $seen = [];

        foreach (PermissionRegistry::modules() as $moduleKey => $module) {
            foreach ($module['abilities'] as $abilityKey => $ability) {
                $seen[$ability['name']][] = "{$moduleKey}.{$abilityKey}";
            }
        }

        $duplicated = array_filter($seen, fn (array $where) => count($where) > 1);

        $this->assertSame([], $duplicated, $duplicated === [] ? '' : sprintf(
            "The same permission name is declared by more than one module/ability:\n%s",
            collect($duplicated)->map(fn ($w, $n) => "  {$n} <- " . implode(', ', $w))->implode("\n")
        ));
    }

    public function test_every_module_declares_a_known_group(): void
    {
        $groups = array_keys((array) config('permissions.groups'));

        foreach (PermissionRegistry::modules() as $moduleKey => $module) {
            $this->assertContains(
                $module['group'],
                $groups,
                "Module '{$moduleKey}' is filed under group '{$module['group']}', which is not "
                . 'declared in the registry groups list.'
            );
        }
    }
}
