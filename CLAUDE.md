# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Servora is a multi-tenant Laravel 12 + Livewire 3 SaaS for Food & Beverage operations (ingredients, recipes, purchasing, sales, inventory, reports, HR, LMS, supplier and affiliate portals). MySQL 8 in production, SQLite `:memory:` in tests.

Extensive developer documentation already exists under `docs/` — prefer reading it over grepping:

- `docs/01-architecture.md` — tenancy, guards, middleware, layouts.
- `docs/02-domain-model.md` — models, relationships, global scopes.
- `docs/03-modules.md` — Livewire components mapped to user-facing modules.
- `docs/04-services.md` — service catalog (purpose, methods, callers, notes).
- `docs/05-workflows.md` — end-to-end flows (procurement, cost chain, P&L, sales, RFQ, transfers, subscription lifecycle).
- `docs/06-routes.md` — URLs, controllers, middleware stacks.
- `docs/07-database.md` — schema and migration timeline.
- `docs/08-feature-playbook.md` — step-by-step recipes for common additions.

**When a workflow, service, or role changes**, update the matching doc. These files are the first place future Claude instances look.

## Common commands

```bash
# One-shot local setup (install, key, migrate, npm, build)
composer setup

# Run all four dev processes concurrently: artisan serve, queue:listen, pail logs, vite
composer dev

# Test suite (clears config cache first)
composer test
# or
php artisan test
# Single test / filter
php artisan test --filter=ProfileTest
php artisan test tests/Feature/Auth/AuthenticationTest.php

# Lint / format (Laravel Pint)
./vendor/bin/pint

# Frontend only
npm run dev        # Vite dev server
npm run build      # production build

# Tail logs (separate from `composer dev`)
php artisan pail
```

Tests run against `sqlite :memory:` (see `phpunit.xml`) — the dev DB is MySQL but tests do not touch it. `BCRYPT_ROUNDS=4` in test env keeps auth tests fast.

Default seeded login after `php artisan migrate --seed`:
- Email: `admin@servora.test` / Password: `password` / Role: Super Admin.

## Deploy

- Fresh Ubuntu 22.04 droplet: `bash deploy/install.sh` (handles nginx, PHP, MySQL, Node, Composer, migrations, SSL, systemd queue worker, cron scheduler).
- Updates: `bash deploy/update.sh` (pull → build → migrate → cache clear, with maintenance mode).
- If an update leaves the app in maintenance mode: `php artisan up`.

## Architecture — what you must know before editing

### Multi-tenancy is enforced by a global scope, not by controllers

Almost every tenant model registers `CompanyScope` in `booted()`:

```php
protected static function booted(): void
{
    static::addGlobalScope(new \App\Scopes\CompanyScope());
}
```

`CompanyScope` (`app/Scopes/CompanyScope.php`) resolves `company_id` from the `web` user, then the `lms` user, then a subdomain-bound `currentCompany` (set by `ResolveCompanyFromSubdomain`). Admin/cross-company queries must call `Model::withoutGlobalScope(CompanyScope::class)` explicitly. System Admins have `company_id = null` and bypass most gates — their queries must scope manually.

`EnsureCompanyScope` middleware (alias `company.scope`) rejects authenticated users with no `company_id` (except System Admins). It's applied to the main auth group in `routes/web.php` (≈ line 153).

### Multi-outlet scoping

Users are assigned to outlets via the `outlet_user` pivot. The "active outlet" is stored in `session('active_outlet_id')` and set by `OutletSwitcher`. Business Manager / Super Admin / System Admin get an "All Outlets" option (`null`).

Livewire list components use the `ScopesToActiveOutlet` trait:

```php
use ScopesToActiveOutlet;

$query = PurchaseOrder::query();
$this->scopeByOutlet($query);                      // default column 'outlet_id'
$this->scopeByOutlet($query, 'from_outlet_id');
```

`scopeByOutlet` is a no-op when `activeOutletId()` is null — cross-outlet users then see everything they're allowed to see. **Do not hard-code `where('outlet_id', $user->outlet_id)`** — it silently breaks multi-outlet users. Use the trait.

Workspace modes (`outlet` vs `kitchen`) are orthogonal to outlets and live under separate session keys (`workspace_mode`, `active_kitchen_id`).

### Four auth guards

| Guard | Model | Portal |
|-------|-------|--------|
| `web` (default) | `User` | main app |
| `lms` | `LmsUser` | training/LMS |
| `supplier` | `SupplierUser` | `/supplier/*` |
| `affiliate` | `Affiliate` | `/affiliate/*` |

Each has its own login routes and middleware. Supplier portal code must scope by `auth('supplier')->user()->supplier_id` — **not** CompanyScope.

### Roles, permissions, and subscription gates layer on top

Roles live in Spatie Laravel Permission migrations. Routes gate with `can:<permission>` or `role:<Name>`. The canonical permission names are in `docs/01-architecture.md` (ingredients.view, recipes.view, purchasing.view, sales.view, inventory.view, reports.view, settings.view, users.manage, hr.view).

`EnforceSubscription` middleware (alias `enforce.subscription`) applies a **graceful read-only lock** to expired companies: GETs pass, writes return 403 except `billing.*`, `profile`, `logout`. Livewire write failures dispatch `subscription-expired`. Grandfathered companies and System Admins are exempt. Plan-gated features use `check.feature:<key>` via `SubscriptionService::canUseFeature`.

Usage quotas (outlets, users, recipes, ingredients, lms_users) are enforced in code via `SubscriptionService::enforceLimit($company, $metric)` **before** creating records.

### Services own side-effects; Livewire orchestrates

Cross-cutting logic lives in `app/Services/` (26 services). Before writing new code, check `docs/04-services.md` — most of what you need is already there (UOM conversion, cost summary, PO splitting, PR consolidation, invoice matching, tax calculation, CHIP-IN billing, referrals, AI extraction, etc.).

Patterns to keep:

- **DB transactions** for multi-table writes (`PoSplitService`, `RfqService`, `CouponService`, `CompanyRegistrationService`, `PurchaseRequestService`).
- **Audit trails** go through services: `OrderAdjustmentLog`, `IngredientPriceHistory`, `AiAnalysisLog`, `AuditLog`.
- **External APIs** (ChipIn, EngineMailer, OpenRouter Claude, Google Vision) live in services; API keys in `.env`.
- EngineMailer returns **HTTP 200 on errors** — always inspect `StatusCode` in the response body.

### Cost chain and P&L — use the existing formulas

Don't reimplement any of:

- **Cost per recipe unit** — `UomService::convertCost(Ingredient, UnitOfMeasure)`. Checks ingredient-specific `IngredientUomConversion` first, falls back to UOM `base_unit_factor`. Applies `yield_percent`.
- **Monthly P&L / COGS / cost %** — `CostSummaryService::generate($period, $outletId, $startDate, $endDate)`. Groups by department → sales_category, supports custom ranges and MTD comparison via repeated calls with different bounds.
- **Tax** — `TaxCalculationService::calculate($subtotal, $taxRateId, $company)` resolves explicit `TaxRate` → company default → legacy `tax_percent`.

### Procurement lifecycle

```
PR (draft → submitted → approved) → consolidate → PO (draft → sent → partial → received)
                                              → (auto) DO (pending → received) → GRN → PurchaseRecord + IngredientPriceHistory
                                                                                    → ProcurementInvoice → (optional) CreditNote
```

Branching is driven by **company toggles**: `require_pr_approval`, `require_po_approval`, `auto_generate_do`, `direct_supplier_order`, `ordering_mode` (`outlet` vs `cpu`), `show_price_on_do_grn`, `price_alert_threshold`. When adding a new branching flag, follow playbook §3 in `docs/08-feature-playbook.md`.

Status values are **string columns, not DB enums**. Canonical values per entity are documented in `docs/05-workflows.md`. When adding a new status, update every place the set is used (filter dropdowns, transition methods, Blade classes, PDFs) — grep first.

### Auto numbers and money precision

- Number formats: `PO-YYYYMMDD-NNN`, `PR-`, `DO-`, `GRN-`, `STO-`, `PROD-`, `QTN-`. Services generate these — don't roll your own.
- `decimal(12,2)` for totals, `decimal(12,4)` for unit costs. Always cast in `$casts`.
- `Ingredient.name` and `Recipe.name` are uppercased in the `saving()` hook. The DB stores uppercase — match with `ILIKE` / strtoupper-compared queries, never lowercase.
- Dates: users enter in their timezone (`users.timezone`); DB stores UTC. `SetDisplayTimezone` (appended to `web` middleware group) converts on display.

## Adding features — follow `docs/08-feature-playbook.md`

That file has working step-by-steps for:

1. New tenant-scoped entity (migration + model + CompanyScope + Livewire Index/Form + route + permission + sidebar link).
2. New report (Livewire + `ReportFilters` trait + CSV export + route under `can:reports.view` + hub link).
3. New company-level feature toggle (boolean column + settings UI + optional `check.feature`).
4. New PDF export (Blade + controller + `Pdf::loadView(...)->download(...)` + button).
5. New Livewire field on an existing form.
6. New service.
7. New workflow stage / status.
8. New role or permission (migration modelled on `2026_03_08_000049_create_po_approvers_and_fix_roles`).
9. AI feature (reuse `AiAnalyticsService` / `AiInvoiceExtractionService` / `VisionService` patterns; cache + log).
10. Scheduled task (register in `routes/console.php`; production cron runs `schedule:run`).
11. Supplier portal screen.
16. **Cross-outlet role** — the `can_view_all_outlets` boolean on `users` plus `scopeByOutlet` being a no-op for null active outlet is the intended pattern. Seed approver matrix rows with `outlet_id = null` to wildcard. Don't hard-code outlet filters.

## Conventions worth preserving

- **No comments describing WHAT code does** — names speak. Comments for WHY, hidden constraints, subtle invariants.
- **Services over fat Livewire** — if a flow touches >2 tables or has side-effects, it belongs in a service.
- **Keep the 30+ `scopeByOutlet` call sites consistent** — the trait is the contract. A new hard-coded outlet filter anywhere regresses multi-outlet users silently.
- **CompanyScope is non-negotiable** on new tenant models. If you need an unscoped query, use `withoutGlobalScope` at the call site.
- **Livewire components choose layout via `#[Layout('layouts.xxx')]`** — six layouts: `app`, `guest`, `kitchen`, `lms`, `marketing`, `supplier`.

## Development branch

Per session instructions, develop on `claude/add-claude-documentation-VVy0W` and push there.
