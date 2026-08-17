<?php

namespace App\Livewire\Settings;

use App\Models\CentralKitchen;
use App\Models\CentralPurchasingUnit;
use App\Models\Department;
use App\Models\DocumentFolder;
use App\Models\LmsUser;
use App\Models\Outlet;
use App\Models\OutletGroup;
use App\Models\PoApprover;
use App\Models\ReportSubscription;
use App\Models\TaxRate;
use App\Models\TrainingCertificate;
use App\Models\TrainingCourse;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The settings index, grouped by the module a setting belongs to.
 *
 * The tiles are DATA, not markup. Thirteen hand-written cards had drifted into
 * one flat "General Settings" grid where pay components sat between a kitchen
 * and a tax rate, and adding one meant copying twenty lines of SVG. Defining
 * them here means a new setting is one array entry, the grouping is visible in
 * one place, and every card is laid out identically by construction.
 *
 * Each tile may carry:
 *   'can'   — a permission the user must hold
 *   'when'  — an extra condition already evaluated to a bool
 *   'count' — [n, singular] rendered as "3 branches"
 */
class Index extends Component
{
    /** Show one module only. Slug of a group label, from ?module= . */
    #[\Livewire\Attributes\Url(as: 'module', except: '')]
    public string $module = '';

    public function render()
    {
        $user              = Auth::user();
        $isSystemLevel     = $user->isSystemRole();
        $isBusinessLevel   = $user->canDo('users.manage');

        $groups = $this->filter(
            $isSystemLevel
                ? $this->systemGroups($user)
                : $this->companyGroups($user, $isBusinessLevel),
            $user
        );

        // Slugs are computed from the labels so a group cannot have a link
        // pointing at a name it no longer has.
        $groups = array_map(
            fn (array $g) => $g + ['slug' => \Illuminate\Support\Str::slug($g['label'])],
            $groups
        );

        $all      = $groups;
        $focused  = null;

        if ($this->module !== '') {
            $match = collect($groups)->firstWhere('slug', $this->module);
            // An unknown or unpermitted module falls back to everything rather
            // than to an empty page that looks broken.
            if ($match) {
                $focused = $match;
                $groups  = [$match];
            } else {
                $this->module = '';
            }
        }

        return view('livewire.settings.index', [
            'groups'     => $groups,
            'allGroups'  => $all,
            'focused'    => $focused,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), [
            'title' => $focused ? 'Settings — ' . $focused['label'] : 'Settings',
        ]);
    }

    /**
     * Platform administration. Company-level settings are deliberately absent:
     * a platform admin does not configure someone else's tax rates.
     */
    private function systemGroups($user): array
    {
        return [
            [
                'label' => 'System Administration',
                'note'  => 'Across every company on the platform.',
                'tiles' => [
                    [
                        'label' => 'All Users',
                        'note'  => 'Every account across all companies, with memberships & roles',
                        'route' => 'admin.users',
                        'icon'  => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                        'count' => [User::count(), 'user'],
                        'tone'  => 'gray',
                    ],
                    [
                        'label' => 'Company Health',
                        'note'  => 'Engagement, usage and at-risk accounts',
                        'route' => 'admin.company-health',
                        'icon'  => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
                        'tone'  => 'gray',
                    ],
                    [
                        'label' => 'API Keys',
                        'note'  => 'External integrations & API access',
                        'route' => 'settings.api-keys',
                        'icon'  => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
                        'tone'  => 'gray',
                    ],
                ],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function companyGroups($user, bool $isBusinessLevel): array
    {
        $groups = [];

        if ($isBusinessLevel) {
            $groups[] = [
                'label' => 'Company',
                'note'  => 'Who can get in, and how the business is described.',
                'tiles' => [
                    [
                        'label' => 'Roles & Access',
                        'note'  => 'Who can reach what — people, roles, and why',
                        'route' => 'settings.users',
                        'can'   => 'users.manage',
                        'icon'  => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                        'count' => [User::where('company_id', $user->company_id)->count(), 'user'],
                    ],
                    [
                        'label' => 'Company Details',
                        'note'  => 'Business name, registration and branding',
                        'route' => 'settings.company-details',
                        'can'   => 'users.manage',
                        'icon'  => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
                    ],
                ],
            ];
        }

        // No settings.view gate any more — Phase 5 retired it. Every tile below carries
        // the permission its own route requires, so the page can be opened by
        // anyone who administers ANY module and they see exactly their part of
        // it — which is what makes the per-module Settings links in the sidebar
        // work. A user who administers nothing gets the empty state.
        $groups[] = [
            'label' => 'Organisation',
            'note'  => 'Branches, how they group together, and what applies across them.',
            'tiles' => [
                [
                    'label' => 'Branches',
                    'note'  => 'Manage outlet locations',
                    'route' => 'settings.outlets',
                    'can'   => 'settings.outlets',
                    'icon'  => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
                    'count' => [$user->company_id ? Outlet::where('company_id', $user->company_id)->count() : 0, 'branch'],
                ],
                [
                    'label' => 'Outlet Groups',
                    'note'  => 'Group outlets to tag recipes in bulk',
                    'route' => 'settings.outlet-groups',
                    'can'   => 'settings.outlet_groups',
                    'icon'  => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 100-8 4 4 0 000 8zm6 4a3 3 0 100-6 3 3 0 000 6zM7 14a3 3 0 100-6 3 3 0 000 6z',
                    'count' => [OutletGroup::count(), 'group'],
                ],
                [
                    'label' => 'Tax Rates',
                    'note'  => 'Configure tax rates per country (SST, GST, VAT)',
                    'route' => 'settings.tax-rates',
                    'can'   => 'settings.tax_rates',
                    'icon'  => 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',
                    'count' => [TaxRate::count(), 'rate'],
                ],
            ],
        ];

        $groups[] = [
            'label' => 'HR & People',
            'note'  => 'How staff are grouped, what they are paid, and what they must hold.',
            'tiles' => [
                [
                    'label' => 'Sections',
                    'note'  => 'Employee groups (FOH, BOH, …) for OT claims and duty roster',
                    'route' => 'settings.sections',
                    'can'   => 'settings.sections',
                    'icon'  => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                ],
                [
                    'label' => 'Certifications & Training',
                    'note'  => 'Courses you record against staff, with expiry reminders',
                    'route' => 'settings.certifications',
                    'can'   => 'settings.certifications',
                    'icon'  => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z',
                ],
                [
                    'label' => 'Pay Components',
                    'note'  => 'Allowances, deductions and overtime rates',
                    'route' => 'settings.pay-components',
                    'icon'  => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 9v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                    'can'   => 'hr.compensation',
                ],
                [
                    'label' => 'Statutory Rates',
                    'note'  => 'EPF, SOCSO, EIS and PCB — verify before use',
                    'route' => 'settings.statutory',
                    'icon'  => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                    'can'   => 'hr.compensation',
                ],
                [
                    'label' => 'Employee Particulars',
                    'note'  => 'Sex, nationality, race, religion, marital status, education',
                    'route' => 'settings.employee-particulars',
                    'icon'  => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                    'can'   => 'settings.hr',
                ],
                [
                    'label' => 'Banks',
                    'note'  => 'The list the employee bank picker offers',
                    'route' => 'settings.banks',
                    'icon'  => 'M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11m16-11v11M8 14v3m4-3v3m4-3v3',
                    'can'   => 'hr.compensation',
                ],
                [
                    'label' => 'Leave Types',
                    'note'  => 'Annual, RPH, paternity — and which are paid with salary',
                    'route' => 'settings.leave-types',
                    'icon'  => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                    'can'   => 'settings.hr',
                ],
                [
                    'label' => 'Public Holidays',
                    'note'  => 'The register replacement days are earned from',
                    'route' => 'settings.public-holidays',
                    'icon'  => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
                    'can'   => 'settings.hr',
                ],
                [
                    'label' => 'Leave Approvers',
                    'note'  => 'Fallback for staff with no superior set',
                    'route' => 'settings.leave-approvers',
                    'icon'  => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
                    'can'   => 'settings.hr',
                ],
                [
                    'label' => 'OT Approvers',
                    'note'  => 'Assign who approves overtime claims per outlet',
                    'route' => 'settings.ot-approvers',
                    'can'   => 'settings.ot_approvers',
                    'icon'  => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                ],
                [
                    'label' => 'Clock-In Settings',
                    'note'  => 'Lateness charge, geofence per outlet, face matching',
                    'route' => 'hr.clock-settings',
                    'icon'  => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                    'can'   => 'settings.hr',
                ],
                [
                    'label' => 'Document Folders',
                    'note'  => 'Configure Google Drive folders for company documents',
                    'route' => 'settings.document-folders',
                    'icon'  => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
                    'can'   => 'hr.documents.manage',
                    'count' => [DocumentFolder::count(), 'folder'],
                ],
            ],
        ];

        /*
         * Learning & Development.
         *
         * Its own group rather than two tiles in HR. The Training Portal card
         * moved here from HR & People when the module did — the screen has not
         * changed, only what it is filed under, and its gate moved from
         * `hr.view` to `training.portal` in the same migration so nobody lost
         * access.
         *
         * The slug this group produces — learning-development — is what the
         * sidebar's "Training Settings" link points at. Slugs are computed from
         * the label in render(), so renaming this group renames that link's
         * target too; the link would fall back to showing everything rather
         * than 404, but it would stop being a shortcut.
         */
        $groups[] = [
            'label' => 'Learning & Development',
            'note'  => 'Who can reach the training portal, and what the AI writes quizzes from.',
            'tiles' => [
                [
                    'label' => 'Training Portal',
                    'note'  => 'Approve trainees and choose which outlets\' SOPs each can open',
                    'route' => 'settings.lms-users',
                    'can'   => 'training.portal',
                    'icon'  => 'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z',
                    // Scoped BY HAND, unlike the tiles around it. LmsUser has no
                    // CompanyScope and must not gain one: the login flow looks a
                    // trainee up by email before any company is known, and a
                    // global scope there would make signing in impossible. So a
                    // bare count() here would have counted every trainee on the
                    // platform and shown it to one tenant.
                    'count' => [
                        $user->company_id
                            ? LmsUser::where('company_id', $user->company_id)->count()
                            : 0,
                        'trainee',
                    ],
                ],
                [
                    'label' => 'Courses',
                    'note'  => 'The material quizzes are written from',
                    'route' => 'training.courses',
                    'can'   => 'training.view',
                    'icon'  => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
                    'count' => [TrainingCourse::count(), 'course'],
                ],
                [
                    'label' => 'Certificates',
                    'note'  => 'What has been issued, and what is about to lapse',
                    'route' => 'training.certificates',
                    'can'   => 'training.view',
                    'icon'  => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                    'count' => [TrainingCertificate::count(), 'certificate'],
                ],
            ],
        ];

        $groups[] = [
            'label' => 'Procurement',
            'note'  => 'Who signs off spending, and how orders are routed.',
            'tiles' => [
                [
                    'label' => 'PO Approvers',
                    'note'  => 'Assign who approves purchase orders per outlet',
                    'route' => 'settings.po-approvers',
                    'can'   => 'settings.po_approvers',
                    'icon'  => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                    'count' => [PoApprover::count(), 'assignment'],
                ],
                [
                    'label' => 'Departments',
                    'note'  => 'Ordering departments for purchase orders',
                    'route' => 'settings.departments',
                    'can'   => 'settings.departments',
                    'icon'  => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
                    'count' => [Department::count(), 'department'],
                ],
                [
                    'label' => 'Central Purchasing',
                    'note'  => 'Manage CPU units and assigned staff',
                    'route' => 'settings.cpu-management',
                    'can'   => 'settings.cpu',
                    'icon'  => 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z',
                    'when'  => $user->company?->ordering_mode === 'cpu',
                    'count' => [CentralPurchasingUnit::count(), 'unit'],
                ],
            ],
        ];

        $groups[] = [
            'label' => 'Kitchen & Production',
            'note'  => 'Where food is made and who makes it.',
            'tiles' => [
                [
                    'label' => 'Central Kitchen',
                    'note'  => 'Manage kitchens and assign production staff',
                    'route' => 'settings.kitchen-management',
                    'can'   => 'settings.kitchens',
                    'icon'  => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z',
                    'count' => [CentralKitchen::count(), 'kitchen'],
                ],
            ],
        ];

        $groups[] = [
            'label' => 'Reporting',
            'note'  => 'What gets sent out, to whom, and when.',
            'tiles' => [
                [
                    'label' => 'Scheduled Reports',
                    'note'  => 'Sales, analytics and staff document expiry, by email',
                    'route' => 'settings.reports',
                    'icon'  => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                    'can'   => 'reports.view',
                    'count' => [ReportSubscription::count(), 'subscription'],
                ],
            ],
        ];

        $groups[] = [
            'label' => 'Apps & Downloads',
            'note'  => 'Companion programs for outlet PCs and POS terminals.',
            'tiles' => [
                [
                    // No 'can' on purpose — the zips are public assets and the
                    // person fetching one onto an outlet PC often administers
                    // nothing. Pairing stays behind each tool's own screen.
                    'label' => 'Files & Downloads',
                    'note'  => 'Print agent and POS sync agent installers for Windows',
                    'route' => 'settings.downloads',
                    'icon'  => 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3',
                ],
            ],
        ];

        return $groups;
    }

    /**
     * Drop tiles the user may not see, then drop any group left empty — an
     * empty "Procurement" heading is worse than no heading.
     */
    private function filter(array $groups, $user): array
    {
        return collect($groups)
            ->map(function (array $group) use ($user) {
                $group['tiles'] = collect($group['tiles'])
                    ->filter(fn (array $tile) => ($tile['when'] ?? true)
                        && (! isset($tile['can']) || $user->can($tile['can'])))
                    ->values()
                    ->all();

                return $group;
            })
            ->filter(fn (array $group) => $group['tiles'] !== [])
            ->values()
            ->all();
    }
}
