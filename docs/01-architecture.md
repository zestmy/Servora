# Architecture

Servora is a multi-tenant Laravel 12 + Livewire 3 SaaS. This doc covers tenancy, auth, middleware, and layout mechanics. For feature flow, see [05-workflows.md](05-workflows.md).

---

## High-level

```
┌───────────────────────────────────────────────────────────────┐
│  Routes (routes/web.php, routes/auth.php, routes/lms.php)     │
│    → Middleware stack (auth, company.scope, subscription, ..) │
│    → Livewire components (app/Livewire/)                      │
│    → Services (app/Services/) — reusable business logic       │
│    → Eloquent models (app/Models/) — data + CompanyScope      │
│    → MySQL 8                                                  │
└───────────────────────────────────────────────────────────────┘
```

Laravel bootstrap: [bootstrap/app.php](../bootstrap/app.php) registers middleware aliases and groups. Routes for the LMS sub-app are loaded separately via the `then:` callback.

---

## Multi-tenancy

### `CompanyScope` global scope
Source: [app/Scopes/CompanyScope.php](../app/Scopes/CompanyScope.php).

Applied in a model's `booted()` method:
```php
protected static function booted(): void
{
    static::addGlobalScope(new CompanyScope());
}
```

The scope picks `company_id` from:
1. The authenticated `web` guard user, OR
2. The authenticated `lms` guard user, OR
3. A subdomain-resolved `currentCompany` (bound by [ResolveCompanyFromSubdomain](../app/Http/Middleware/ResolveCompanyFromSubdomain.php)).

Models that apply it include `Ingredient`, `Recipe`, `Supplier`, all purchasing/inventory/sales tables, HR, kitchen, AI logs. See [02-domain-model.md](02-domain-model.md) for the full list.

> **Bypassing the scope** — for admin/cross-company queries (e.g. `Admin/Subscriptions`), use `Model::withoutGlobalScope(CompanyScope::class)` or `withoutGlobalScopes()`.

### `EnsureCompanyScope` middleware
[app/Http/Middleware/EnsureCompanyScope.php](../app/Http/Middleware/EnsureCompanyScope.php).

Gates every authenticated request — if the user has no `company_id` and isn't a System Admin, they're bounced back to login. Registered as alias `company.scope` in [bootstrap/app.php](../bootstrap/app.php) and applied to the main auth group in [routes/web.php](../routes/web.php) line 153.

### System Admin bypass
System Admins have `company_id = null`. `EnsureCompanyScope` lets them through without a company; `EnforceSubscription`, `EnforceSubscription`, and `CheckFeatureAccess` also short-circuit for them. Their queries must manually scope `company_id` when inspecting tenant data.

---

## Multi-outlet

A single company has many outlets. Users are assigned to outlets via the `outlet_user` pivot table ([migration 2026_03_07_000041](../database/migrations/2026_03_07_000041_create_outlet_user_table.php)).

### Active outlet
Stored in the session under `active_outlet_id`. The [OutletSwitcher](../app/Livewire/OutletSwitcher.php) component in the layout sets it. Business Manager / Super Admin / System Admin have an "All Outlets" option (null outlet).

Helper on [User](../app/Models/User.php): `activeOutletId(): ?int`.

### `ScopesToActiveOutlet` trait
[app/Traits/ScopesToActiveOutlet.php](../app/Traits/ScopesToActiveOutlet.php).

Used by Livewire index components to filter list queries by the session outlet:
```php
use ScopesToActiveOutlet;

$query = PurchaseOrder::query();
$this->scopeByOutlet($query);            // default column "outlet_id"
$this->scopeByOutlet($query, 'from_outlet_id');
```

When `activeOutletId()` is null (all-outlets view), no filter is applied.

### `ReportFilters` trait
[app/Traits/ReportFilters.php](../app/Traits/ReportFilters.php).

Shared state for report Livewire components: `$dateFrom`, `$dateTo`, `$outletFilter`, `$supplierFilter`. Provides `getOutlets()`, `getSuppliers()`, `exportCsvDownload()`. Initializes dates in `mountReportFilters()`.

### Workspace modes
Users can switch between **outlet** and **kitchen** workspaces via `/workspace/{mode}` (see [routes/web.php](../routes/web.php) line 214). The session key `workspace_mode` and `active_kitchen_id` drive which sidebar layout renders. Kitchen workspace is gated by the `kitchen.user` middleware → [EnsureKitchenUser](../app/Http/Middleware/EnsureKitchenUser.php).

---

## Auth guards

Defined in [config/auth.php](../config/auth.php):

| Guard | Provider | Model | Purpose |
|-------|----------|-------|---------|
| `web` (default) | `users` | [User](../app/Models/User.php) | Main app users (company staff + admins) |
| `lms` | `lms_users` | [LmsUser](../app/Models/LmsUser.php) | Training/LMS portal |
| `supplier` | `supplier_users` | [SupplierUser](../app/Models/SupplierUser.php) | Supplier portal (`/supplier/*`) |
| `affiliate` | `affiliates` | [Affiliate](../app/Models/Affiliate.php) | Affiliate portal (`/affiliate/*`) |

Each portal has its own login/register routes in [routes/web.php](../routes/web.php) and uses a matching middleware (e.g. [SupplierAuthenticate](../app/Http/Middleware/SupplierAuthenticate.php)).

---

## Roles & permissions

Built on **Spatie Laravel Permission**. Defined in migrations [2026_03_05_000040_define_roles_and_permissions](../database/migrations/2026_03_05_000040_define_roles_and_permissions.php), [2026_03_08_000049_create_po_approvers_and_fix_roles](../database/migrations/2026_03_08_000049_create_po_approvers_and_fix_roles.php), and subsequent permission migrations.

### Roles

| Role | Scope |
|------|-------|
| Super Admin | Full system access, all companies |
| System Admin | Full system access, all companies (no `company_id`) |
| Company Admin | Full access within one company |
| Business Manager | All outlets in company, analytics & reports |
| Operations Manager | Multi-outlet operations |
| Branch Manager | Assigned outlets, PO approval |
| Chef | Assigned outlets, recipes, inventory |
| Purchasing | Cross-outlet purchasing view |
| Finance | Financial reports & summaries |
| Staff | Assigned outlets only, limited modules |

### Gate permissions

Applied on routes as `middleware('can:permission.name')`:

| Permission | Guards |
|------------|--------|
| `ingredients.view` | `/ingredients/*` |
| `recipes.view` | `/recipes/*` |
| `purchasing.view` | `/purchasing/*`, `/settings/suppliers`, `/settings/form-templates`, `/settings/supplier-mapping`, `/settings/price-alerts` |
| `sales.view` | `/sales/*`, `/settings/sales-targets` |
| `inventory.view` | `/inventory/*` |
| `reports.view` | `/reports/*`, `/analytics` |
| `settings.view` | `/settings`, `/settings/categories`, `/settings/recipe-categories`, `/settings/price-classes`, `/settings/sales-categories`, `/settings/outlets`, `/settings/po-approvers`, `/settings/calendar-events`, `/settings/departments`, `/settings/sections`, `/settings/par-levels`, `/settings/outlet-groups`, `/settings/cpu-management`, `/settings/kitchen-management`, `/settings/tax-rates`, `/settings/ot-approvers` |
| `users.manage` | `/settings/users`, `/settings/company-details` |
| `hr.view` | `/hr/*`, `/settings/labour-costs`, `/settings/lms-users`, `/training/sop/*` |

Role-specific middleware:
- `role:System Admin` — `/admin/*` (via `\Spatie\Permission\Middleware\RoleMiddleware`).
- `role:Super Admin|System Admin` — `/settings/api-keys`.

---

## Subscription & feature gating

The app is a SaaS. All in-app routes run through [EnforceSubscription](../app/Http/Middleware/EnforceSubscription.php):

- **Active / trialing** — full access. Warning banner shown when trial has ≤ 3 days remaining.
- **Expired / cancelled / past due** — graceful lock:
  - GET requests allowed (read-only data and reports).
  - POST / PUT / DELETE blocked except `billing.*`, `profile`, `logout`.
  - Livewire write requests return a 403 that dispatches a `subscription-expired` event.
- **Grandfathered companies** (`Company::isGrandfathered`) — no restrictions.
- **System Admins** — no restrictions.

Plan-gated features use `check.feature:<feature>` middleware → [CheckFeatureAccess](../app/Http/Middleware/CheckFeatureAccess.php). Example: `/analytics` is gated by `check.feature:analytics`. The feature flag lookup calls `SubscriptionService::canUseFeature`.

Usage quotas (outlets, users, recipes, ingredients, lms_users) are enforced in code via `SubscriptionService::enforceLimit($company, $metric)` before creating records. `UsageTrackingService` snapshots usage periodically.

---

## Middleware quick reference

Registered aliases in [bootstrap/app.php](../bootstrap/app.php):

| Alias | Class | Purpose |
|-------|-------|---------|
| `company.scope` | [EnsureCompanyScope](../app/Http/Middleware/EnsureCompanyScope.php) | Reject users without a company (unless System Admin) |
| `company.subdomain` | [ResolveCompanyFromSubdomain](../app/Http/Middleware/ResolveCompanyFromSubdomain.php) | Resolve tenant from subdomain (LMS) |
| `lms.auth` | [LmsAuthenticate](../app/Http/Middleware/LmsAuthenticate.php) | LMS guard check |
| `lms.guest` | [LmsGuest](../app/Http/Middleware/LmsGuest.php) | LMS guest-only pages |
| `onboarding` | [EnsureOnboardingComplete](../app/Http/Middleware/EnsureOnboardingComplete.php) | Redirect to onboarding until complete |
| `check.subscription` | [CheckSubscription](../app/Http/Middleware/CheckSubscription.php) | Verify active subscription (hard block) |
| `enforce.subscription` | [EnforceSubscription](../app/Http/Middleware/EnforceSubscription.php) | Graceful read-only lock |
| `check.feature:X` | [CheckFeatureAccess](../app/Http/Middleware/CheckFeatureAccess.php) | Plan feature flag |
| `plan.rate_limit` | [PlanRateLimiter](../app/Http/Middleware/PlanRateLimiter.php) | Per-plan API rate limiting |
| `kitchen.user` | [EnsureKitchenUser](../app/Http/Middleware/EnsureKitchenUser.php) | Kitchen-user gate for `/kitchen/*` |

Global prepend/append on the `web` group:
- **Prepend:** [EnforceMainDomain](../app/Http/Middleware/EnforceMainDomain.php) — force all non-LMS traffic to the primary domain.
- **Append:** [SetDisplayTimezone](../app/Http/Middleware/SetDisplayTimezone.php) — set per-user / per-company display timezone.

The root auth group `['auth', 'verified', 'company.scope', 'enforce.subscription']` wraps most in-app routes ([routes/web.php](../routes/web.php) line 153). Permissions and role gates layer on top.

---

## Layouts

Six root layouts under [resources/views/layouts/](../resources/views/layouts/):

| Layout | Used for |
|--------|----------|
| `app.blade.php` | Main app (dark sidebar + top nav + OutletSwitcher) |
| `guest.blade.php` | Login / register / password reset |
| `kitchen.blade.php` | Kitchen workspace (different sidebar) |
| `lms.blade.php` | LMS / training portal |
| `marketing.blade.php` | Public marketing pages |
| `supplier.blade.php` | Supplier portal |

Which layout a Livewire component uses is controlled by the component's view or an `#[Layout('layouts.xxx')]` attribute / class method.

---

## Seeded data

[database/seeders/DatabaseSeeder.php](../database/seeders/DatabaseSeeder.php) + [PlanSeeder.php](../database/seeders/PlanSeeder.php) + [TestUsersSeeder.php](../database/seeders/TestUsersSeeder.php):

- 17+ Units of Measure (kg, g, mg, lb, oz, L, ml, gal, fl oz, pcs, doz, pack, box, ctn, tray, m, cm, tsp, tbsp, gm, slice, bar, can, block, pack, roll, ream, pair, sheet, set, tub, tie, bundle).
- Default roles and permissions.
- Demo company "Demo Restaurant Co." (slug: `demo-restaurant-co`).
- Demo outlet "Main Branch" (code: `MAIN`).
- Subscription plans.

---

## Tech stack recap

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | Livewire 3, Alpine.js, Tailwind CSS 3/4, Vite |
| Database | MySQL 8 |
| Auth | Laravel Breeze (Livewire stack) |
| Roles | Spatie Laravel Permission 6.24 |
| PDF | barryvdh/laravel-dompdf |
| Export | maatwebsite/excel + openspout |
| AI | Claude API (Anthropic) via OpenRouter for analytics & vision |
| Payments | CHIP-IN |
| Email | EngineMailer V2 |
| QR | chillerlan/php-qrcode |
| PDF parse | smalot/pdfparser |
