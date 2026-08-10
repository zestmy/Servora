<?php

/**
 * The permission registry — the single source of truth for what abilities exist.
 *
 * Before this file, the catalogue lived in two places that silently drifted: the
 * `permissions` table (what is enforced) and `Settings\Users::MODULES`, a 23-entry
 * PHP const (what an admin could actually grant). Ten permissions — including
 * `hr.payroll` and `hr.payroll.approve` — existed and were enforced but appeared in
 * neither admin screen, so the only way to change who could run payroll was to write
 * a migration.
 *
 * Everything now reads from here: `permissions:sync` seeds the table, Settings > Users
 * and Admin > Role Templates render their checkboxes, and PermissionRegistryTest fails
 * the build if a route, Blade directive or nav item names an ability this file does not
 * declare (or if an ability declared here is enforced nowhere).
 *
 * ---------------------------------------------------------------------------
 * `name` IS THE ENFORCED STRING AND MUST NOT BE RENAMED.
 * ---------------------------------------------------------------------------
 * It appears in route middleware, `@can`, nav arrays and — crucially — in the
 * `role_has_permissions` / `model_has_permissions` rows already in production.
 * The module/ability keys around it are presentation and grouping only, which is why
 * the legacy flat names (`hr.attendance`, `staff.pins`) sit happily beside the dotted
 * ones. Introducing a better name means a migration that moves the grants, not an edit
 * here.
 *
 * Per-ability keys:
 *   name        (required) the permission string as enforced. Never rename.
 *   label       (required) short label for the ability grid.
 *   title       full label for badges and flat lists. Defaults to the module label for
 *               single-ability modules, "Module (Ability)" otherwise — which reproduces
 *               the pre-registry wording exactly.
 *   help        one-line explanation shown under the checkbox.
 *   managed_by  'capability' — real and enforced, but granted through some other control
 *               rather than the module grid. Excluded from the grid, still registry-known
 *               so the drift test accounts for it. CURRENTLY UNUSED: `users.manage` was the
 *               only entry and Phase 1 folded the capability flags into permissions, so it
 *               is now an ordinary grid ability. The mechanism is kept for the next time one
 *               permission has two controls, because that is what makes them disagree.
 *   enforced    false — declared and granted, but gates nothing today. Must carry a note.
 *               The drift test permits only explicitly-declared exceptions.
 *
 * Scope note: this registry covers only abilities that are enforced TODAY — 42 after Phase 1
 * folded the capability flags in. The wider ~172-ability target (View/Create/Edit/Delete/
 * Approve per sub-module) lands in Phase 4, one module at a time, as the enforcement for each
 * is actually written. Declaring the full target here now would put ~130 checkboxes in front
 * of admins that gate nothing — and would hole the drift-test invariant on day one.
 * See docs/rbac-revamp-proposal.md.
 */

return [

    'groups' => [
        'operations' => 'Operations',
        'people'     => 'People & HR',
        'insight'    => 'Reporting',
        'admin'      => 'Administration',
    ],

    'modules' => [

        /* ---------------------------------------------------------------- Operations */

        'ingredients' => [
            'label'     => 'Ingredients',
            'group'     => 'operations',
            'abilities' => [
                'view' => [
                    'name'  => 'ingredients.view',
                    'label' => 'View (read-only)',
                    'title' => 'Ingredients',
                    'help'  => 'See the ingredient list and costs, and export them. Read-only on its own.',
                ],
                'manage' => [
                    'name'  => 'ingredients.manage',
                    'label' => 'Add & edit',
                    'title' => 'Ingredients (Add & Edit)',
                    'help'  => 'Create and change ingredients, their costs and categories.',
                ],
                'delete' => [
                    'name'  => 'ingredients.delete',
                    'label' => 'Delete',
                    'title' => 'Ingredients (Delete)',
                    'help'  => 'Delete an ingredient or a category.',
                ],
                'import' => [
                    'name'  => 'ingredients.import',
                    'label' => 'Import & scan',
                    'title' => 'Ingredients (Import)',
                    'help'  => 'Bulk import ingredients and review scanned supplier documents.',
                ],
            ],
        ],

        'recipes' => [
            'label'     => 'Recipes',
            'group'     => 'operations',
            'abilities' => [
                'view' => [
                    'name'  => 'recipes.view',
                    'label' => 'View (read-only)',
                    'title' => 'Recipes',
                    'help'  => 'See recipes and costings, and export cost sheets and SOPs.',
                ],
                'manage' => [
                    'name'  => 'recipes.manage',
                    'label' => 'Add & edit',
                    'title' => 'Recipes (Add & Edit)',
                    'help'  => 'Create and change recipes, prep items and recipe categories.',
                ],
                // Separated from editing the recipe itself for the same reason salary is
                // separated from employee records: the person who maintains a method is
                // not necessarily the person who decides what it sells for.
                'price' => [
                    'name'  => 'recipes.price',
                    'label' => 'Set selling prices',
                    'title' => 'Recipes (Set Prices)',
                    'help'  => 'Change selling prices per price class, which drives revenue and margin.',
                ],
                'delete' => [
                    'name'  => 'recipes.delete',
                    'label' => 'Delete',
                    'title' => 'Recipes (Delete)',
                    'help'  => 'Delete a recipe or prep item.',
                ],
                'import' => [
                    'name'  => 'recipes.import',
                    'label' => 'Import',
                    'title' => 'Recipes (Import)',
                    'help'  => 'Bulk import recipes, creating ingredients and categories as it goes.',
                ],
            ],
        ],

        'purchasing' => [
            'label'     => 'Purchasing',
            'group'     => 'operations',
            'abilities' => [
                // Module-level read. Deliberately NOT split per document type: the
                // Purchasing index is a single tabbed Livewire component showing orders,
                // requests, GRNs and invoices together, so "view orders but not invoices"
                // is not enforceable without splitting that component — a much larger
                // change than this phase. Writes split instead, which is where F3 actually
                // bit: this one permission used to grant raising POs, receiving goods and
                // processing invoices as well as reading them.
                'view' => [
                    'name'  => 'purchasing.view',
                    'label' => 'View (read-only)',
                    // Explicit: adding abilities below would otherwise turn this into
                    // "Purchasing (View (read-only))" and change every existing badge.
                    'title' => 'Purchasing',
                    'help'  => 'See purchase orders, requests, goods receipts and invoices. Read-only on its own.',
                ],
                'orders_create' => [
                    'name'  => 'purchasing.orders.create',
                    'label' => 'Raise orders',
                    'title' => 'Purchasing (Raise Orders)',
                    'help'  => 'Create a purchase order, including by consolidating requests.',
                ],
                'orders_edit' => [
                    'name'  => 'purchasing.orders.edit',
                    'label' => 'Amend orders',
                    'title' => 'Purchasing (Amend Orders)',
                    'help'  => 'Change an existing purchase order, or convert one to a delivery order.',
                ],
                'requests_create' => [
                    'name'  => 'purchasing.requests.create',
                    'label' => 'Raise requests',
                    'title' => 'Purchasing (Raise Requests)',
                    'help'  => 'Create a purchase request.',
                ],
                'requests_edit' => [
                    'name'  => 'purchasing.requests.edit',
                    'label' => 'Amend requests',
                    'title' => 'Purchasing (Amend Requests)',
                    'help'  => 'Change an existing purchase request.',
                ],
                'transfers_create' => [
                    'name'  => 'purchasing.transfers.create',
                    'label' => 'Raise transfers',
                    'title' => 'Purchasing (Raise Transfers)',
                    'help'  => 'Create a stock transfer order between outlets.',
                ],
                // Consolidating turns many outstanding requests into purchase orders in one
                // action, across outlets. Raising a single order and sweeping up everyone's
                // requests into committed spend are different decisions, so it is granted
                // separately rather than riding on purchasing.orders.create.
                'consolidate' => [
                    'name'  => 'purchasing.consolidate',
                    'label' => 'Consolidate requests',
                    'title' => 'Purchasing (Consolidate Requests)',
                    'help'  => 'Turn outstanding purchase requests into orders in bulk.',
                ],
                'suppliers_manage' => [
                    'name'  => 'purchasing.suppliers.manage',
                    'label' => 'Manage suppliers',
                    'title' => 'Purchasing (Manage Suppliers)',
                    'help'  => 'Supplier directory and mapping, price alerts and order form templates.',
                ],
                'approve' => [
                    'name'  => 'purchasing.approve',
                    'label' => 'Approve orders',
                    'title' => 'Purchasing (Approve Orders)',
                    'help'  => 'Approve or reject a submitted purchase order.',
                ],
                'request' => [
                    'name'  => 'purchasing.request',
                    'label' => 'Approve requests',
                    'title' => 'Purchasing (Approve Requests)',
                    'help'  => 'Approve or reject a purchase request before it becomes an order.',
                ],
                'receive' => [
                    'name'  => 'purchasing.receive',
                    'label' => 'Receive goods',
                    'title' => 'Purchasing (Receive Goods)',
                    'help'  => 'Record goods received against an order (GRN), and receive stock transfers.',
                ],
                'invoice' => [
                    'name'  => 'purchasing.invoice',
                    'label' => 'Invoices & credit notes',
                    'title' => 'Purchasing (Invoices)',
                    'help'  => 'Supplier invoices, payments and credit notes.',
                ],
                'delete' => [
                    'name'  => 'purchasing.delete',
                    'label' => 'Delete & roll back',
                    'title' => 'Purchasing (Delete)',
                    'help'  => 'Delete purchasing documents and roll back an approved order.',
                ],
            ],
        ],

        'sales' => [
            'label'     => 'Sales',
            'group'     => 'operations',
            'abilities' => [
                'view' => [
                    'name'  => 'sales.view',
                    'label' => 'View (read-only)',
                    'title' => 'Sales',
                    'help'  => 'See sales records and daily closures, and export them. Read-only on its own.',
                ],
                'record' => [
                    'name'  => 'sales.record',
                    'label' => 'Record & close',
                    'title' => 'Sales (Record)',
                    'help'  => 'Enter and amend sales records, close the day, and manage sales categories and targets.',
                ],
                'import' => [
                    'name'  => 'sales.import',
                    'label' => 'Import',
                    'title' => 'Sales (Import)',
                    'help'  => 'Bulk import sales, which can overwrite a period in one action.',
                ],
                'delete' => [
                    'name'  => 'sales.delete',
                    'label' => 'Delete',
                    'title' => 'Sales (Delete)',
                    'help'  => 'Delete sales records and closures.',
                ],
            ],
        ],

        'inventory' => [
            'label'     => 'Inventory & Kitchen',
            'group'     => 'operations',
            'abilities' => [
                // Module-level read, like Purchasing: the Inventory index is one screen
                // listing every document type together.
                'view' => [
                    'name'  => 'inventory.view',
                    'label' => 'View (read-only)',
                    'title' => 'Inventory & Kitchen',
                    'help'  => 'See stock takes, wastage, transfers, staff meals, prep items and purchases. Read-only on its own.',
                ],

                // Recording and deleting split per document type. Unlike Purchasing, each
                // type here has its own form component and its own delete method, so the
                // split lands on real seams rather than needing anything restructured.
                'stock_takes_record' => [
                    'name' => 'inventory.stock_takes.record', 'label' => 'Stock takes — record',
                    'title' => 'Inventory (Record Stock Takes)', 'help' => 'Start and complete stock takes.',
                ],
                'stock_takes_delete' => [
                    'name' => 'inventory.stock_takes.delete', 'label' => 'Stock takes — delete',
                    'title' => 'Inventory (Delete Stock Takes)', 'help' => 'Delete a completed stock take, reversing its effect on stock.',
                ],
                'wastage_record' => [
                    'name' => 'inventory.wastage.record', 'label' => 'Wastage — record',
                    'title' => 'Inventory (Record Wastage)', 'help' => 'Record and amend wastage.',
                ],
                'wastage_delete' => [
                    'name' => 'inventory.wastage.delete', 'label' => 'Wastage — delete',
                    'title' => 'Inventory (Delete Wastage)', 'help' => 'Delete a wastage record.',
                ],
                'transfers_record' => [
                    'name' => 'inventory.transfers.record', 'label' => 'Transfers — record',
                    'title' => 'Inventory (Record Transfers)', 'help' => 'Create and amend outlet transfers.',
                ],
                'transfers_delete' => [
                    'name' => 'inventory.transfers.delete', 'label' => 'Transfers — delete',
                    'title' => 'Inventory (Delete Transfers)', 'help' => 'Delete a transfer that is no longer a draft.',
                ],
                'staff_meals_record' => [
                    'name' => 'inventory.staff_meals.record', 'label' => 'Staff meals — record',
                    'title' => 'Inventory (Record Staff Meals)', 'help' => 'Record and amend staff meals.',
                ],
                'staff_meals_delete' => [
                    'name' => 'inventory.staff_meals.delete', 'label' => 'Staff meals — delete',
                    'title' => 'Inventory (Delete Staff Meals)', 'help' => 'Delete a staff meal record.',
                ],
                'prep_items_record' => [
                    'name' => 'inventory.prep_items.record', 'label' => 'Prep items — record',
                    'title' => 'Inventory (Record Prep Items)', 'help' => 'Create and amend prep items.',
                ],
                'prep_items_delete' => [
                    'name' => 'inventory.prep_items.delete', 'label' => 'Prep items — delete',
                    'title' => 'Inventory (Delete Prep Items)', 'help' => 'Delete a prep item and its synced ingredient.',
                ],
                'purchases_record' => [
                    'name' => 'inventory.purchases.record', 'label' => 'Purchases — record',
                    'title' => 'Inventory (Record Purchases)', 'help' => 'Capture and amend direct purchases.',
                ],
                'purchases_delete' => [
                    'name' => 'inventory.purchases.delete', 'label' => 'Purchases — delete',
                    'title' => 'Inventory (Delete Purchases)', 'help' => 'Delete a captured purchase.',
                ],
            ],
        ],

        'labels' => [
            'label'     => 'Food Labels',
            'group'     => 'operations',
            'abilities' => [
                'print' => [
                    'name'  => 'labels.print',
                    'label' => 'Print',
                    'title' => 'Food Labels (Print)',
                    'help'  => 'Print HACCP labels and print sets, and see what is expiring.',
                ],
                'log' => [
                    'name'  => 'labels.view_log',
                    'label' => 'Print log',
                    'title' => 'Food Labels (Print Log)',
                    'help'  => 'Review the history of everything printed.',
                ],
                'manage' => [
                    'name'  => 'labels.manage',
                    'label' => 'Manage',
                    'title' => 'Food Labels (Manage)',
                    'help'  => 'Shelf life, label templates, printers and label settings.',
                ],
            ],
        ],

        /* ------------------------------------------------------------ People & HR */

        'hr.employees' => [
            'label'     => 'HR — Employees & Labour',
            'group'     => 'people',
            'abilities' => [
                'view' => [
                    'name'  => 'hr.view',
                    'label' => 'View (read-only)',
                    'title' => 'HR — Employees & Labour',
                    'help'  => 'See employee records, departments, shifts and labour cost, and export them.',
                ],
                // Employee records carry IC numbers, bank details and the link to payroll,
                // so creating and editing them is not something "can open the staff list"
                // should imply. Deleting is separate again — it was previously gated by
                // nothing at all beyond outlet access.
                'manage' => [
                    'name'  => 'hr.employees.manage',
                    'label' => 'Add & edit staff',
                    'title' => 'HR — Employees (Add & Edit)',
                    'help'  => 'Create employee records and change their details, including bank and identity fields.',
                ],
                'delete' => [
                    'name'  => 'hr.employees.delete',
                    'label' => 'Delete staff',
                    'title' => 'HR — Employees (Delete)',
                    'help'  => 'Delete an employee record.',
                ],
                // Someone's standing — probation, confirmation, resignation, whether they
                // are still active — is a different order of information from where they
                // work and what they are called. It sits behind its own ability for the
                // same reason pay does, and is protected the same way: the fields are
                // hidden, and a submitted value is ignored rather than trusted.
                //
                // Deliberately NOT the whole Employment tab. Outlet is required to save an
                // employee and lives there, so hiding all of it would hand anyone who can
                // edit staff a form they cannot submit, failing on a field they cannot see.
                'employment' => [
                    'name'  => 'hr.employment',
                    'label' => 'Employment standing',
                    'title' => 'HR — Employment Standing',
                    'help'  => 'See and change employment status, join and resignation dates, '
                             . 'active/inactive and outsourcing. Placement fields stay open to anyone who can edit staff.',
                ],
            ],
        ],

        'hr.attendance' => [
            'label'     => 'HR — Attendance & Service Charge',
            'group'     => 'people',
            'abilities' => [
                'view' => [
                    'name'  => 'hr.attendance',
                    'label' => 'View (read-only)',
                    'title' => 'HR — Attendance & Service Charge',
                    'help'  => 'See the attendance grid, service charge distribution and exports.',
                ],
                // Marking someone present or absent feeds straight into payroll, so
                // editing the grid is a separate ability from reading it. Service charge
                // itself stays gated on hr.compensation, which is stricter again.
                'record' => [
                    'name'  => 'hr.attendance.record',
                    'label' => 'Edit attendance',
                    'title' => 'HR — Attendance (Edit)',
                    'help'  => 'Mark attendance, fill or clear a range, reorder rows, and manage attendance codes.',
                ],
            ],
        ],

        'hr.claims' => [
            'label'     => 'HR — Overtime Claims',
            'group'     => 'people',
            'abilities' => [
                'view' => [
                    'name'  => 'hr.claims',
                    'label' => 'View & settle',
                    'title' => 'HR — Overtime Claims',
                    'help'  => 'Submit, review and settle overtime claims.',
                ],
                'delete' => [
                    'name'  => 'hr.claims.delete',
                    'label' => 'Delete any claim',
                    'title' => 'HR — Overtime Claims (Delete)',
                    'help'  => 'Remove any overtime claim regardless of its status.',
                ],
            ],
        ],

        'hr.clock' => [
            'label'     => 'HR — Clock-In',
            'group'     => 'people',
            'abilities' => [
                'view' => [
                    'name'  => 'hr.clock',
                    'label' => 'Review punches',
                    'title' => 'HR — Clock-In Review',
                    'help'  => 'Review clock-in and clock-out punches, including photos and locations.',
                ],
                'manage' => [
                    'name'  => 'hr.clock.manage',
                    'label' => 'Settings & enrolment',
                    'title' => 'HR — Clock-In Settings & Face Enrolment',
                    'help'  => 'Clock settings, kiosk devices and face enrolment.',
                ],
                'delete' => [
                    'name'  => 'hr.clock.delete',
                    'label' => 'Delete punches',
                    'title' => 'HR — Clock-In (Delete Punches)',
                    'help'  => 'Delete a recorded clock-in or clock-out punch.',
                ],
            ],
        ],

        'hr.compensation' => [
            'label'     => 'HR — Salary & Service Points',
            'group'     => 'people',
            'abilities' => [
                'view' => [
                    'name'  => 'hr.compensation',
                    'label' => 'View & revise',
                    'title' => 'HR — Salary & Service Points (sensitive)',
                    'help'  => 'See and propose changes to salaries and service points.',
                ],
                'approve' => [
                    'name'  => 'hr.compensation.approve',
                    'label' => 'Approve revisions',
                    'title' => 'HR — Salary Revisions (Approve)',
                    'help'  => 'Sign off a proposed salary revision, committing it to payroll.',
                ],
            ],
        ],

        'hr.leave' => [
            'label'     => 'HR — Leave & Time Off',
            'group'     => 'people',
            'abilities' => [
                'view' => [
                    'name'  => 'hr.leave',
                    'label' => 'View & apply',
                    'title' => 'HR — Leave & Time Off',
                    'help'  => 'See the leave calendar and apply for leave or time off.',
                ],
                'approve' => [
                    'name'  => 'hr.leave.approve',
                    'label' => 'Approve & grant',
                    'title' => 'HR — Leave (Approve)',
                    'help'  => "Decide on other people's requests, and grant entitlement.",
                ],
            ],
        ],

        'hr.payroll' => [
            'label'     => 'HR — Payroll',
            'group'     => 'people',
            'abilities' => [
                'view' => [
                    'name'  => 'hr.payroll',
                    'label' => 'Run & view',
                    'title' => 'HR — Payroll (Run & View)',
                    'help'  => 'Generate payroll runs, read payslips, EA and Form E exports.',
                ],
                'approve' => [
                    'name'  => 'hr.payroll.approve',
                    'label' => 'Approve run',
                    'title' => 'HR — Payroll (Approve)',
                    'help'  => 'Sign a run off, after which its figures are locked.',
                ],
            ],
        ],

        'hr.documents' => [
            'label'     => 'HR Documents',
            'group'     => 'people',
            'abilities' => [
                'view' => [
                    'name'  => 'hr.documents.view',
                    'label' => 'View',
                    'help'  => 'Read employee documents and folders.',
                ],
                'manage' => [
                    'name'  => 'hr.documents.manage',
                    'label' => 'Manage',
                    'help'  => 'Upload, move and delete employee documents, and manage folders.',
                ],
            ],
        ],

        'staff.pins' => [
            'label'     => 'HR — Staff PINs',
            'group'     => 'people',
            'abilities' => [
                'view' => [
                    'name'  => 'staff.pins',
                    'label' => 'HR — Staff PINs (staff app access)',
                    'title' => 'HR — Staff PINs (staff app access)',
                    'help'  => 'Issue and reset the PINs staff use for the clock and label apps.',
                ],
            ],
        ],

        'roster' => [
            'label'     => 'Duty Roster',
            'group'     => 'people',
            'abilities' => [
                'view' => [
                    'name'     => 'roster.view',
                    'label'    => 'View',
                    'help'     => 'See published duty rosters.',
                    'enforced' => false,
                    'note'     => 'Granted to 5 roles but gates nothing: routes/web.php:427 leaves '
                                . '/hr/duty-roster open to every authenticated user by design. Enforcing it '
                                . 'would remove roster visibility from Chef, Purchasing, Finance and Staff, '
                                . 'so it is a deliberate behaviour change for a later phase, not Phase 0.',
                ],
                'create' => [
                    'name'  => 'roster.create',
                    'label' => 'Create',
                    'help'  => 'Start a new roster for a section and week.',
                ],
                'edit' => [
                    'name'  => 'roster.edit',
                    'label' => 'Edit/Submit',
                    'help'  => 'Change roster entries and submit for approval.',
                ],
                'approve' => [
                    'name'  => 'roster.approve',
                    'label' => 'Approve',
                    'help'  => 'Approve or reject a submitted roster.',
                ],
                'delete' => [
                    'name'  => 'roster.delete',
                    'label' => 'Delete',
                    'help'  => 'Delete a roster and its entries.',
                ],
                'amend' => [
                    'name'  => 'roster.amend',
                    'label' => 'Amend Approved',
                    'help'  => 'Change a roster after it has been approved.',
                ],
                'settings' => [
                    'name'  => 'roster.settings',
                    'label' => 'Settings',
                    'help'  => 'Roster approvers, stations, sections and email recipients.',
                ],
            ],
        ],

        /* -------------------------------------------------------------- Reporting */

        'reports' => [
            'label'     => 'Reports',
            'group'     => 'insight',
            'abilities' => [
                'view' => [
                    'name'  => 'reports.view',
                    'label' => 'View & export',
                    'title' => 'Reports',
                    'help'  => 'The reports hub and report exports.',
                ],
                // Reading a report on screen and mailing it out on a schedule are
                // different powers. A subscription sends company figures to whatever
                // address is configured, on repeat, without anyone watching — closer to
                // data egress than to reporting, so it is granted separately.
                'schedule' => [
                    'name'  => 'reports.schedule',
                    'label' => 'Schedule by email',
                    'title' => 'Reports (Scheduled Subscriptions)',
                    'help'  => 'Create and change scheduled report subscriptions, which email figures out on a recurring basis.',
                ],
            ],
        ],

        'audit' => [
            'label'     => 'Audit Logs',
            'group'     => 'insight',
            'abilities' => [
                'view' => [
                    'name'  => 'audit.view',
                    'label' => 'Audit Logs',
                    'help'  => 'Who changed what, and when.',
                ],
            ],
        ],

        /* ---------------------------------------------------------- Administration */

        'settings' => [
            'label'     => 'Settings',
            'group'     => 'admin',
            'abilities' => [
                // Every page below was behind a single `settings.view` until Phase 5:
                // granting someone tax rates also handed
                // them branches, departments, central kitchens and every approver list.
                // Pages that belong to another module were already gated by it — pay
                // components and banks on hr.compensation, leave types on
                // hr.leave.approve, scheduled reports on reports.view, suppliers on
                // purchasing.suppliers.manage — and are deliberately not duplicated here.
                'outlets' => [
                    'name' => 'settings.outlets', 'label' => 'Branches',
                    'title' => 'Settings (Branches)', 'help' => 'Create and edit outlets.',
                ],
                'outlet_groups' => [
                    'name' => 'settings.outlet_groups', 'label' => 'Outlet groups',
                    'title' => 'Settings (Outlet Groups)', 'help' => 'Group outlets for reporting and access.',
                ],
                'tax_rates' => [
                    'name' => 'settings.tax_rates', 'label' => 'Tax rates',
                    'title' => 'Settings (Tax Rates)', 'help' => 'Tax rates applied to purchasing and sales.',
                ],
                'sections' => [
                    'name' => 'settings.sections', 'label' => 'Sections',
                    'title' => 'Settings (Sections)', 'help' => 'FOH/BOH sections used by the duty roster.',
                ],
                'certifications' => [
                    'name' => 'settings.certifications', 'label' => 'Certifications',
                    'title' => 'Settings (Certifications)', 'help' => 'Certification and training types for staff.',
                ],
                'ot_approvers' => [
                    'name' => 'settings.ot_approvers', 'label' => 'OT approvers',
                    'title' => 'Settings (OT Approvers)', 'help' => 'Who approves overtime claims, per outlet.',
                ],
                'hr' => [
                    'name' => 'settings.hr', 'label' => 'HR configuration',
                    'title' => 'Settings (HR)',
                    'help'  => 'Leave types, holidays, approvers, particulars and clock-in rules — '
                        . 'company-wide HR setup, not approving one leave request.',
                ],
                'po_approvers' => [
                    'name' => 'settings.po_approvers', 'label' => 'PO approvers',
                    'title' => 'Settings (PO Approvers)', 'help' => 'Who approves purchase orders, per outlet.',
                ],
                'departments' => [
                    'name' => 'settings.departments', 'label' => 'Departments',
                    'title' => 'Settings (Departments)', 'help' => 'Purchasing departments and their budgets.',
                ],
                'cpu' => [
                    'name' => 'settings.cpu', 'label' => 'Central purchasing',
                    'title' => 'Settings (Central Purchasing)', 'help' => 'Central purchasing units and their outlets.',
                ],
                'kitchens' => [
                    'name' => 'settings.kitchens', 'label' => 'Central kitchens',
                    'title' => 'Settings (Central Kitchens)', 'help' => 'Central kitchens, their members and linked outlets.',
                ],
            ],
        ],

        'users' => [
            'label'     => 'Users & Access',
            'group'     => 'admin',
            'abilities' => [
                'manage' => [
                    'name'  => 'users.manage',
                    'label' => 'Users & Access',
                    'help'  => 'Create users, set their access, and edit company details.',
                ],
            ],
        ],

    ],
];
