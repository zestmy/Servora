<?php

namespace App\Support\Navigation;

use App\Models\User;
use App\Services\SubscriptionService;

/**
 * What is in the sidebar, for each workspace.
 *
 * These lists were array literals inside two Blade layouts — `$navGroups` and
 * `$adminNavItems` in layouts/app.blade.php, `$kitchenNav` in
 * layouts/kitchen.blade.php — each with its own copy of the group-icon map and
 * its own idea of how to filter by permission. NavPermissionDriftTest had to
 * regex-parse the Blade source to read them, and its own comment says why:
 * "these lists are array literals inside a view or component, not config, so
 * there is nothing to require() without also running the page."
 *
 * They are config now. The drift test reads them by calling this class, which
 * means it sees the real structure rather than whatever a regex could recover
 * from source text — and the two workspaces can no longer disagree about how
 * an item is gated, because there is one filter.
 *
 * Item keys, all optional except route and label:
 *
 *   route          route name
 *   label          the words on the link
 *   query          querystring appended to the href, e.g. 'tab=prep-items'
 *   permission     a single ability, resolved through the GATE (see visible())
 *   anyPermission  shown when the user holds ANY of them
 *   capability     a canDo() capability rather than a permission
 *   feature        a subscription feature the company must have
 *   kitchenOnly    only for users attached to a central kitchen
 *   section        sub-heading within a group; must be contiguous
 *   icon           <x-icon> name, for the ungrouped top-level items
 */
final class NavMenu
{
    /** @return array<int, array{label: ?string, icon: ?string, items: array}> */
    public static function for(string $workspace): array
    {
        return match ($workspace) {
            'kitchen' => self::kitchen(),
            default   => self::outlet(),
        };
    }

    /**
     * Filter a workspace's groups down to what this user may actually open.
     *
     * One implementation for both sidebars. The outlet layout had this as a
     * `$canSee` closure and the kitchen layout had a bare inline
     * `auth()->user()?->can($item['permission'])` that understood none of
     * capability, anyPermission, feature or kitchenOnly — so a kitchen item
     * could not be gated on a subscription feature even though the key
     * existed, and nobody would have found out until one needed to be.
     *
     * Groups left with no visible items are dropped, so an empty header can
     * never render.
     *
     * @return array<int, array{label: ?string, icon: ?string, items: array}>
     */
    public static function visible(array $groups, User $user): array
    {
        $out = [];

        foreach ($groups as $group) {
            $items = array_values(array_filter(
                $group['items'],
                fn (array $item) => self::allows($item, $user)
            ));

            if ($items !== []) {
                $out[] = ['label' => $group['label'], 'icon' => $group['icon'] ?? null, 'items' => $items];
            }
        }

        return $out;
    }

    /**
     * A system role sees only the dashboard from the business nav — the rest
     * of the product is a tenant's, not the platform's. Their own tools come
     * from admin() below.
     */
    public static function visibleForSystemRole(array $groups): array
    {
        $out = [];

        foreach ($groups as $group) {
            $items = array_values(array_filter(
                $group['items'],
                fn (array $item) => ($item['route'] ?? null) === 'dashboard'
            ));

            if ($items !== []) {
                $out[] = ['label' => $group['label'], 'icon' => $group['icon'] ?? null, 'items' => $items];
            }
        }

        return $out;
    }

    private static function allows(array $item, User $user): bool
    {
        if (! empty($item['capability']) && ! $user->canDo($item['capability'])) {
            return false;
        }

        /*
         * can(), not hasPermissionTo(): `can:` middleware on the route goes
         * through the Gate, so denials and the Super Admin bypass apply.
         * Spatie's own lookup skips both — a denied ability still drew its
         * link and 403'd on arrival — and throws on a permission that has
         * since been retired, which would take the whole page down.
         */
        if (($item['permission'] ?? null) !== null && ! $user->can($item['permission'])) {
            return false;
        }

        /*
         * 'anyPermission': shown when the user holds ANY of them. The
         * per-module Settings links need this — a module's settings are worth
         * offering to whoever can open any one of the screens inside them, not
         * only to settings.view.
         */
        if (! empty($item['anyPermission'])
            && ! collect($item['anyPermission'])->contains(fn ($p) => $user->can($p))) {
            return false;
        }

        if (! empty($item['feature']) && $user->company) {
            if (! app(SubscriptionService::class)->canUseFeature($user->company, $item['feature'])) {
                return false;
            }
        }

        if (! empty($item['kitchenOnly']) && ! $user->isKitchenUser()) {
            return false;
        }

        return true;
    }

    // ── Outlet ─────────────────────────────────────────────────────────────

    public static function outlet(): array
    {
        return [
            [
                'label' => null, // No header for the top-level entry
                'icon'  => null,
                'items' => [
                    ['route' => 'dashboard', 'icon' => 'home', 'label' => 'Dashboard'],
                ],
            ],
            [
                'label' => 'Procurement',
                'icon'  => 'cart',
                'items' => [
                    ['route' => 'purchasing.index',           'label' => 'Orders & Requests',  'permission' => 'purchasing.view'],
                    ['route' => 'settings.suppliers',         'label' => 'Suppliers',          'permission' => 'purchasing.suppliers.manage'],
                    ['route' => 'settings.supplier-mapping',  'label' => 'Product Mapping',    'permission' => 'purchasing.suppliers.manage'],
                    ['route' => 'settings.form-templates',    'label' => 'Form Templates',     'permission' => 'purchasing.suppliers.manage'],
                    ['route' => 'settings.price-alerts',      'label' => 'Price Alerts',       'permission' => 'purchasing.suppliers.manage'],
                    ['route' => 'settings.index', 'query' => 'module=procurement', 'label' => 'Procurement Settings',
                     'anyPermission' => ['settings.po_approvers', 'settings.departments', 'settings.cpu']],
                ],
            ],
            [
                'label' => 'Inventory & Recipes',
                'icon'  => 'cube',
                'items' => [
                    ['route' => 'ingredients.index',            'label' => 'Market List',        'permission' => 'ingredients.view'],
                    ['route' => 'settings.categories',          'label' => 'Product Categories', 'permission' => 'ingredients.manage'],
                    ['route' => 'recipes.index',                'label' => 'Recipes',            'permission' => 'recipes.view'],
                    ['route' => 'settings.recipe-categories',   'label' => 'Recipe Categories',  'permission' => 'recipes.manage'],
                    ['route' => 'recipes.index',                'label' => 'Prep Items',         'permission' => 'recipes.view', 'query' => 'tab=prep-items'],
                    ['route' => 'settings.price-classes',       'label' => 'Price Classes',      'permission' => 'recipes.price'],
                    ['route' => 'inventory.index',              'label' => 'Stock Management',  'permission' => 'inventory.view'],
                    ['route' => 'settings.par-levels',          'label' => 'Par Levels',         'permission' => 'inventory.view'],
                    ['route' => 'ingredients.review-documents', 'label' => 'Review Documents',   'permission' => 'ingredients.import'],
                    ['route' => 'settings.index', 'query' => 'module=kitchen-production', 'label' => 'Kitchen Settings',
                     'anyPermission' => ['settings.kitchens']],
                ],
            ],
            [
                'label' => 'Labels',
                'icon'  => 'tag',
                'items' => [
                    ['route' => 'labels.print',      'label' => 'Print Labels',   'permission' => 'labels.print'],
                    ['route' => 'labels.sets',       'label' => 'Print Sets',     'permission' => 'labels.print'],
                    ['route' => 'labels.expiring',   'label' => 'Expiring',       'permission' => 'labels.print'],
                    ['route' => 'labels.log',        'label' => 'Print Log',      'permission' => 'labels.view_log'],
                    ['route' => 'labels.shelf-life', 'label' => 'Shelf Life',     'permission' => 'labels.manage'],
                    ['route' => 'labels.templates',  'label' => 'Templates',      'permission' => 'labels.manage'],
                    ['route' => 'labels.printers',   'label' => 'Label Printers', 'permission' => 'labels.manage'],
                    ['route' => 'labels.agents',     'label' => 'Print Agents',   'permission' => 'labels.manage'],
                    ['route' => 'labels.settings',   'label' => 'Label Settings', 'permission' => 'labels.manage'],
                ],
            ],
            [
                'label' => 'Sales',
                'icon'  => 'chart',
                'items' => [
                    ['route' => 'sales.index',               'label' => 'Sales Records',    'permission' => 'sales.view'],
                    ['route' => 'sales.pos-sync',            'label' => 'POS Sync',         'permission' => 'sales.import'],
                    ['route' => 'settings.sales-categories', 'label' => 'Sales Categories', 'permission' => 'sales.record'],
                    ['route' => 'settings.sales-targets',    'label' => 'Sales Targets',    'permission' => 'sales.record'],
                ],
            ],
            [
                'label' => 'HR',
                'icon'  => 'users',
                // Sub-grouped by what the screen is FOR, so eleven items stop
                // reading as one list. Order matters: items are rendered in
                // sequence and the caption is emitted when the section changes,
                // so each section must be contiguous.
                'items' => [
                    ['route' => 'hr.employees',          'label' => 'Employees',         'permission' => 'hr.view',         'section' => 'People'],
                    ['route' => 'hr.staff-pins',         'label' => 'Staff PINs',        'permission' => 'staff.pins',      'section' => 'People'],

                    ['route' => 'hr.duty-roster',        'label' => 'Duty Roster',                                          'section' => 'Scheduling'], // Viewable by all users
                    ['route' => 'hr.shifts',             'label' => 'Shifts',            'permission' => 'roster.settings', 'section' => 'Scheduling'],

                    ['route' => 'hr.attendance',         'label' => 'Attendance Record', 'permission' => 'hr.attendance',   'section' => 'Time & Attendance'],
                    ['route' => 'hr.clock-ins',          'label' => 'Clock-Ins',         'permission' => 'hr.clock',        'section' => 'Time & Attendance'],
                    ['route' => 'hr.overtime-claims',    'label' => 'Overtime Claims',   'permission' => 'hr.claims',       'section' => 'Time & Attendance'],
                    ['route' => 'hr.leave',              'label' => 'Leave',             'permission' => 'hr.leave',        'section' => 'Time & Attendance'],
                    ['route' => 'hr.time-off',           'label' => 'Time Off',          'permission' => 'hr.leave',        'section' => 'Time & Attendance'],

                    ['route' => 'hr.compensation',       'label' => 'Compensation',      'permission' => 'hr.compensation', 'section' => 'Pay'],
                    ['route' => 'hr.payroll',            'label' => 'Payroll',           'permission' => 'hr.payroll',      'section' => 'Pay'],
                    ['route' => 'hr.payroll.ea-forms',   'label' => 'EA Forms',          'permission' => 'hr.payroll',      'section' => 'Pay'],
                    ['route' => 'settings.labour-costs', 'label' => 'Labour Costs',      'permission' => 'hr.view',         'section' => 'Pay'],

                    // Training Portal used to sit here, in a section called
                    // "Records & Training". It now lives in Learning &
                    // Development below, with the courses, quizzes and
                    // certificates it belongs beside — one place a manager goes
                    // for anything to do with training, rather than the portal
                    // in HR and everything else somewhere else.
                    ['route' => 'hr.documents',          'label' => 'Documents',         'permission' => 'hr.documents.view', 'section' => 'Records'],

                    // Straight to this module's own settings — including
                    // Clock-In Settings, which moved there. Offered to anyone
                    // who can open any of them, not only to settings.view, or
                    // the person who administers pay would have no way in.
                    ['route' => 'settings.index', 'query' => 'module=hr-people', 'label' => 'HR Settings',
                     // Configuration abilities only. It used to include
                     // hr.compensation, hr.documents.manage and hr.clock.manage,
                     // which every Chef and Branch Manager holds — so the link
                     // was offered to almost everybody in the company.
                     'anyPermission' => ['settings.hr', 'settings.sections', 'settings.certifications', 'settings.ot_approvers'],
                     'section' => 'Configure'],
                ],
            ],
            [
                // Its own group rather than a corner of HR. Training is now a
                // module a merchant runs deliberately — author material, host a
                // live round, issue certificates — and the person who runs a
                // product-knowledge session for the floor is rarely the person
                // who administers payroll.
                //
                // Sectioned for the same reason HR is: eleven items in one list
                // read as a wall. Sections must stay CONTIGUOUS — the caption is
                // emitted when the section changes, so splitting one in two
                // prints its heading twice.
                //
                // "Learning", not "Learning & Development": every other group in
                // this panel is one word or a short pair — Procurement, Labels,
                // Sales, HR — and a four-word label was the odd one out at the
                // width the sidebar actually has. The permission GROUP keeps the
                // longer name, because that list is read rather than scanned.
                'label' => 'Learning',
                'icon'  => 'academic',
                'items' => [
                    ['route' => 'training.courses',       'label' => 'Courses',        'permission' => 'training.view',    'section' => 'Content'],
                    ['route' => 'training.quizzes',       'label' => 'Quizzes',        'permission' => 'training.view',    'section' => 'Content'],
                    ['route' => 'training.paths',         'label' => 'Learning Paths', 'permission' => 'training.view',    'section' => 'Content'],

                    ['route' => 'training.live',          'label' => 'Live Sessions',  'permission' => 'training.host',    'section' => 'Run it'],
                    ['route' => 'training.assignments',   'label' => 'Assignments',    'permission' => 'training.assign',  'section' => 'Run it'],

                    ['route' => 'training.leaderboard',   'label' => 'Leaderboard',    'permission' => 'training.view',    'section' => 'Progress'],
                    ['route' => 'training.report-cards',  'label' => 'Report Cards',   'permission' => 'training.reports', 'section' => 'Progress'],
                    ['route' => 'training.certificates',  'label' => 'Certificates',   'permission' => 'training.view',    'section' => 'Progress'],

                    ['route' => 'settings.lms-users',     'label' => 'Training Portal', 'permission' => 'training.portal', 'section' => 'Configure'],
                    ['route' => 'settings.index', 'query' => 'module=learning-development', 'label' => 'Training Settings',
                     'anyPermission' => ['training.manage', 'training.portal'],
                     'section' => 'Configure'],
                ],
            ],
            [
                'label' => 'Business Intelligence',
                'icon'  => 'trending-up',
                'items' => [
                    ['route' => 'reports.hub',              'label' => 'Reports',         'permission' => 'reports.view'],
                    ['route' => 'analytics.index',          'label' => 'AI Analysis',     'permission' => 'reports.view', 'feature' => 'analytics'],
                    ['route' => 'settings.calendar-events', 'label' => 'Calendar Events', 'permission' => 'reports.view'],
                    ['route' => 'audit-logs.index',         'label' => 'Audit Logs',      'permission' => 'audit.view'],
                    ['route' => 'settings.index', 'query' => 'module=reporting', 'label' => 'Reporting Settings',
                     'anyPermission' => ['reports.view']],
                ],
            ],
            [
                'label' => 'Settings',
                'icon'  => 'cog',
                'items' => [
                    // No permission: the page shows only the modules the user
                    // actually administers, and shows an empty state to anyone
                    // who administers none.
                    ['route' => 'settings.index',     'label' => 'All Settings'],
                    ['route' => 'billing.index',      'label' => 'Billing',      'capability' => 'users.manage'],
                    ['route' => 'referral.dashboard', 'label' => 'Refer & Earn'],
                ],
            ],
        ];
    }

    /**
     * Platform administration, for system roles only.
     *
     * Every item here carried an emoji — 👥 🏢 ➕ 🛡️ and the rest — that the
     * template never rendered. Dead data, and emoji had already been pulled
     * out of the dashboard's quick actions for good reasons: they are a
     * different typeface on every OS, cannot take the icon set's stroke
     * weight, cannot be recoloured, and are announced by screen readers with
     * names that have nothing to do with the task.
     */
    public static function admin(): array
    {
        return [
            [
                'label' => 'Admin',
                'icon'  => 'shield',
                'items' => [
                    ['route' => 'admin.users',               'label' => 'Users'],
                    ['route' => 'admin.companies',           'label' => 'Companies'],
                    ['route' => 'company.create',            'label' => 'New Company'],
                    ['route' => 'admin.role-templates',      'label' => 'Role Templates'],
                    ['route' => 'admin.plans.index',         'label' => 'Plans'],
                    ['route' => 'admin.subscriptions.index', 'label' => 'Subscriptions'],
                    ['route' => 'admin.coupons',             'label' => 'Coupons'],
                    ['route' => 'admin.trials.index',        'label' => 'Trials'],
                    ['route' => 'admin.referrals.index',     'label' => 'Referrals'],
                    ['route' => 'admin.company-health',      'label' => 'Health'],
                    ['route' => 'admin.announcements',       'label' => 'Announcements'],
                    ['route' => 'admin.pages',               'label' => 'Pages'],
                    ['route' => 'settings.api-keys',         'label' => 'API Keys'],
                ],
            ],
        ];
    }

    // ── Central Kitchen ────────────────────────────────────────────────────

    public static function kitchen(): array
    {
        return [
            [
                'label' => null,
                'icon'  => null,
                'items' => [
                    ['route' => 'kitchen.index', 'icon' => 'home', 'label' => 'Dashboard'],
                ],
            ],
            [
                'label' => 'Production',
                'icon'  => 'fire',
                'items' => [
                    // List first, create second — the sidebar previously jumped
                    // straight to the create form while the page tab showed the list
                    ['route' => 'kitchen.index',         'label' => 'Production Orders',   'query' => 'tab=orders'],
                    ['route' => 'kitchen.orders.create', 'label' => 'New Production Order'],
                    ['route' => 'kitchen.recipes.index', 'label' => 'Production Recipes'],
                    ['route' => 'kitchen.index',         'label' => 'Inventory',           'query' => 'tab=inventory'],
                ],
            ],
            [
                'label' => 'Procurement',
                'icon'  => 'cart',
                'items' => [
                    ['route' => 'ingredients.index', 'label' => 'Market List', 'permission' => 'ingredients.view'],
                    ['route' => 'purchasing.index',  'label' => 'Purchasing',  'permission' => 'purchasing.view'],
                ],
            ],
            [
                'label' => 'Operations',
                'icon'  => 'clipboard',
                'items' => [
                    // Same screen the outlet workspace reaches; the kitchen used
                    // to name it after one tab of it.
                    ['route' => 'inventory.index',             'label' => 'Stock Management',  'permission' => 'inventory.view'],
                    ['route' => 'inventory.transfers.create',  'label' => 'New Transfer', 'permission' => 'inventory.transfers.record'],
                ],
            ],
            [
                'label' => 'Labels',
                'icon'  => 'tag',
                'items' => [
                    ['route' => 'labels.print',    'label' => 'Print Labels', 'permission' => 'labels.print'],
                    ['route' => 'labels.sets',     'label' => 'Print Sets',   'permission' => 'labels.print'],
                    ['route' => 'labels.expiring', 'label' => 'Expiring',     'permission' => 'labels.print'],
                    ['route' => 'labels.log',      'label' => 'Print Log',    'permission' => 'labels.view_log'],
                ],
            ],
            [
                'label' => 'Insights',
                'icon'  => 'trending-up',
                'items' => [
                    ['route' => 'reports.production-history', 'label' => 'Production History', 'permission' => 'reports.view'],
                    ['route' => 'reports.yield-analysis',     'label' => 'Yield Analysis',     'permission' => 'reports.view'],
                ],
            ],
            [
                'label' => 'System',
                'icon'  => 'cog',
                'items' => [
                    // Kitchen keeps its own production category list, so it
                    // needs a way in that isn't the outlet sidebar.
                    ['route' => 'settings.recipe-categories', 'label' => 'Production Categories', 'permission' => 'recipes.manage'],
                    // The kitchen's own price classes. Same screen as the
                    // outlet's, showing the kitchen list because the workspace
                    // decides which one it edits.
                    ['route' => 'settings.price-classes',     'label' => 'Production Price Classes', 'permission' => 'recipes.price'],
                    ['route' => 'settings.index',             'label' => 'Settings'],
                ],
            ],
        ];
    }
}
