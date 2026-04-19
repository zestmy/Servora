# Feature Playbook

Step-by-step recipes for common feature work. Skip to the pattern you need.

- [1. Add a new tenant-scoped entity](#1-add-a-new-tenant-scoped-entity)
- [2. Add a new report](#2-add-a-new-report)
- [3. Add a company-level feature toggle](#3-add-a-company-level-feature-toggle)
- [4. Add a new PDF export](#4-add-a-new-pdf-export)
- [5. Add a new Livewire field to an existing form](#5-add-a-new-livewire-field-to-an-existing-form)
- [6. Add a new service for cross-cutting logic](#6-add-a-new-service-for-cross-cutting-logic)
- [7. Add a new workflow stage / status](#7-add-a-new-workflow-stage--status)
- [8. Add a new role or permission](#8-add-a-new-role-or-permission)
- [9. Add an AI-powered feature](#9-add-an-ai-powered-feature)
- [10. Add a scheduled task](#10-add-a-scheduled-task)
- [11. Extend the supplier portal](#11-extend-the-supplier-portal)
- [12. Debug common issues](#12-debug-common-issues)
- [13. Code style & conventions](#13-code-style--conventions)
- [14. Run / test locally](#14-run--test-locally)
- [15. Deploy](#15-deploy)
- [16. Add a cross-outlet role](#16-add-a-cross-outlet-role-process-submissions-from-all-outlets-without-switching)
- [17. Persistent login until explicit logout](#17-persistent-login-until-explicit-logout)
- [18. Remove the outlet switcher (use filters instead)](#18-remove-the-outlet-switcher-use-filters-instead)

---

## 1. Add a new tenant-scoped entity

Goal: a new CRUD for a per-company thing (e.g. "Supplier Notes", "Recipe Tags").

1. **Migration** — `php artisan make:migration create_xxx_table`. Add `company_id` + index, standard timestamps, `softDeletes()` if header-level. Use [2026_03_04_000006_create_ingredients_table](../database/migrations/2026_03_04_000006_create_ingredients_table.php) as a template.
2. **Model** — `php artisan make:model Xxx`. Add:
   ```php
   protected static function booted(): void
   {
       static::addGlobalScope(new \App\Scopes\CompanyScope());
   }
   ```
   Add `$fillable`, `$casts`, and relationships. See [02-domain-model.md](02-domain-model.md).
3. **Livewire Index** — `php artisan make:livewire Xxx/Index`. Patterns: `WithPagination`, `ScopesToActiveOutlet`, filter props + `updatedXxx` → `resetPage()`. Use [Ingredients/Index](../app/Livewire/Ingredients/Index.php) as a reference.
4. **Livewire Form** — `php artisan make:livewire Xxx/Form`. Dual-mode `mount(?int $id = null)`, `rules()`, `save()` method.
5. **View** — under `resources/views/livewire/xxx/`. Use existing layout (`#[Layout('layouts.app')]`).
6. **Route** — add to the auth group in [routes/web.php](../routes/web.php). Gate with `can:xxx.view`.
7. **Permissions** — register in a new migration (follow [2026_03_05_000040_define_roles_and_permissions](../database/migrations/2026_03_05_000040_define_roles_and_permissions.php)); grant to relevant roles.
8. **Sidebar link** — add to the sidebar partial in the layout.
9. **Seeder** (optional) — for demo data.

Checklist: CompanyScope applied? Outlet filter if outlet-level? Permission gated on route? Sidebar link guarded by same permission?

---

## 2. Add a new report

1. **Pick a folder** — `Reports/Purchase/`, `/Order/`, `/Inventory/`, `/InventoryAction/`, `/Menu/`, `/Kitchen/`, or `/Others/`.
2. **Livewire component** — `app/Livewire/Reports/<Area>/<ReportName>.php`. Extend traits:
   ```php
   use WithPagination, ReportFilters, ScopesToActiveOutlet;
   ```
3. **Filters** — `mount()` calls `mountReportFilters()` to default dates to "this month".
4. **Query** — build in `render()` or a dedicated method. Scope by active outlet where relevant.
5. **Export** — `public function exportCsv()` using `$this->exportCsvDownload($filename, $headers, $rows)`.
6. **View** — under `resources/views/livewire/reports/<area>/<report-name>.blade.php`. Import filter partial and table.
7. **Route** — add to the `can:reports.view` block in [routes/web.php](../routes/web.php).
8. **Link from the hub** — [Reports/Hub](../app/Livewire/Reports/Hub.php) and its view.
9. **(Optional) PDF** — add a Blade in [resources/views/pdf/](../resources/views/pdf/) and a controller route.

For cost reports, reuse [CostSummaryService](../app/Services/CostSummaryService.php) — don't duplicate the formula.

---

## 3. Add a company-level feature toggle

1. **Migration** — add a boolean column on `companies` with a default. See [2026_03_12_000073_add_auto_generate_do_to_companies_table](../database/migrations/2026_03_12_000073_add_auto_generate_do_to_companies_table.php) as template.
2. **Model** — add to `$fillable` and cast as `boolean`.
3. **Settings page** — surface in [Settings/CompanyDetails](../app/Livewire/Settings/CompanyDetails.php) (or the appropriate settings tab).
4. **Use site** — read via `auth()->user()->company->feature_flag` in Livewire / services.
5. **If plan-gated** — update the `Plan` record's JSON features and gate the route with `check.feature:<key>`.

Precedent: `require_po_approval`, `auto_generate_do`, `direct_supplier_order`, `ordering_mode`, `show_price_on_do_grn`, `price_alert_threshold`.

---

## 4. Add a new PDF export

1. **Blade** — create under [resources/views/pdf/](../resources/views/pdf/), extending `pdf/layout.blade.php` if suitable. Use existing PDFs as style guide (fonts, partials).
2. **Controller** — `php artisan make:controller XxxPdfController`. Return `Pdf::loadView('pdf.xxx', $data)->download($filename)` using `Barryvdh\DomPDF\Facade\Pdf`.
3. **Route** — add under the correct permission group.
4. **Button** — add download/view link from the relevant Livewire index or show page.

See [PurchaseDocumentPdfController](../app/Http/Controllers/PurchaseDocumentPdfController.php) for a multi-type pattern.

---

## 5. Add a new Livewire field to an existing form

1. **Schema** — migrate the new column.
2. **Model** — update `$fillable` and any casts.
3. **Component** — add a public property, include in `rules()` + `messages()`, load it in `mount()`, save in `save()`.
4. **View** — add the input. Keep Tailwind classes consistent with existing inputs.
5. **Index / detail views** — surface the value if useful (filter, column, PDF).
6. **Tests** — if the project has tests for the module, extend them (`tests/Feature/...`).

---

## 6. Add a new service for cross-cutting logic

Use a service when logic is:
- shared by >1 Livewire component,
- involves multiple tables or external APIs,
- has its own audit/side-effects.

1. Create `app/Services/XxxService.php`. Prefer static methods unless you need DI.
2. Wrap multi-write operations in `DB::transaction`.
3. Emit audit logs (`OrderAdjustmentLog`, `IngredientPriceHistory`, `AiAnalysisLog`, `AuditLog`) when the action changes state.
4. Document in [04-services.md](04-services.md) — name, purpose, methods, callers, notes.

---

## 7. Add a new workflow stage / status

Example: add a "ready_for_pickup" status to PurchaseOrder.

1. Grep for the current status values (e.g. `'draft', 'sent', 'partial', 'received', 'cancelled'`). Status strings typically live in Livewire components, not DB enums.
2. Add the new value everywhere the set is used: status filters, transition methods, Blade view classes, PDFs.
3. Update any status-based queries (`->where('status', 'sent')`) — search for these.
4. Update [05-workflows.md](05-workflows.md) with the new step and any transitions.
5. Add migration if you also want to track additional columns (e.g. a `readied_at` timestamp).

---

## 8. Add a new role or permission

1. **Migration** — define the role and permission in a new migration (template: [2026_03_08_000049_create_po_approvers_and_fix_roles](../database/migrations/2026_03_08_000049_create_po_approvers_and_fix_roles.php)).
2. **Route gate** — use `middleware('can:xxx')` or `middleware('role:Role Name')`.
3. **Sidebar** — guard the link with `@can('xxx')`.
4. **Document in [01-architecture.md](01-architecture.md)** — update the roles & permissions tables.

Naming: use singular-noun or `module.action` style (`hr.view`, `purchasing.view`, `users.manage`).

---

## 9. Add an AI-powered feature

1. **Pick a service pattern**:
   - Text analysis → [AiAnalyticsService](../app/Services/AiAnalyticsService.php).
   - Invoice/PDF extraction → [AiInvoiceExtractionService](../app/Services/AiInvoiceExtractionService.php).
   - OCR → [VisionService](../app/Services/VisionService.php).
2. **Cache results** — expensive. See `AiAnalyticsService::analyze` for caching by prompt hash.
3. **Log outputs** — write to `AiAnalysisLog` or `AiInvoiceScan` so you can audit (and A/B compare).
4. **Plan-gate** — add `check.feature:<key>` to the route. Define the feature on `Plan`.
5. **Fallback** — always handle API errors gracefully. Never block the primary workflow on the AI call.

---

## 10. Add a scheduled task

1. Define a console command: `php artisan make:command XxxCommand`.
2. Schedule it in [routes/console.php](../routes/console.php) (Laravel 12 pattern).
3. On prod, the systemd `cron` runs `php artisan schedule:run` every minute — see `deploy/install.sh`.

Current schedules include: `UsageTrackingService::snapshotAll`, `PriceMonitoringService::autoDetectChanges`, `PriceMonitoringService::checkAlerts`.

---

## 11. Extend the supplier portal

Supplier portal routes live under `/supplier/*` with a separate `supplier` guard. To add a screen:

1. Create Livewire under `app/Livewire/Supplier/Xxx.php`.
2. Add view under `resources/views/livewire/supplier/xxx.blade.php` using `#[Layout('layouts.supplier')]`.
3. Add route inside the `SupplierAuthenticate` middleware group in [routes/web.php](../routes/web.php).
4. Scope data by `auth('supplier')->user()->supplier_id` — don't rely on CompanyScope.

---

## 12. Debug common issues

- **Queries returning nothing** — CompanyScope may be filtering by a user with no `company_id`. Check auth state; use `withoutGlobalScope(CompanyScope::class)` in admin contexts.
- **Outlet-scoped lists empty** — user has no active outlet. Check `OutletSwitcher`.
- **"Subscription expired" banner** — caused by [EnforceSubscription](../app/Http/Middleware/EnforceSubscription.php). For local dev, either seed an active `Subscription` or mark the company grandfathered.
- **PO email not sent** — verify `EngineMailerService` API key in `.env`. Service returns HTTP 200 even on errors — check `StatusCode` in the response body.
- **Price history not updating** — Price Watcher import flows through [Ingredients/ReviewDocument](../app/Livewire/Ingredients/ReviewDocument.php); GRN flow through [GrnReceiveForm](../app/Livewire/Purchasing/GrnReceiveForm.php). Both call into services that write `IngredientPriceHistory`.

---

## 13. Code style & conventions

- **Names uppercased** — `Ingredient` and `Recipe` `saving()` hooks uppercase the `name` column. Don't lowercase-match — the DB stores uppercase.
- **Money** — `decimal(12,2)` for totals, `decimal(12,4)` for unit costs. Always cast via `$casts`.
- **Currency display** — `Money::format($value, $company->currency)` helper if it exists; otherwise format in view.
- **Dates** — user-entered dates are in the user's timezone (see `users.timezone`); DB stores UTC. `SetDisplayTimezone` middleware handles display conversion.
- **Never write comments that describe WHAT code does** — let the name speak. Comments for WHY only.
- **Prefer services over fat Livewire** — if a flow touches >2 tables or has side-effects, it belongs in a service.

---

## 14. Run / test locally

```bash
# One-shot setup
composer setup

# All four dev processes concurrently (server, queue, logs, vite)
composer dev

# Run tests
php artisan test

# Lint (Pint)
./vendor/bin/pint
```

Default login after seeding:
- Email: `admin@servora.test`
- Password: `password`
- Role: Super Admin

---

## 15. Deploy

Initial deploy: `bash deploy/install.sh` on a fresh Ubuntu droplet. Updates: `bash deploy/update.sh`.

Rollback tips in [../README.md](../README.md#troubleshooting).

---

## 16. Add a cross-outlet role (process submissions from all outlets without switching)

Goal: a role whose users see — and can action — records from **every outlet** in the company without needing to use the outlet switcher. Use this for HR (approve OT claims company-wide), centralised Purchasing (process POs from all branches), Finance (reconcile invoices across outlets), etc.

The foundation already exists — the pattern is:

1. **The `can_view_all_outlets` capability is the switch.** It's a boolean column on `users` (see [User](../app/Models/User.php) `$fillable`). `User::canViewAllOutlets()` returns `true` when this flag is set OR the user has a system role (Super Admin / System Admin). Business Manager / Operations Manager roles already flip it on via seeders — extend the pattern for new roles.
2. **The `ScopesToActiveOutlet` trait auto-disables when no outlet is active.** In [app/Traits/ScopesToActiveOutlet.php](../app/Traits/ScopesToActiveOutlet.php), `scopeByOutlet($query)` only applies a filter when `activeOutletId()` returns a value. If the user's session outlet is `null` (the "All Outlets" selection), the listing query is unscoped and returns all outlets.
3. **Approval matrices (`PoApprover`, `PrApprover`, `OvertimeClaimApprover`) accept a null scope.** Seed approver rows with `outlet_id = null` (and optionally `department_id = null` / `section_id = null`) to wildcard an approver across outlets. See [02-domain-model.md](02-domain-model.md) for the columns.

### Step-by-step

1. **Decide the name and permission.** e.g. "HR Manager" with `hr.view`, or "Central Purchasing" with `purchasing.view` + `can_receive_grn` + `can_manage_invoices`.
2. **Create a migration** modelled on [2026_03_08_000049_create_po_approvers_and_fix_roles](../database/migrations/2026_03_08_000049_create_po_approvers_and_fix_roles.php):
   - `Role::firstOrCreate(['name' => 'HR Manager'])`.
   - Attach permissions (`hr.view`, etc.).
   - For existing users that should become cross-outlet, set `can_view_all_outlets = true` via a data migration.
3. **Default the flag on when creating users in this role.** In [Settings/Users](../app/Livewire/Settings/Users.php), when the selected role is one of the cross-outlet roles, force-set `can_view_all_outlets = true` on save. (Alternative: register an `Eloquent` observer on `User` that syncs the flag from role.)
4. **Ensure every index the role uses works unscoped.** Most index components already do. For any that filter hard-coded by `outlet_id`, replace with:
   ```php
   use ScopesToActiveOutlet;

   $query = OvertimeClaim::query();
   $this->scopeByOutlet($query);   // no-op when session outlet is null
   ```
   Concrete targets for the three named scenarios:
   - **HR OT approvals** — [Hr/OvertimeClaims](../app/Livewire/Hr/OvertimeClaims.php). Verify the query uses `scopeByOutlet` (not a hard `->where('outlet_id', $user->outlet_id)`).
   - **Purchasing POs / DOs / GRNs / Invoices** — [Purchasing/Index](../app/Livewire/Purchasing/Index.php), [Purchasing/InvoiceIndex](../app/Livewire/Purchasing/InvoiceIndex.php). Same check.
   - **Approver matrix** — in `Settings/OtApprovers` / `Settings/PoApprovers`, let admins seed an approver row with `outlet_id = null` to act across outlets.
5. **Ensure the OutletSwitcher still shows the "All Outlets" option.** [OutletSwitcher](../app/Livewire/OutletSwitcher.php) already calls `$user->canViewAllOutlets()` — the option will render automatically once the flag is set.
6. **Default the session outlet to null for these users.** In [Providers/AppServiceProvider](../app/Providers/) or on login, if `$user->canViewAllOutlets()` and no outlet has been chosen this session:
   ```php
   if (!session()->has('active_outlet_id')) {
       session(['active_outlet_id' => null]);
   }
   ```
   This removes the need to click the switcher at all on first login.

> **Caveat on `User::activeOutletId()`**: the current implementation falls back to the first assigned outlet when the session key isn't set. For a pure cross-outlet role without any outlet assignments, the "All Outlets" state is represented by an explicit `session(['active_outlet_id' => null])`. The condition `if ($sessionId && …)` short-circuits on null, so the fallback only kicks in when the session is genuinely unset. Set the session key explicitly on login (step 6) to avoid the edge case.

7. **Sidebar & dashboard widgets.** Gate links on the permission, not outlet context. Example: OT approvals link should appear whenever `@can('hr.view')`, regardless of which outlet (if any) is active.
8. **Update [01-architecture.md](01-architecture.md)** — add the new role to the Roles table and (if new) the permission to the permissions table.

### Worked example: "HR Manager — approve OT claims from all outlets"

```php
// Migration (sketch)
public function up(): void
{
    $role = Role::firstOrCreate(['name' => 'HR Manager', 'guard_name' => 'web']);
    $role->givePermissionTo(['hr.view']);

    // Backfill the capability for existing HR Managers
    User::role('HR Manager')->update(['can_view_all_outlets' => true]);

    // Make every HR Manager a wildcard OT approver
    User::role('HR Manager')->get()->each(function ($u) {
        OvertimeClaimApprover::firstOrCreate([
            'company_id' => $u->company_id,
            'user_id'    => $u->id,
            'outlet_id'  => null,
            'section_id' => null,
        ]);
    });
}
```

In [Hr/OvertimeClaims](../app/Livewire/Hr/OvertimeClaims.php) the listing query should already look like:
```php
$query = OvertimeClaim::query()->with('employee', 'outlet', 'section');
$this->scopeByOutlet($query);
```
When an HR Manager has `session('active_outlet_id') === null`, no outlet filter is applied → they see claims from every outlet in the company.

### Checklist

- [ ] New role migration committed
- [ ] `can_view_all_outlets` defaulted on role creation/assignment
- [ ] Index queries use `ScopesToActiveOutlet::scopeByOutlet` (no hard outlet filter)
- [ ] Approver matrix seeded with `outlet_id = null` rows
- [ ] Session outlet defaults to `null` on first login for cross-outlet users
- [ ] Sidebar links gated on permission, not outlet presence
- [ ] `01-architecture.md` roles/permissions tables updated

---

## 17. Persistent login until explicit logout

Goal: user logs in once and stays logged in across browser restarts and days of idle time — until they explicitly hit Logout.

Laravel already supports this via the `remember_token` cookie. The pieces:

1. **`Auth::attempt($credentials, $remember)`** — when `$remember = true`, Laravel issues a long-lived remember cookie tied to the user's `remember_token` column.
2. **Session lifetime** — set by [config/session.php](../config/session.php) from `SESSION_LIFETIME` env var. Currently `120` minutes. Even with a remember cookie, session rotation is capped by this.
3. **`expire_on_close`** — when `true`, the session cookie dies when the browser closes. Currently `false`.

### Implementation

1. **Default the login form's `$remember` to `true`.** In [app/Livewire/Forms/LoginForm.php](../app/Livewire/Forms/LoginForm.php):
   ```php
   public bool $remember = true;   // was: false
   ```
   Optionally remove the checkbox from the view or leave it as an opt-out.
2. **Extend session lifetime** in `.env`:
   ```
   SESSION_LIFETIME=43200          # 30 days in minutes
   SESSION_EXPIRE_ON_CLOSE=false
   ```
   On prod droplets, edit `/var/www/servora/.env` and run `php artisan config:cache`.
3. **Verify the `users` table has a `remember_token` column.** Laravel's default migration includes it ([2026_03_04 users migration or earlier]). Grep `remember_token` if unsure — if missing, add via a new migration:
   ```php
   Schema::table('users', fn (Blueprint $t) => $t->rememberToken());
   ```
4. **Don't conflict with subscription gating.** [EnforceSubscription](../app/Http/Middleware/EnforceSubscription.php) doesn't log the user out — expired companies still stay signed in, just read-only. No change needed here.
5. **Logout flow — leave as-is.** Breeze's logout route invalidates the session and regenerates the token, killing both the session cookie and the remember cookie. This ensures "explicit logout" always works.

### Caveats

- **Security trade-off.** A 30-day remember cookie means a stolen device stays signed in for 30 days. Mitigate by exposing "Log out of all devices" in [profile](../resources/views/profile/) — regenerate the `remember_token`:
  ```php
  Auth::user()->update(['remember_token' => Str::random(60)]);
  Auth::logoutOtherDevices($currentPassword);
  ```
- **Re-auth for sensitive actions.** Consider `middleware('password.confirm')` on `Settings/Users`, `Settings/ApiKeys`, and `billing.*` so a stale remembered session can't drain a company. Laravel's confirmation prompt expires after `AUTH_PASSWORD_TIMEOUT` seconds ([config/auth.php](../config/auth.php) — currently `10800` / 3 hours).
- **Supplier and affiliate guards.** These have their own login forms under [Supplier\AuthController](../app/Http/Controllers/Supplier/) and [Affiliate\AuthController](../app/Http/Controllers/Affiliate/). Repeat steps 1-3 for each if you want persistent login across all portals; otherwise only `web` guard users get it.
- **Test in staging before prod.** A misconfigured `SESSION_LIFETIME` applied mid-session can log every user out. Deploy during a low-traffic window.

### Checklist

- [ ] `LoginForm::$remember` defaults to `true`
- [ ] `SESSION_LIFETIME` raised in `.env` (and `php artisan config:cache` run)
- [ ] `SESSION_EXPIRE_ON_CLOSE=false` confirmed
- [ ] `users.remember_token` column exists
- [ ] Sensitive routes wrapped in `password.confirm` middleware
- [ ] "Log out of all devices" UI exposed in profile (optional but recommended)
- [ ] Staging smoke-tested before prod rollout

---

## 18. Remove the outlet switcher (use filters instead)

Goal: drop the "switch active outlet" UI and session, replace it with per-page outlet filters on listings and an explicit outlet picker on forms. Users always see everything they have access to; they narrow results with a filter dropdown instead of globally flipping their scope.

### Does this leak data across outlets?

**No.** Every listing is still capped to the user's accessible outlets via `availableOutletIds()` (see step 3 — pulls from the `outlet_user` pivot, or all outlets for users with `can_view_all_outlets`). `CompanyScope` still isolates tenants. `canAccessOutlet()` still gates per-record actions and approvals.

The only behavioural change is for users assigned to **multiple** outlets:

| User | Before (switcher) | After (filters) |
|------|-------------------|------------------|
| Assigned to Outlet A only | Sees A | Sees A (unchanged) |
| Assigned to Outlets A + B | Sees A *or* B (switch to toggle) | Sees A + B by default; filter to narrow |
| `can_view_all_outlets` | Sees one outlet *or* "All" | Sees all outlets; filter to narrow |

### What actually breaks vs. what stays

Survey results from the current codebase (grep these yourself before starting):

| Surface | Files | What to do |
|---------|-------|------------|
| Switcher UI | [resources/views/profile.blade.php:9](../resources/views/profile.blade.php), [OutletSwitcher](../app/Livewire/OutletSwitcher.php) | **Delete.** Only rendered on the profile page today. |
| Sidebar active-outlet display | [resources/views/layouts/app.blade.php:374-516](../resources/views/layouts/app.blade.php) | **Delete** the `$activeOutletName` block. |
| `scopeByOutlet` index listings | 30 Livewire files under `app/Livewire/{Dashboard,Purchasing,Sales,Inventory,Reports,Analytics}` | **Convert** — replace `$this->scopeByOutlet($q)` with a filter-driven `$q->when($this->outletFilter, fn($q) => $q->whereIn('outlet_id', $this->outletFilter))`. |
| Listings that hard-code `where('outlet_id', activeOutletId())` | [Hr/OvertimeClaims.php:312/362/375](../app/Livewire/Hr/OvertimeClaims.php) (others may exist — `grep -rn "where('outlet_id'.*activeOutletId"`) | **Convert** — replace with `whereIn('outlet_id', $this->availableOutletIds())`, then narrow by `$outletFilter` if set. Otherwise these regress from "current outlet" to "only first assigned outlet", which silently hides data from multi-outlet users. [Hr/Employees.php:444](../app/Livewire/Hr/Employees.php) already does this correctly — copy that pattern. |
| Form prefill | 25 files calling `activeOutletId()` — e.g. [OrderForm](../app/Livewire/Purchasing/OrderForm.php), [TransferForm](../app/Livewire/Inventory/TransferForm.php), [PurchaseRequestForm](../app/Livewire/Purchasing/PurchaseRequestForm.php), [Sales/Import](../app/Livewire/Sales/Import.php) | **Keep working** — repurpose `activeOutletId()` to mean "user's default outlet for form prefill" (first assigned outlet). Still prefill the dropdown, but make it a required user choice. |
| `ScopesToActiveOutlet` trait | [app/Traits/ScopesToActiveOutlet.php](../app/Traits/ScopesToActiveOutlet.php) | **Retire** in step 5, after every component is migrated. |
| Approver matrices (`PoApprover`, `PrApprover`, `OvertimeClaimApprover`) | unchanged | No change — still outlet-keyed; approver sees records their approver-row matches. |

**Approach:** incremental, not big-bang. The session key and the trait can coexist with the new filters during the migration — existing scoping quietly no-ops as components are ported.

### Migration plan

1. **Stop writing the session key.** In [OutletSwitcher::switchOutlet](../app/Livewire/OutletSwitcher.php), short-circuit or delete the component. Also remove the invocation in [profile.blade.php](../resources/views/profile.blade.php). Any lingering reads of `session('active_outlet_id')` will now always return null.
2. **Make `scopeByOutlet` a no-op transitionally.** Change [app/Traits/ScopesToActiveOutlet.php](../app/Traits/ScopesToActiveOutlet.php):
   ```php
   protected function scopeByOutlet(Builder $query, string $column = 'outlet_id'): Builder
   {
       return $query;   // no-op — use explicit $outletFilter on each component
   }
   ```
   This is the safety net: every index that hasn't been migrated yet silently shows all outlets (with the outlet column rendered so the user can see what's what). Existing permission checks still apply — users never see outlets they can't access because `canAccessOutlet()` and approver matrices gate the *actions*.
3. **Add a shared outlet filter to listings.** Most index components already have a `$outletFilter` via the [ReportFilters](../app/Traits/ReportFilters.php) trait (single int). Promote it to multi-select:
   ```php
   public array $outletFilter = [];     // empty = show all accessible

   public function updatedOutletFilter(): void { $this->resetPage(); }

   protected function scopeToSelectedOutlets(Builder $q, string $column = 'outlet_id'): Builder
   {
       $ids = $this->availableOutletIds();
       $q->whereIn($column, $ids);                         // always limit to accessible
       if (!empty($this->outletFilter)) {
           $q->whereIn($column, $this->outletFilter);      // then narrow by user choice
       }
       return $q;
   }

   protected function availableOutletIds(): array
   {
       $user = auth()->user();
       return $user->canViewAllOutlets()
           ? Outlet::where('company_id', $user->company_id)->pluck('id')->all()
           : $user->outlets()->pluck('outlets.id')->all();
   }
   ```
   Add to `ReportFilters` trait so every report inherits it. Replace each `$this->scopeByOutlet($q)` call with `$this->scopeToSelectedOutlets($q)` (or the appropriate column, e.g. `from_outlet_id`).
4. **Render the filter widget.** Build a reusable Blade partial `resources/views/components/outlet-filter.blade.php` with a multi-select (checkbox list in a dropdown). Drop into every index view above the table. For single-outlet users (one assigned outlet and `!canViewAllOutlets()`), hide the filter entirely — filtering is pointless.
5. **Display the outlet column.** Every listing now spans multiple outlets, so users need to see which row belongs to which outlet. Add a `Outlet` column to:
   - [Purchasing/Index](../app/Livewire/Purchasing/Index.php) and child tabs
   - [Sales/Index](../app/Livewire/Sales/Index.php)
   - [Inventory/Index](../app/Livewire/Inventory/Index.php) (each tab)
   - [Reports/*](../app/Livewire/Reports/)
   - [Hr/OvertimeClaims](../app/Livewire/Hr/OvertimeClaims.php) and [Hr/Employees](../app/Livewire/Hr/Employees.php)
6. **Forms: turn prefill into an explicit pick.** Components that currently call `activeOutletId()` to default the outlet (e.g. `OrderForm::$outlet_id`) should:
   - Keep the line `public int $outlet_id = 0;` then `$this->outlet_id = auth()->user()->activeOutletId() ?? 0` in `mount()` — this prefills but doesn't force.
   - Render the existing outlet `<select>` as a **required** field. Most forms already have this; audit each and make sure it's visible.
   - Validate `outlet_id` is in the user's accessible outlet list (`canAccessOutlet`).
7. **Repurpose `User::activeOutletId()`.** Rename for clarity if you want; semantics become "user's preferred default outlet for new forms". Implementation drops the session read:
   ```php
   public function activeOutletId(): ?int
   {
       return $this->outlets()->orderBy('pivot_created_at')->value('outlets.id');
   }
   ```
   Keep the name for compatibility — 25 callers depend on it. Once callers all use the new filter-based pattern, you can rename to `defaultOutletId()` in a follow-up PR.
8. **Dashboard & Analytics.** [Dashboard](../app/Livewire/Dashboard.php) and [Analytics/Index](../app/Livewire/Analytics/Index.php) currently scope KPIs by active outlet. Two choices:
   - **A.** Aggregate across all accessible outlets (simpler, what most users want post-switcher).
   - **B.** Add an outlet filter dropdown to each, defaulting to "All outlets".
   Pick A unless users push back; it matches the spirit of removing the switcher.
9. **Workspace modes (kitchen).** The `/workspace/kitchen` switcher uses a different session key (`workspace_mode`, `active_kitchen_id`) and is orthogonal to outlets. **Leave it alone** — kitchen mode is not an outlet choice.
10. **Delete the switcher.** After all indexes are migrated and shipped, remove:
    - [app/Livewire/OutletSwitcher.php](../app/Livewire/OutletSwitcher.php)
    - [resources/views/livewire/outlet-switcher.blade.php](../resources/views/livewire/outlet-switcher.blade.php)
    - `@livewire('outlet-switcher')` in [profile.blade.php](../resources/views/profile.blade.php)
    - The `$activeOutletName` block in [layouts/app.blade.php](../resources/views/layouts/app.blade.php)
    - (Optionally) the `ScopesToActiveOutlet` trait and the 30 `use ScopesToActiveOutlet;` lines.
11. **Seed-data clean-up (optional).** The `active_outlet_id` session key is no longer read. No DB migration needed — it just becomes a dead session value that ages out with the session.

### What does NOT need to change

- **Approver matrices** — `PoApprover`, `PrApprover`, `OvertimeClaimApprover` are outlet-keyed in the DB, not via session. Approvers still see only records their approver row covers (wildcard with `outlet_id = null` if needed — see §16).
- **`canAccessOutlet()` / `canViewAllOutlets()`** — `canViewAllOutlets()` is the authority for "which outlets can this user even see"; `canAccessOutlet($id)` is the per-record gate for actions. After this refactor, listings are capped to `availableOutletIds()` (step 3) so **records from outlets the user isn't assigned to never appear in the list in the first place** — no post-query filtering, no "hide on action" hack. `canAccessOutlet()` is still the second line of defence for URL-tampering on individual record routes (e.g. someone crafts `/purchasing/orders/999/edit` for an outlet they aren't assigned to — the route handler rejects it).
- **`CompanyScope`** — untouched. Tenant isolation is still bulletproof.
- **Services** — grep shows `app/Services/RfqService.php` reads `activeOutletId()` once for a default; leave `activeOutletId()` semantics (step 7) so it still resolves to a sensible value.
- **`PurchaseRequest` model** — uses `scopeByOutlet` internally for one query; the no-op transition (step 2) keeps it working.
- **PDF controllers** ([OtClaimPdfController](../app/Http/Controllers/OtClaimPdfController.php)) — reads `activeOutletId()`; covered by step 7.

### Order to ship in

Split into PRs to keep each reviewable and reversible:

1. **PR 1 — Safety net.** Steps 1-2: stop writing the session, neuter `scopeByOutlet`. Ship to staging, verify nothing 500s. Output of every index should be unchanged for users with one outlet and broader for users with multiple.
2. **PR 2 — Filter + column.** Steps 3-5: add the filter trait + widget + outlet column, migrate the high-traffic indexes first (Dashboard, Purchasing/Index, Sales/Index, Inventory/Index).
3. **PR 3 — Long tail.** Steps 5 rest: migrate every report + HR + analytics.
4. **PR 4 — Forms.** Step 6: audit and enforce the outlet `<select>` as required everywhere.
5. **PR 5 — Prune.** Steps 7, 10, 11: delete dead code.

### Caveats

- **Don't silently change what users see.** Today, a Branch Manager logged into Outlet A sees only Outlet A. After this change they see every outlet they're assigned to by default. Warn users in the release notes; the filter makes it easy to reproduce the old view by selecting a single outlet.
- **Performance.** Cross-outlet queries on big tables (SalesRecordLine, StockTakeLine, IngredientPriceHistory) will return more rows. Verify indexes on `outlet_id` exist and pagination is in place on every index.
- **Tests.** Grep `tests/` for `active_outlet_id` and `switchOutlet` — update or remove any integration tests that assumed the session-based flow.
- **Compatibility window for cached sessions.** After step 1, existing sessions still have `active_outlet_id` set. That's fine because nothing reads it anymore. No user action required.

### Checklist

- [ ] PR 1: `OutletSwitcher::switchOutlet` no longer writes session; `scopeByOutlet` is a no-op
- [ ] PR 2: `scopeToSelectedOutlets` helper in `ReportFilters`, multi-select Blade component, top 4 indexes migrated
- [ ] PR 3: all remaining indexes show outlet column + filter
- [ ] PR 4: every form's outlet picker is required + validated via `canAccessOutlet`
- [ ] PR 5: `OutletSwitcher` component/view deleted, sidebar display removed, `ScopesToActiveOutlet` trait removed
- [ ] `activeOutletId()` documented as "preferred default" (or renamed `defaultOutletId()`)
- [ ] Release notes communicate the behavioural change to users
- [ ] Staging smoke test: multi-outlet user sees all assigned outlets; single-outlet user sees unchanged behaviour; Branch Manager can't action a record outside their approver scope
- [ ] [docs/01-architecture.md](01-architecture.md) multi-outlet section updated
- [ ] [docs/03-modules.md](03-modules.md) updated (drop OutletSwitcher entry)
