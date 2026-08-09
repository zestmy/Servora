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
        // User::canDo() — a permission check system roles always pass. See the method
        // docblock for why it exists rather than plain can().
        "/canDo\(\s*'([a-z][a-z_]*(?:\.[a-z_]+)+)'/",
        // Nav arrays: 'permission' => 'labels.print', 'capability' => 'users.manage'
        "/'permission'\s*=>\s*'([a-z][a-z_]*(?:\.[a-z_]+)+)'/",
        "/'capability'\s*=>\s*'([a-z][a-z_]*(?:\.[a-z_]+)+)'/",
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

    /**
     * Both kinds of exception to the drift check must say why they exist, or they are
     * indistinguishable from a mistake. Asserted together so the test still carries an
     * assertion when one of the two sets is empty — `capabilityManaged()` emptied out in
     * Phase 1 when the capability flags became ordinary permissions.
     */
    public function test_declared_exceptions_explain_themselves(): void
    {
        $exceptions = PermissionRegistry::unenforced() + PermissionRegistry::capabilityManaged();

        $undocumented = array_keys(array_filter(
            $exceptions,
            fn (array $a) => empty($a['note'])
        ));

        $this->assertSame([], $undocumented, $undocumented === [] ? '' : sprintf(
            "These registry entries opt out of the drift check but carry no note explaining why:\n  %s",
            implode("\n  ", $undocumented)
        ));

        $this->assertNotEmpty(
            $exceptions,
            'No declared exceptions at all — if that is now true, delete the exception '
            . 'mechanism from PermissionRegistry rather than leaving an unused escape hatch.'
        );
    }

    /**
     * Permission names handed to givePermissionTo() must exist.
     *
     * Enforcement and granting are different verbs, so this cannot ride on the patterns
     * above — a granted-but-unenforced permission would then pass the dead-permission
     * check. It needs its own test because the failure is nasty and silent until it is
     * not: Spatie throws PermissionDoesNotExist, and these calls sit in company creation
     * and onboarding, so the first person to hit it is a new customer signing up.
     *
     * Phase 4b retired `inventory.delete` and three of these arrays still granted it.
     */
    public function test_every_granted_permission_name_exists(): void
    {
        $unknown = [];

        foreach ($this->sourceFiles() as $relative => $path) {
            $contents = file_get_contents($path);

            // givePermissionTo([...]) / syncPermissions([...]), including multi-line arrays.
            if (! preg_match_all('/(?:givePermissionTo|syncPermissions)\(\s*\[(.*?)\]\s*\)/s', $contents, $blocks)) {
                continue;
            }

            foreach ($blocks[1] as $block) {
                preg_match_all("/'([a-z][a-z_]*(?:\.[a-z_]+)+)'/", $block, $names);
                foreach ($names[1] as $name) {
                    if (! PermissionRegistry::has($name)) {
                        $unknown[$name][] = $relative;
                    }
                }
            }
        }

        $unknown = array_map(fn (array $f) => array_values(array_unique($f)), $unknown);

        $this->assertSame([], $unknown, $unknown === [] ? '' : sprintf(
            "These permission names are granted in code but are not in the registry, so the "
            . "grant will throw PermissionDoesNotExist at runtime:\n%s",
            collect($unknown)->map(fn ($f, $n) => "  {$n}\n    " . implode("\n    ", $f))->implode("\n")
        ));
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
