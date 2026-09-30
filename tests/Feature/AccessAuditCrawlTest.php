<?php

namespace Tests\Feature;

use App\Helpers\PermissionRegistry;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Every link a user is shown must open for that user.
 *
 * NavPermissionDriftTest compares the gate a link DECLARES with the gate its
 * route DECLARES. That misses everything that happens after the route lets the
 * request in: a mount() that authorizes a second ability, a page that needs two
 * permissions where the link checks one, a tile on a hub page, a button. This
 * test does not read declarations at all. It logs in as a user holding exactly
 * one permission (and then as each preset role), renders the pages that carry
 * navigation, collects every in-app link on them, opens each one, and fails on
 * any 403.
 *
 * Only 403 counts: several reports use MySQL-only SQL (YEAR(), DATE_FORMAT)
 * that the SQLite test database cannot run, so their 500s here are not real.
 *
 * Opt-in (ACCESS_AUDIT=1): it opens every linked page for every permission,
 * which takes over half an hour — too slow for every run, right for a release
 * that changes permissions, routes or navigation.
 *
 * @group access-audit
 */
class AccessAuditCrawlTest extends TestCase
{
    use RefreshDatabase;

    /** Pages whose links are the navigation: the sidebar, and the two hubs. */
    private const HUBS = ['/dashboard', '/settings', '/reports'];

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        if (! env('ACCESS_AUDIT')) {
            $this->markTestSkipped('Opt-in: set ACCESS_AUDIT=1 to crawl every link for every permission.');
        }

        $this->company = Company::create([
            'name' => 'Audit Co', 'slug' => Str::slug('Audit Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        setPermissionsTeamId($this->company->id);
    }

    private function userWith(array $permissions = [], ?Role $role = null): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach ($permissions as $p) {
            $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        if ($role) {
            $user->assignRole($role);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /** In-app GET links on a page, as paths with query. */
    private function linksOn(string $html): array
    {
        preg_match_all('/href="([^"#]+)"/', $html, $m);
        $base = rtrim(config('app.url'), '/');

        return collect($m[1])
            ->map(fn ($h) => html_entity_decode($h))
            ->map(fn ($h) => str_starts_with($h, $base) ? substr($h, strlen($base)) : $h)
            ->filter(fn ($h) => str_starts_with($h, '/') && ! str_starts_with($h, '//'))
            // Not pages: assets, logout, downloads that stream files, external handoffs.
            ->reject(fn ($h) => preg_match('#^/(build|storage|vendor|livewire|logout|favicon|images|downloads)#', $h))
            ->reject(fn ($h) => preg_match('#(pdf|excel|xlsx|csv|export|download)#i', $h))
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, string> "status path (from hub)" for every link that fails */
    private function crawl(User $user): array
    {
        $failures = [];
        $seen = [];

        foreach (self::HUBS as $hub) {
            $response = $this->actingAs($user)->get($hub);
            if ($response->status() !== 200) {
                continue;   // a hub the user cannot open offers nothing to follow
            }

            foreach ($this->linksOn($response->getContent()) as $link) {
                if (isset($seen[$link])) {
                    continue;
                }
                $seen[$link] = true;

                $status = $this->actingAs($user)->get($link)->status();
                if ($status === 403) {
                    $failures[] = "{$status} {$link} (linked from {$hub})";
                }
            }
        }

        return $failures;
    }

    public function test_every_link_opens_for_a_user_holding_one_permission(): void
    {
        $report = [];

        foreach (PermissionRegistry::names() as $permission) {
            $failures = $this->crawl($this->userWith([$permission]));
            if ($failures) {
                $report[] = "[{$permission}]\n  " . implode("\n  ", $failures);
            }
        }

        $this->assertSame([], $report, "Links shown that do not open:\n" . implode("\n", $report));
    }

    /**
     * Real permission sets, one per line as "count<TAB>perm perm ...", from
     * ACCESS_AUDIT_PERMSETS. Not committed: it is a snapshot of a tenant's
     * grants, used to audit against what people actually hold.
     */
    public function test_every_link_opens_for_supplied_permission_sets(): void
    {
        $path = env('ACCESS_AUDIT_PERMSETS');
        if (! $path || ! is_file($path)) {
            $this->markTestSkipped('Set ACCESS_AUDIT_PERMSETS to a permission-set file to run this.');
        }

        $report = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $i => $line) {
            [$count, $perms] = array_pad(explode("\t", $line, 2), 2, '');
            $perms = array_values(array_filter(explode(' ', trim($perms)), fn ($p) => PermissionRegistry::has($p)));

            $failures = $this->crawl($this->userWith($perms));
            if ($failures) {
                $report[] = "[set #" . ($i + 1) . ", {$count} user(s), " . count($perms) . " perms]\n  " . implode("\n  ", $failures);
            }
        }

        $this->assertSame([], $report, "Links shown that do not open:\n" . implode("\n", $report));
    }

    public function test_every_link_opens_for_each_preset_role(): void
    {
        $report = [];

        foreach (array_keys(\App\Livewire\Settings\Users::ASSIGNABLE_ROLES) as $name) {
            $role = Role::whereNull('team_id')->where('name', $name)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            $failures = $this->crawl($this->userWith([], $role));
            if ($failures) {
                $report[] = "[role: {$name}]\n  " . implode("\n  ", $failures);
            }
        }

        $this->assertSame([], $report, "Links shown that do not open:\n" . implode("\n", $report));
    }
}
