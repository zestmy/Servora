<?php

/*
 * Modules: the units Servora is sold in (docs/pricing-model.md) and the map
 * from every screen to the module that owns it. App\Services\Entitlements is
 * the only reader of the catalogue and the map; ask it, not this file.
 */
return [

    /*
     * Supplier portal and marketplace: supplier logins at /supplier, the
     * public /marketplace and /for-suppliers pages, the in-app "Find
     * Suppliers" directory and supplier product mapping.
     *
     * Parked while Servora focuses on merchants. Switching it off hides the
     * screens and 404s the routes; no code, table or row is removed, so it
     * comes back by flipping this. Supplier records, purchasing, PO email and
     * price alerts are merchant features and do not depend on it.
     */
    'supplier_portal' => (bool) env('SUPPLIER_PORTAL_ENABLED', false),

    /*
     * Platform switches: modules that are on or off for EVERY company at once,
     * read from the top-level key of the same name above. No plan grants them.
     */
    'switches' => ['supplier_portal'],

    /*
     * What can be sold. `kind`:
     *   suite  — part of a suite plan (Basic / Full), never bought alone
     *   addon  — flat per company; a Basic company may hold at most
     *            `addon_cap_on_basic` of them, the Full suite includes all
     *   metered — priced per unit (employee, kitchen), on either suite,
     *            outside the add-on cap
     * Prices are MYR per month and are the list price; what a company actually
     * pays is stored on its subscription_addons row.
     */
    'catalogue' => [
        'basic'           => ['name' => 'Basic suite',          'kind' => 'suite'],
        'labels'          => ['name' => 'Food Safety Labels',   'kind' => 'addon',   'price' => 80],
        'learn'           => ['name' => 'Learn SOP',            'kind' => 'addon',   'price' => 100],
        'audits'          => ['name' => 'Audits & Compliance',  'kind' => 'addon',   'price' => 60],
        'assets'          => ['name' => 'Assets',               'kind' => 'addon',   'price' => 50],
        'pos_sync'        => ['name' => 'POS Sync',             'kind' => 'addon',   'price' => 60],
        'ai_insights'     => ['name' => 'AI Insights',          'kind' => 'addon',   'price' => 80],
        'hr'              => ['name' => 'HR & Payroll',         'kind' => 'metered', 'price' => 3,   'unit' => 'employee', 'min_quantity' => 10],
        'central_kitchen' => ['name' => 'Central Kitchen',      'kind' => 'metered', 'price' => 300, 'unit' => 'kitchen',  'min_quantity' => 1],
    ],

    'addon_cap_on_basic' => 2,

    /*
     * Route name => module, FIRST MATCH WINS (Str::is patterns). `null` means
     * core: open to every company, Free included. A route that matches nothing
     * is core too — so a new screen is free until somebody files it here,
     * which fails open on purpose: a paying customer locked out of a screen is
     * worse than a free one seeing it early.
     *
     * Exceptions go ABOVE the wildcard they carve out of.
     */
    'routes' => [
        // ── Staff portal: the shell every staff app needs ──────────────────
        'clock.staff.home'        => null,
        'clock.staff.login'       => null,
        'clock.staff.login.photo' => null,
        'clock.staff.logout'      => null,
        'clock.staff.account'     => null,
        'clock.staff.manifest'    => null,
        'clock.staff.sw'          => null,
        'clock.staff.heartbeat'   => null,
        'clock.staff.photo'       => null, // colleagues' faces in the header

        // ── Learn SOP ───────────────────────────────────────────────────────
        'clock.staff.learn*'      => 'learn',
        'clock.staff.lms'         => 'learn',
        'clock.staff.live'        => 'learn',
        'clock.staff.quiz.link'   => 'learn',
        'clock.staff.leaderboard' => 'learn',
        'clock.staff.certificate' => 'learn',
        'training.*'              => 'learn',
        'lms.*'                   => 'learn',
        'settings.lms-users'      => 'learn',

        // ── Audits ──────────────────────────────────────────────────────────
        'clock.staff.actions'     => 'audits',
        'audits.*'                => 'audits',
        'reports.audit-trend'     => 'audits',

        // ── HR & Payroll (the rest of the staff portal is clock/leave/pay) ─
        'clock.*'                         => 'hr',
        'hr.*'                            => 'hr',
        'settings.leave-types'            => 'hr',
        'settings.leave-approvers'        => 'hr',
        'settings.ot-approvers'           => 'hr',
        'settings.pay-components'         => 'hr',
        'settings.statutory'              => 'hr',
        'settings.banks'                  => 'hr',
        'settings.employee-particulars'   => 'hr',
        'settings.public-holidays'        => 'hr',
        'settings.labour-costs'           => 'hr',
        'settings.document-folders'       => 'hr',
        'settings.certifications'         => 'hr',
        'reports.service-charge-payout'   => 'hr',

        // ── Food Safety Labels ─────────────────────────────────────────────
        'labels.*'                => 'labels',
        'print-agent.*'           => 'labels',

        // ── Assets / POS Sync / AI Insights ────────────────────────────────
        'assets.*'                => 'assets',
        'sales.pos-sync'          => 'pos_sync',
        'pos-agent.*'             => 'pos_sync',
        'analytics.*'             => 'ai_insights',

        // ── Central Kitchen ─────────────────────────────────────────────────
        'kitchen.*'                  => 'central_kitchen',
        'settings.kitchen-management' => 'central_kitchen',
        'reports.production-history' => 'central_kitchen',
        'reports.yield-analysis'     => 'central_kitchen',

        // ── Supplier portal (platform switch) ──────────────────────────────
        'purchasing.suppliers.directory' => 'supplier_portal',
        'settings.supplier-mapping'      => 'supplier_portal',

        // ── Basic suite. Free keeps costing, the supplier list, stock takes,
        //    manual sales and the food-cost reports; everything else here is
        //    the first thing worth paying for. ──────────────────────────────
        'reports.index'                  => null,
        'reports.hub'                    => null,
        'reports.menu-ingredients'       => null,
        'reports.sales-menu-ingredients' => null,
        'reports.stock-count'            => null,
        'reports.*'                      => 'basic',
        'audit-logs.*'                   => 'basic',
        'purchasing.*'                   => 'basic',
        'inventory.purchases.*'          => 'basic',
        'inventory.transfers.*'          => 'basic',
        'inventory.wastage.*'            => 'basic',
        'inventory.staff-meals.*'        => 'basic',
        'ingredients.scan-document'      => 'basic',
        'ingredients.review-documents*'  => 'basic',
        'recipes.import'                 => 'basic',
        'recipes.cost-pdf-all'           => 'basic',
        'recipes.cost-pdf-summary'       => 'basic',
        'recipes.prep-cost-pdf-all'      => 'basic',
        'recipes.prep-cost-pdf-summary'  => 'basic',
        'sales.import'                   => 'basic',
        'settings.par-levels'            => 'basic',
        'settings.po-approvers'          => 'basic',
        'settings.cpu-management'        => 'basic',
        'settings.departments'           => 'basic',
        'settings.form-templates*'       => 'basic',
        'settings.price-alerts*'         => 'basic',
        'settings.price-classes'         => 'basic',
        'settings.sales-targets'         => 'basic',
        'settings.outlet-groups'         => 'basic',
        'settings.calendar-events'       => 'basic',
        'settings.reports*'              => 'basic',
    ],

    /*
     * Legacy plan feature flags → the module that now carries them, so
     * canUseFeature('analytics') keeps answering on the new plans.
     */
    'legacy_features' => [
        'analytics'   => 'ai_insights',
        'ai_analysis' => 'ai_insights',
        'lms'         => 'learn',
        'reports'     => 'basic',
    ],

];
