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
 *   managed_by  'capability' — real and enforced, but granted through a capability flag
 *               rather than the module grid. Excluded from the grid, still registry-known
 *               so the drift test accounts for it. Phase 1 removes this.
 *   enforced    false — declared and granted, but gates nothing today. Must carry a note.
 *               The drift test permits only explicitly-declared exceptions.
 *
 * Scope note: this registry covers the ~33 abilities that are enforced TODAY. The wider
 * ~172-ability target (View/Create/Edit/Delete/Approve per sub-module) lands in Phase 4,
 * one module at a time, as the enforcement for each is actually written. Declaring the
 * full target here now would put 139 checkboxes in front of admins that gate nothing —
 * and would hole the drift-test invariant on day one. See docs/rbac-revamp-proposal.md.
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
                    'label' => 'Ingredients',
                    'help'  => 'Ingredient list, costs, categories, imports and document scanning.',
                ],
            ],
        ],

        'recipes' => [
            'label'     => 'Recipes',
            'group'     => 'operations',
            'abilities' => [
                'view' => [
                    'name'  => 'recipes.view',
                    'label' => 'Recipes',
                    'help'  => 'Recipes, costings, price classes and SOP exports.',
                ],
            ],
        ],

        'purchasing' => [
            'label'     => 'Purchasing',
            'group'     => 'operations',
            'abilities' => [
                'view' => [
                    'name'  => 'purchasing.view',
                    'label' => 'Purchasing',
                    'help'  => 'Purchase orders and requests, goods receipt, supplier invoices and credit notes.',
                ],
            ],
        ],

        'sales' => [
            'label'     => 'Sales',
            'group'     => 'operations',
            'abilities' => [
                'view' => [
                    'name'  => 'sales.view',
                    'label' => 'Sales',
                    'help'  => 'Sales records, daily closures and sales imports.',
                ],
            ],
        ],

        'inventory' => [
            'label'     => 'Inventory & Kitchen',
            'group'     => 'operations',
            'abilities' => [
                'view' => [
                    'name'  => 'inventory.view',
                    'label' => 'Inventory & Kitchen',
                    'help'  => 'Stock takes, wastage, transfers, staff meals and prep items.',
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
                    'label' => 'HR — Employees & Labour',
                    'help'  => 'Employee records, departments, shifts and labour cost.',
                ],
            ],
        ],

        'hr.attendance' => [
            'label'     => 'HR — Attendance & Service Charge',
            'group'     => 'people',
            'abilities' => [
                'view' => [
                    'name'  => 'hr.attendance',
                    'label' => 'HR — Attendance & Service Charge',
                    'help'  => 'Attendance records and service charge distribution.',
                ],
            ],
        ],

        'hr.claims' => [
            'label'     => 'HR — Overtime Claims',
            'group'     => 'people',
            'abilities' => [
                'view' => [
                    'name'  => 'hr.claims',
                    'label' => 'HR — Overtime Claims',
                    'help'  => 'Submit, review and settle overtime claims.',
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
                    'label' => 'Reports',
                    'help'  => 'The reports hub, report exports and scheduled subscriptions.',
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
                'view' => [
                    'name'  => 'settings.view',
                    'label' => 'Settings',
                    'help'  => 'Company settings: outlets, suppliers, categories, tax rates and more. '
                             . 'One switch covers roughly 40 pages today — Phase 5 splits it per page.',
                ],
            ],
        ],

        'users' => [
            'label'     => 'Users & Access',
            'group'     => 'admin',
            'abilities' => [
                'manage' => [
                    'name'       => 'users.manage',
                    'label'      => 'Manage users',
                    'help'       => 'Create users, set their access, and edit company details.',
                    'managed_by' => 'capability',
                    'note'       => 'Granted by the "Can manage users" capability checkbox, which writes both '
                                  . 'the company_user pivot flag and this permission. Kept out of the module '
                                  . 'grid so the two controls cannot disagree; Phase 1 folds the flag away '
                                  . 'and this becomes an ordinary grid ability.',
                ],
            ],
        ],

    ],
];
