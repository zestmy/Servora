<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use App\Support\Navigation\NavMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The navigation panel, in both workspaces and both themes.
 *
 * NavPermissionDriftTest already guards WHAT is in the menu — that a link and
 * the door behind it name the same ability. Nothing guarded how the panel is
 * built, and four things had gone wrong underneath it:
 *
 *   - Collapsing the outlet sidebar left exactly one link on screen. Every
 *     group sat inside x-show="sidebarExpanded", so the 64px rail had no way
 *     to reach anything. The kitchen sidebar had solved this; the outlet one
 *     never got it, and nothing would have caught the difference.
 *
 *   - The light theme was twenty-two !important rules remapping dark utility
 *     classes to light hexes — a global find-and-replace on grey by class
 *     name. .text-gray-500 became #9ca3af, which is 2.54:1 on white, so the
 *     kitchen's group headers and the HR section captions failed AA in light
 *     mode. The same class was 3.67:1 for 10px text in dark mode.
 *
 *   - The panel had no landmark label, no aria-current, no skip link, and the
 *     collapsed rail's only accessible name was a title attribute.
 *
 *   - Two hand-rolled implementations that had already drifted four ways.
 */
class NavigationPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::create([
            'name' => 'Nav Co', 'slug' => Str::slug('Nav Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $outlet = Outlet::create([
            'company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $company->id, 'outlet_id' => $outlet->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$company->id]);
        $this->user->outlets()->syncWithoutDetaching([$outlet->id]);
    }

    private function dashboardHtml(): string
    {
        return $this->actingAs($this->user)->get(route('dashboard'))->assertOk()->getContent();
    }

    /**
     * The regression that prompted this. At the 64px rail the outlet sidebar
     * rendered Dashboard and nothing else — every group was inside an
     * x-show="sidebarExpanded" wrapper, so the only way to reach Purchasing
     * was to expand again.
     */
    public function test_every_group_is_reachable_from_the_collapsed_rail(): void
    {
        $html = $this->dashboardHtml();

        $labelled = 0;

        foreach (NavMenu::visible(NavMenu::outlet(), $this->user) as $group) {
            if (! $group['label']) {
                continue;
            }

            $slug = Str::slug($group['label']);

            $this->assertStringContainsString(
                "openGroupExpanded('{$slug}')",
                $html,
                "The collapsed rail has no way into the '{$group['label']}' group."
            );

            $labelled++;
        }

        // A user with no granted abilities still sees the ungated groups, so
        // this is not vacuous — but it is only a couple of groups, which is why
        // the menu's full shape is asserted separately below rather than by
        // guessing a number here.
        $this->assertGreaterThan(0, $labelled, 'No labelled groups were visible to check.');
    }

    /**
     * The rail check above runs against whatever this user can see, which for a
     * bare account is two groups. This pins the menu itself, so a group that
     * disappears from the definition fails here rather than quietly reducing
     * what the rail test covers.
     */
    public function test_the_outlet_menu_keeps_its_groups(): void
    {
        $labels = array_values(array_filter(array_column(NavMenu::outlet(), 'label')));

        $this->assertSame([
            'Procurement',
            'Inventory & Recipes',
            'Labels',
            'Sales',
            'HR',
            'Business Intelligence',
            'Settings',
        ], $labels);

        // Every labelled group needs an icon, or its rail button is a blank
        // 44px square with nothing to aim at but a screen-reader name.
        foreach (array_merge(NavMenu::outlet(), NavMenu::kitchen(), NavMenu::admin()) as $group) {
            if ($group['label']) {
                $this->assertNotEmpty($group['icon'], "Group '{$group['label']}' has no icon for the collapsed rail.");
            }
        }
    }

    /** The rail is icon-only, so each button needs a name that is not a tooltip. */
    public function test_the_collapsed_rail_buttons_are_named(): void
    {
        $html = $this->dashboardHtml();

        foreach (NavMenu::visible(NavMenu::outlet(), $this->user) as $group) {
            if (! $group['label']) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/<span class="sr-only">' . preg_quote($group['label'], '/') . '<\/span>/',
                $html,
                "The '{$group['label']}' rail button has no accessible name."
            );
        }
    }

    public function test_the_panel_carries_its_landmark_and_theme_hooks(): void
    {
        $html = $this->dashboardHtml();

        $this->assertStringContainsString('aria-label="Main navigation"', $html);
        $this->assertStringContainsString('data-workspace="outlet"', $html);
        $this->assertStringContainsString(':data-nav-theme="navTheme"', $html);
        $this->assertStringContainsString('class="skip-link"', $html);
        $this->assertStringContainsString('id="main-content"', $html);
    }

    /** The item you are standing on says so to a screen reader, not only in colour. */
    public function test_the_active_item_is_marked(): void
    {
        $this->assertStringContainsString('aria-current="page"', $this->dashboardHtml());
    }

    /** Group headers are real disclosure controls. */
    public function test_group_headers_report_their_state(): void
    {
        $html = $this->dashboardHtml();

        $this->assertStringContainsString(':aria-expanded="(activeGroup ===', $html);
        $this->assertStringContainsString('aria-controls="nav-group-', $html);
    }

    /**
     * The override sheet is gone and nothing reaches for it.
     *
     * Keeping this asserted matters more than it looks: the sheet worked by
     * enumerating utility classes, so re-introducing it would silently start
     * winning over the tokens with !important and the contrast fixes below
     * would quietly stop applying.
     */
    public function test_the_important_override_sheet_is_gone(): void
    {
        $this->assertFileDoesNotExist(base_path('resources/views/partials/nav-theme.blade.php'));

        foreach (['app', 'kitchen'] as $layout) {
            $this->assertStringNotContainsString(
                "@include('partials.nav-theme')",
                file_get_contents(base_path("resources/views/layouts/{$layout}.blade.php")),
                "layouts/{$layout} still includes the deleted override sheet."
            );
        }
    }

    /**
     * The panel names roles, not greys.
     *
     * Every hardcoded grey in the sidebar markup was a value that had to be
     * remapped by hand for the other theme, and the ones nobody remembered to
     * list stayed dark. If a grey creeps back into the component, the light
     * theme silently breaks for it again.
     */
    public function test_the_nav_component_hardcodes_no_greys(): void
    {
        $component = file_get_contents(base_path('resources/views/components/app-nav.blade.php'));

        foreach (['bg-gray-900', 'bg-gray-800', 'text-gray-300', 'text-gray-400', 'text-gray-500', 'hover:bg-gray-700', 'border-gray-700'] as $grey) {
            $this->assertStringNotContainsString(
                $grey,
                $component,
                "app-nav names '{$grey}' directly — it should use a token so the light theme follows automatically."
            );
        }
    }

    /**
     * Both workspaces render from one component.
     *
     * The drift was not hypothetical: the two had different collapse
     * behaviour, different hover colours, a different active hue and
     * different permission filters. A second <aside> is how that starts again.
     */
    public function test_both_layouts_use_the_shared_component(): void
    {
        foreach (['app', 'kitchen'] as $layout) {
            $src = file_get_contents(base_path("resources/views/layouts/{$layout}.blade.php"));

            $this->assertStringContainsString('<x-app-nav', $src, "layouts/{$layout} does not use the shared nav.");
            $this->assertStringNotContainsString('<aside', $src, "layouts/{$layout} still hand-rolls a sidebar.");
        }
    }

    /**
     * Contrast, at the point where the value is set.
     *
     * These are the two that were actually failing. The caption token must not
     * fall back to a step that cannot carry 10px text on its own surface —
     * gray-500 is 3.67:1 on gray-900 and #9ca3af is 2.54:1 on white, and both
     * were shipping.
     */
    public function test_the_caption_token_is_legible_in_both_themes(): void
    {
        $css = file_get_contents(base_path('resources/css/app.css'));

        preg_match('/\.app-nav\s*\{(.*?)\}/s', $css, $dark);
        preg_match("/\.app-nav\[data-nav-theme='light'\]\s*\{(.*?)\}/s", $css, $light);

        $this->assertNotEmpty($dark, 'No .app-nav token block found.');
        $this->assertNotEmpty($light, 'No light-theme token block found.');

        $this->assertStringContainsString("--nav-fg-caption:   theme('colors.gray.400')", $dark[1],
            'Dark captions must be gray-400 (6.99:1); gray-500 is 3.67:1 and fails at 10px.');

        $this->assertStringContainsString("--nav-fg-caption:   theme('colors.gray.600')", $light[1],
            'Light captions must be gray-600 (7.56:1); the old sheet used #9ca3af at 2.54:1.');
    }

    /**
     * Contrast is a property of a pair, so it is asserted as one.
     *
     * @dataProvider navPairs
     */
    public function test_nav_colour_pairs_meet_aa(string $what, string $fg, string $bg, float $min): void
    {
        $this->assertGreaterThanOrEqual(
            $min,
            round($this->contrast($fg, $bg), 2),
            "{$what}: {$fg} on {$bg} is below {$min}:1."
        );
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: float}> */
    public static function navPairs(): array
    {
        $gray900 = '#111827';
        $white   = '#ffffff';

        return [
            'dark item'          => ['dark item text',      '#d1d5db', $gray900, 4.5],
            'dark muted'         => ['dark group header',   '#9ca3af', $gray900, 4.5],
            'dark caption'       => ['dark section caption', '#9ca3af', $gray900, 4.5],
            'light item'         => ['light item text',     '#374151', $white,   4.5],
            'light muted'        => ['light group header',  '#4b5563', $white,   4.5],
            'light caption'      => ['light section caption', '#4b5563', $white, 4.5],
            'brand active pill'  => ['white on brand-600',  $white,    '#0b7677', 4.5],
            'kitchen active pill' => ['white on workspace-600', $white, '#7c3aed', 4.5],
        ];
    }

    private function contrast(string $a, string $b): float
    {
        $lum = function (string $hex): float {
            $hex = ltrim($hex, '#');
            $ch = array_map(
                function ($c) {
                    $c /= 255;
                    return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
                },
                [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))]
            );

            return 0.2126 * $ch[0] + 0.7152 * $ch[1] + 0.0722 * $ch[2];
        };

        $la = $lum($a);
        $lb = $lum($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }
}
