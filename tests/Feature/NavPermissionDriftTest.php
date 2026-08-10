<?php

namespace Tests\Feature;

use App\Helpers\PermissionRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A link must ask for what the door asks for.
 *
 * Every navigation surface declares, per item, the ability it will show that
 * item to. Every route declares, in `can:` middleware, the ability it will let
 * through. When those two drift apart the person gets a link they cannot use
 * and a 403 page that tells them nothing — they cannot tell a permission
 * boundary from a broken screen, and there is nothing to act on either way.
 *
 * They drifted because the permission splits changed the ROUTES and left the
 * navigation naming the abilities those modules used to need: Price Classes was
 * still offered to anyone with `recipes.view` after the route moved to
 * `recipes.price`, so every Chef in the product saw the link and every Chef who
 * clicked it got a 403.
 *
 * This reads both sidebars, the reports hub and the settings tiles out of their
 * source and compares each declared gate against the route's own. It is a drift
 * test, not a permission test: it does not care WHICH ability a screen requires,
 * only that the link and the door name the same one.
 */
class NavPermissionDriftTest extends TestCase
{
    /**
     * Pull (route name => declared gate) out of a PHP or Blade source.
     *
     * These lists are array literals inside a view or component, not config, so
     * there is nothing to require() without also running the page. Splitting on
     * the route key is enough: every item declares its route first.
     *
     * @return array<int, array{route: string, gate: ?string, any: array<int, string>}>
     */
    private function itemsIn(string $path, ?string $from = null, ?string $to = null): array
    {
        $src = file_get_contents(base_path($path));

        if ($from !== null) {
            $start = strpos($src, $from);
            $this->assertNotFalse($start, "Marker '$from' not found in $path — the test needs updating with it.");
            $end = $to !== null ? strpos($src, $to, $start) : strlen($src);
            $src = substr($src, $start, $end - $start);
        }

        $items = [];
        $chunks = preg_split("/'route'\s*=>/", $src);
        array_shift($chunks);

        foreach ($chunks as $chunk) {
            // Stop at the end of this item so a later item's keys cannot leak in.
            $close = strpos($chunk, '],');
            $chunk = $close === false ? $chunk : substr($chunk, 0, $close);

            if (! preg_match("/^\s*'([a-z0-9._-]+)'/", $chunk, $r)) {
                continue;
            }

            $gate = null;
            foreach (['permission', 'can', 'capability'] as $key) {
                if (preg_match("/'" . $key . "'\s*=>\s*'([^']+)'/", $chunk, $g)) {
                    $gate = $g[1];
                    break;
                }
            }

            $any = [];
            if (preg_match("/'anyPermission'\s*=>\s*\[([^\]]*)\]/", $chunk, $a)) {
                preg_match_all("/'([^']+)'/", $a[1], $aa);
                $any = $aa[1];
            }

            $items[] = ['route' => $r[1], 'gate' => $gate, 'any' => $any];
        }

        return $items;
    }

    /**
     * Does anything in $held satisfy $need — directly, or by implying it?
     *
     * Doing something in a module implies being able to see it, so a page that
     * demanded `inventory.stock_takes.record` can link to the stock list without
     * gating it: nobody standing on that page lacks `inventory.view`.
     */
    private function covers(array $held, string $need): bool
    {
        if (in_array($need, $held, true)) {
            return true;
        }

        return (bool) array_intersect($held, PermissionRegistry::impliedBy($need));
    }

    /** @return array<int, string> the abilities a route's own middleware demands */
    private function gatesOn(string $name): array
    {
        $route = Route::getRoutes()->getByName($name);

        if (! $route) {
            return [];
        }

        return array_values(array_map(
            fn (string $mw) => substr($mw, 4),
            array_filter(
                $route->gatherMiddleware(),
                fn ($mw) => is_string($mw) && str_starts_with($mw, 'can:')
            )
        ));
    }

    /**
     * @param  array<int, string>  $baseline  abilities every visitor to this surface
     *         already holds, because the surface's OWN route demanded them. A report
     *         listed on a hub that needs `reports.view` to open cannot 403 someone on
     *         `reports.view`, so those links declare only what they need ON TOP.
     * @return array<int, string> human-readable drift lines
     */
    private function driftIn(string $label, array $items, array $baseline = []): array
    {
        $drift = [];

        foreach ($items as $item) {
            $needs = array_values(array_filter(
                $this->gatesOn($item['route']),
                fn (string $g) => ! $this->covers($baseline, $g)
            ));

            if (! $needs) {
                continue;
            }

            foreach ($needs as $need) {
                if ($item['gate'] === $need) {
                    continue 2;
                }
                // 'anyPermission' shows the item to whoever holds ANY of them,
                // so it only covers a route gate that is itself in the list AND
                // is the only thing the route asks for.
                if (in_array($need, $item['any'], true) && count($item['any']) === 1) {
                    continue 2;
                }
            }

            $drift[] = sprintf(
                '%s: %s is shown to %s but the route requires %s',
                $label,
                $item['route'],
                $item['gate'] ?: ($item['any'] ? 'any of ' . implode('|', $item['any']) : 'everyone'),
                implode(' + ', $needs)
            );
        }

        return $drift;
    }

    public function test_the_outlet_sidebar_never_offers_a_link_that_403s(): void
    {
        $drift = $this->driftIn(
            'sidebar',
            $this->itemsIn('resources/views/layouts/app.blade.php', '$navGroups = [', '$adminNavItems = [')
        );

        $this->assertSame([], $drift, "Sidebar links that lead to a 403:\n" . implode("\n", $drift));
    }

    public function test_the_kitchen_sidebar_never_offers_a_link_that_403s(): void
    {
        $drift = $this->driftIn(
            'kitchen sidebar',
            $this->itemsIn('resources/views/layouts/kitchen.blade.php', '$kitchenNav = [', '@endphp')
        );

        $this->assertSame([], $drift, "Kitchen links that lead to a 403:\n" . implode("\n", $drift));
    }

    public function test_the_reports_hub_never_offers_a_report_that_403s(): void
    {
        $drift = $this->driftIn(
            'reports hub',
            $this->itemsIn('app/Livewire/Reports/Hub.php'),
            $this->gatesOn('reports.hub')
        );

        $this->assertSame([], $drift, "Report links that lead to a 403:\n" . implode("\n", $drift));
    }

    public function test_the_settings_tiles_never_offer_a_page_that_403s(): void
    {
        $drift = $this->driftIn(
            'settings tile',
            $this->itemsIn('app/Livewire/Settings/Index.php'),
            $this->gatesOn('settings.index')
        );

        $this->assertSame([], $drift, "Settings tiles that lead to a 403:\n" . implode("\n", $drift));
    }

    /**
     * The sidebar asks the Gate, not Spatie.
     *
     * `can:` middleware resolves through the Gate, which is where the Super Admin
     * bypass and per-user denials live. Spatie's own hasPermissionTo() skips both,
     * so a denied ability kept drawing its link and then 403'd on arrival — and it
     * throws PermissionDoesNotExist rather than returning false when an ability
     * has been retired, which would take the whole page down.
     */
    public function test_the_sidebar_resolves_permissions_through_the_gate(): void
    {
        $blade = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
        $start = strpos($blade, '$canSee = function');
        $filter = substr($blade, $start, strpos($blade, 'return true;', $start) - $start);

        $this->assertStringNotContainsString(
            '$authUser->hasPermissionTo(',
            $filter,
            'The sidebar filter must use can(), which goes through the Gate, so denials apply and a retired ability cannot throw.'
        );

        // The coloured shortcuts above the nav are not items in any array, so
        // the filter above never sees them — they get checked here instead.
        $shortcuts = substr($blade, 0, $start);

        $this->assertStringNotContainsString(
            'Auth::user()->hasPermissionTo(',
            $shortcuts,
            'The sidebar shortcuts must use can() for the same reason as the filter.'
        );
    }

    /**
     * Sites where the gate is a boolean the component computed, so the ability
     * is not spelled in the view. Each was read and confirmed by hand.
     *
     * @var array<int, string>
     */
    private const GATED_BY_A_COMPUTED_FLAG = [
        // $canViewPay, from Employee::canViewPay(), which is can('hr.compensation').
        'resources/views/livewire/hr/employees.blade.php|hr.compensation',
    ];

    /**
     * The same rule one level down: a link INSIDE a page must not lead somewhere
     * the page's own visitors cannot go.
     *
     * The page's route gates are the baseline — a "Back to Recipes" link on a
     * screen that already demanded `recipes.view` cannot refuse anyone standing
     * on it. Anything the target needs BEYOND that baseline has to be gated in
     * the view, or the person meets a 403 for clicking what they were offered.
     *
     * Only pages with a gate of their own are checked: an ungated page tells us
     * nothing about who is standing on it.
     */
    public function test_no_page_offers_a_link_it_cannot_deliver(): void
    {
        $viewOfClass = [];
        foreach ($this->phpFilesIn(app_path('Livewire')) as $file) {
            $src = file_get_contents($file);
            if (! preg_match_all("/view\(\s*'(livewire\.[a-z0-9._-]+)'/i", $src, $m)) {
                continue;
            }
            $tail = substr(str_replace(DIRECTORY_SEPARATOR, '/', $file), strlen(str_replace(DIRECTORY_SEPARATOR, '/', app_path('Livewire')) . '/'), -4);
            $cls = 'App' . chr(92) . 'Livewire' . chr(92) . str_replace('/', chr(92), $tail);
            $viewOfClass[$cls] = ['views' => array_unique($m[1]), 'src' => $src];
        }

        $baselineOfView = [];
        $sourceOfView   = [];
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            $cls = str_contains($action, '@') ? explode('@', $action)[0] : $action;
            if (! isset($viewOfClass[$cls])) {
                continue;
            }
            foreach ($viewOfClass[$cls]['views'] as $view) {
                $baselineOfView[$view] = array_unique(array_merge(
                    $baselineOfView[$view] ?? [],
                    $route->getName() ? $this->gatesOn($route->getName()) : []
                ));
                $sourceOfView[$view] = ($sourceOfView[$view] ?? '') . $viewOfClass[$cls]['src'];
            }
        }

        $drift = [];
        foreach ($this->bladeFilesIn(resource_path('views/livewire')) as $file) {
            $src = file_get_contents($file);
            $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen(base_path()) + 1));
            $view = str_replace('/', '.', substr($rel, strlen('resources/views/'), -strlen('.blade.php')));

            $baseline = $baselineOfView[$view] ?? [];
            if (! $baseline) {
                continue;
            }

            foreach (explode("\n", $src) as $n => $line) {
                if (! preg_match_all("/route\(\s*'([a-z0-9._-]+)'/i", $line, $m)) {
                    continue;
                }
                foreach ($m[1] as $target) {
                    foreach ($this->gatesOn($target) as $need) {
                        if ($this->covers($baseline, $need)) { continue; }
                        // Named in the view, or in the component that renders it,
                        // means someone gated it — a stricter check would have to
                        // understand Blade nesting, which is not worth the recall.
                        if (str_contains($src, "'" . $need . "'")) { continue; }
                        if (str_contains($sourceOfView[$view] ?? '', "'" . $need . "'")) { continue; }
                        if (in_array($rel . '|' . $need, self::GATED_BY_A_COMPUTED_FLAG, true)) { continue; }

                        $drift[$rel . '|' . $target . '|' . $need] = sprintf(
                            '%s:%d links to %s, which needs %s — the page only requires %s',
                            $rel, $n + 1, $target, $need, implode('+', $baseline)
                        );
                    }
                }
            }
        }

        // The backlog is a fixture, not silence: the assertion is that it never
        // grows. Every entry was measured against production and bites nobody
        // today; the file's own header explains the two ways to clear one.
        $known = array_values(array_filter(
            array_map('trim', file(base_path('tests/Fixtures/known-inpage-link-drift.txt'), FILE_IGNORE_NEW_LINES)),
            fn (string $l) => $l !== '' && ! str_starts_with($l, '#')
        ));

        $new = array_values(array_diff_key($drift, array_flip($known)));

        $this->assertSame([], $new, "New in-page links that lead to a 403:\n" . implode("\n", $new));
    }

    /** @return array<int, string> */
    private function phpFilesIn(string $dir): array
    {
        return $this->filesIn($dir, '.php');
    }

    /** @return array<int, string> */
    private function bladeFilesIn(string $dir): array
    {
        return $this->filesIn($dir, '.blade.php');
    }

    /** @return array<int, string> */
    private function filesIn(string $dir, string $ext): array
    {
        $out = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), $ext)) {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }
}
