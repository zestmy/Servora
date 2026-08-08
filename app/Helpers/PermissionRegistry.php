<?php

namespace App\Helpers;

/**
 * Read access to the permission registry in config/permissions.php.
 *
 * Everything that needs to know what abilities exist goes through here rather than
 * reaching into config directly, so the normalisation (computed titles, the
 * capability-managed and unenforced exceptions) happens in exactly one place.
 *
 * Results are memoised per request. The registry is a config file, so it cannot
 * change mid-request — and config is cached in production anyway.
 */
class PermissionRegistry
{
    /** @var array<string, mixed>|null */
    private static ?array $memo = null;

    /**
     * Every module, normalised: each ability gains its computed `title`, plus the
     * `module` and `ability` keys it is filed under.
     *
     * Title rules reproduce the pre-registry wording exactly:
     *   explicit 'title'      → used as-is
     *   single-ability module → the module label ("Ingredients")
     *   multi-ability module  → "Module (Ability)" ("Duty Roster (Create)")
     *
     * @return array<string, array{label: string, group: string, abilities: array<string, array<string, mixed>>}>
     */
    public static function modules(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $modules = [];

        foreach ((array) config('permissions.modules', []) as $moduleKey => $module) {
            $abilities = (array) ($module['abilities'] ?? []);
            $isSingle  = count($abilities) === 1;

            foreach ($abilities as $abilityKey => $ability) {
                $abilities[$abilityKey] = $ability + [
                    'title'   => $ability['title'] ?? ($isSingle
                        ? $module['label']
                        : $module['label'] . ' (' . $ability['label'] . ')'),
                    'module'  => $moduleKey,
                    'ability' => $abilityKey,
                ];
            }

            $modules[$moduleKey] = ['abilities' => $abilities] + $module;
        }

        return self::$memo = $modules;
    }

    /** Forget the memo. Tests that swap the config need this; nothing else should. */
    public static function flush(): void
    {
        self::$memo = null;
    }

    /**
     * Every ability, flattened and keyed by permission name.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function abilities(): array
    {
        $flat = [];

        foreach (self::modules() as $module) {
            foreach ($module['abilities'] as $ability) {
                $flat[$ability['name']] = $ability;
            }
        }

        return $flat;
    }

    /** Every permission name the registry declares. @return list<string> */
    public static function names(): array
    {
        return array_keys(self::abilities());
    }

    /** Is this permission name declared in the registry? */
    public static function has(string $name): bool
    {
        return isset(self::abilities()[$name]);
    }

    /**
     * Can an admin tick this ability in the module grid?
     *
     * Two kinds of ability are real but not grantable here:
     *   managed_by  another control already grants it (`users.manage` rides on the
     *               "Can manage users" capability), and two controls writing one
     *               permission is how they end up disagreeing.
     *   enforced:false  it gates nothing, so a checkbox for it would be a lie. It stays
     *               in the registry so `permissions:sync` does not prune it and existing
     *               grants survive — Role Templates preserves anything outside this set
     *               untouched, which is exactly the handling `roster.view` needs.
     */
    private static function isGrantable(array $ability): bool
    {
        return ! isset($ability['managed_by']) && ($ability['enforced'] ?? true) !== false;
    }

    /**
     * name => full title, for badges and flat lists.
     *
     * This is the shape `Settings\Users::MODULES` used to hold — same exclusions, plus
     * the nine abilities that const had fallen behind — which is why the two admin
     * screens could adopt the registry without their Blade templates changing shape.
     *
     * @return array<string, string>
     */
    public static function titles(bool $all = false): array
    {
        $titles = [];

        foreach (self::abilities() as $name => $ability) {
            if (! $all && ! self::isGrantable($ability)) {
                continue;
            }
            $titles[$name] = $ability['title'];
        }

        return $titles;
    }

    /**
     * The grid an admin actually ticks: modules grouped by group label, with
     * capability-managed abilities removed and empty modules dropped.
     *
     * @return array<string, array<string, array{label: string, single: bool, abilities: array<string, array<string, mixed>>}>>
     */
    public static function grid(): array
    {
        $groups = (array) config('permissions.groups', []);
        $grid   = array_fill_keys(array_values($groups), []);

        foreach (self::modules() as $moduleKey => $module) {
            $abilities = array_filter($module['abilities'], self::isGrantable(...));

            if ($abilities === []) {
                continue;
            }

            $groupLabel = $groups[$module['group']] ?? $module['group'];

            $grid[$groupLabel][$moduleKey] = [
                'label'     => $module['label'],
                'single'    => count($abilities) === 1,
                'abilities' => $abilities,
            ];
        }

        return array_filter($grid);
    }

    /**
     * Abilities declared as gating nothing today. Each must carry a note saying
     * why; the drift test allows these and only these.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function unenforced(): array
    {
        return array_filter(
            self::abilities(),
            fn (array $a) => ($a['enforced'] ?? true) === false
        );
    }

    /**
     * Abilities granted through a capability flag rather than the module grid.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function capabilityManaged(): array
    {
        return array_filter(
            self::abilities(),
            fn (array $a) => isset($a['managed_by'])
        );
    }
}
