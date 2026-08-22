# Routes & Controllers

All routes live in [routes/web.php](../routes/web.php) (main app, supplier portal, affiliate portal, admin) and [routes/auth.php](../routes/auth.php) (Breeze auth). LMS sub-app routes load from [routes/lms.php](../routes/lms.php) via the `then:` callback in [bootstrap/app.php](../bootstrap/app.php).

> For component-by-component mapping with purposes, see [03-modules.md](03-modules.md). This doc focuses on route groups, middleware stacks, and controllers.

---

## Main auth group

All in-app Livewire routes sit inside:

```php
Route::middleware(['auth', 'verified', 'company.scope', 'enforce.subscription'])->group(function () { ... });
```

Permission/role gates layer on with `middleware('can:xxx')` or `role:`.

---

## Public (no auth)

| Path | Target | Notes |
|------|--------|-------|
| `/` | `Marketing\Home` | Logged-in users redirect to dashboard |
| `/pricing` | `Marketing\Pricing` | |
| `/features` | `Marketing\Features` | |
| `/for-suppliers` | `Marketing\ForSuppliers` | |
| `/marketplace` | `Marketing\Marketplace` | |
| `/referral` | `Marketing\ReferralProgram` | |
| `/register/start` | `Auth\SaasRegister` | SaaS signup |
| `/page/{slug}` | `Marketing\PageView` | CMS page |
| `/v/{token}` | `VideoShareController@show` | QR / video share (loginless) |
| `/v/{token}/data` | `VideoShareController@data` | JSON metadata |
| `/webhooks/chipin` | `Webhook\ChipInWebhookController` | POST, CSRF exempt |
| `/r/{code}`, `/ref/{code}` | `ReferralTrackingController` | Short link tracking |

---

## Portals

### Supplier portal (`/supplier/*`, `supplier` guard)
Auth controller: [Supplier\AuthController](../app/Http/Controllers/Supplier/).
Middleware: [SupplierAuthenticate](../app/Http/Middleware/SupplierAuthenticate.php).

Login/register/reset-password pairs, then dashboard, products, orders, invoices, quotations, credit-notes, profile. All Livewire.

### Affiliate portal (`/affiliate/*`, `auth:affiliate` guard)
Auth controller: [Affiliate\AuthController](../app/Http/Controllers/Affiliate/).

Dashboard and bank-details endpoints via [Affiliate\DashboardController](../app/Http/Controllers/Affiliate/) and [Affiliate\BankController](../app/Http/Controllers/Affiliate/).

### LMS portal
Loaded separately via [routes/lms.php](../routes/lms.php) with `lms` guard.

---

## In-app module routes (authenticated)

### Dashboard & onboarding
- `/dashboard` → [Dashboard](../app/Livewire/Dashboard.php)
- `/onboarding` → [Onboarding\Wizard](../app/Livewire/Onboarding/Wizard.php)

### Ingredients (`can:ingredients.view`)
| Path | Target |
|------|--------|
| `/ingredients` | [Ingredients\Index](../app/Livewire/Ingredients/Index.php) |
| `/ingredients/export` | [IngredientExportController](../app/Http/Controllers/IngredientExportController.php) |
| `/ingredients/pdf` | [IngredientPdfController](../app/Http/Controllers/IngredientPdfController.php) |
| `/ingredients/import` | [Ingredients\Import](../app/Livewire/Ingredients/Import.php) |
| `/ingredients/scan-document` | [Ingredients\ScanDocument](../app/Livewire/Ingredients/ScanDocument.php) |
| `/ingredients/review-documents` | [Ingredients\ReviewDocuments](../app/Livewire/Ingredients/ReviewDocuments.php) |
| `/ingredients/review-documents/{document}` | [Ingredients\ReviewDocument](../app/Livewire/Ingredients/ReviewDocument.php) |

Legacy redirects: `/ingredients/price-watcher`, `/ingredients/supplier-match` → `/ingredients/scan-document`.

### Recipes (`can:recipes.view`)
| Path | Target |
|------|--------|
| `/recipes` | [Recipes\Index](../app/Livewire/Recipes/Index.php) |
| `/recipes/create`, `/recipes/{id}/edit` | [Recipes\Form](../app/Livewire/Recipes/Form.php) |
| `/recipes/{id}` | [Recipes\Show](../app/Livewire/Recipes/Show.php) |
| `/recipes/import` | [Recipes\SmartImport](../app/Livewire/Recipes/SmartImport.php) |
| `/recipes/cost-pdf/all`, `/cost-pdf/summary`, `/{id}/cost-pdf` | [RecipeCostPdfController](../app/Http/Controllers/RecipeCostPdfController.php) |
| `/recipes/prep/cost-pdf/all`, `/prep/cost-pdf/summary` | same controller, prep variants |

### Purchasing (`can:purchasing.view`)
All under [Purchasing\*](../app/Livewire/Purchasing/) — see [03-modules.md](03-modules.md#purchasing).

Key controller: [PurchaseDocumentPdfController](../app/Http/Controllers/PurchaseDocumentPdfController.php) at `/purchasing/pdf/{type}/{id}` — renders PO / DO / GRN / PR PDFs.

### Sales (`can:sales.view`)
- `/sales`, `/sales/create`, `/sales/{id}/edit`, `/sales/import`

### Inventory (`can:inventory.view`)
- `/inventory` hub + 5 form routes (stock-takes, wastage, staff-meals, prep-items, transfers).
- `/inventory/stock-takes/{id}/count-sheet` → [StockTakeCountSheetController](../app/Http/Controllers/StockTakeCountSheetController.php) (print view).

### Reports (`can:reports.view`)
- `/reports` hub
- `/reports/cost-summary`, `/reports/price-history`
- `/reports/purchase-analysis`, `/reports/po-summary`
- `/reports/order-history`, `/reports/order-summary`, `/reports/order-items-by-branch`, `/reports/delivery-order`, `/reports/grn-report`, `/reports/invoice-summary`
- `/reports/stock-balance-package`, `/reports/stock-balance-product`, `/reports/stock-card`
- `/reports/stock-count`, `/reports/stock-count-analysis`, `/reports/stock-wastage`, `/reports/stock-transfer-history`, `/reports/stock-adjustment`
- `/reports/sales-menu-ingredients`, `/reports/menu-ingredients`
- `/reports/inventory-variance`
- `/reports/production-history`, `/reports/yield-analysis`

### Analytics (`can:reports.view + check.feature:analytics`)
- `/analytics` → [Analytics\Index](../app/Livewire/Analytics/Index.php)

### Kitchen workspace (`kitchen.user` middleware)
Under `/kitchen/*` after switching via `/workspace/kitchen`.

### HR (`can:hr.view`)
- `/hr/employees`, `/hr/overtime-claims`
- `/hr/staff-pins` (`can:staff.pins`) → [Hr/StaffPins](../app/Livewire/Hr/StaffPins.php) — the PIN that opens both staff apps. Moved here from `/labels/staff-access`, which now 301s to it.
- `/hr/overtime-claims/pdf/{employee}` → [OtClaimPdfController](../app/Http/Controllers/OtClaimPdfController.php)

### Web clock-in
Manager-facing (`can:hr.clock`):
- `/hr/clock-ins` → [Hr/ClockEvents](../app/Livewire/Hr/ClockEvents.php) — the review queue
- `/hr/clock-ins/{event}/selfie` → [Hr/ClockImageController](../app/Http/Controllers/Hr/ClockImageController.php)

Manager-facing (`can:hr.clock.manage`):
- `/hr/clock-settings` → [Hr/ClockSettings](../app/Livewire/Hr/ClockSettings.php)
- `/hr/face-enrolment` → [Hr/FaceEnrolment](../app/Livewire/Hr/FaceEnrolment.php)
- `/hr/face-enrolment/{descriptor}/photo` → [Hr/ClockImageController](../app/Http/Controllers/Hr/ClockImageController.php)

Staff-facing, in [routes/clock-staff.php](../routes/clock-staff.php) — registered BEFORE `web.php` for the same
reason `labels-staff.php` is, and mounted at `{slug}.<domain>/clock` (or `/clock-staff` locally):
- `/login` → [Clock/Staff/Login](../app/Livewire/Clock/Staff/Login.php) (staff PIN)
- `/` → [Clock/Staff/Punch](../app/Livewire/Clock/Staff/Punch.php) (`clock.staff` middleware)
- `/history` → [Clock/Staff/History](../app/Livewire/Clock/Staff/History.php)
- `/manifest.webmanifest`, `/sw.js` → [Hr/ClockAppController](../app/Http/Controllers/Hr/ClockAppController.php)
- `/training/sop/{id}/pdf`, `/training/sop/pdf-all` → [Lms\SopPdfController](../app/Http/Controllers/Lms/)

### Billing
- `/billing`, `/billing/checkout/{planSlug}`
- `/invoices/{id}/pdf` → [SubscriptionInvoicePdfController](../app/Http/Controllers/SubscriptionInvoicePdfController.php).
  ONE route for two audiences, because the rule differs per user rather than per
  route: a system role may pull any invoice in any state, a customer only their
  own company's and only once it has left draft. That is not something route
  middleware can express, so the controller decides.
- `/refer` — referral dashboard (all users)

### Settings
Each settings page is a single Livewire component — see [03-modules.md](03-modules.md#settings). Permission gate depends on the page (`can:settings.view`, `can:purchasing.view`, `can:users.manage`, `can:hr.view`, or role-based).

### Workspace switcher
- `/workspace/{mode}` — closure that validates `mode ∈ {outlet, kitchen}`, sets `workspace_mode` in session, and redirects to the appropriate dashboard.

### Admin (`role:System Admin`)
Under `/admin/*`: plans, subscriptions, **invoices**, **billing-settings**, referrals, trials,
company-health, announcements, pages, **docs**, coupons.

`admin.invoices.*` is the SUBSCRIPTION ledger — Servora billing its tenants.
`purchasing.invoices.*` is a tenant recording what its supplier billed it.
Different money, opposite direction, deliberately separate namespaces and
separate services (`InvoiceService` vs `ProcurementInvoiceService`).

### Help centre (public, no auth)
- `/help` → [Help/Index](../app/Livewire/Help/Index.php)
- `/help/{categorySlug}` → [Help/Category](../app/Livewire/Help/Category.php)
- `/help/{categorySlug}/{articleSlug}` → [Help/Article](../app/Livewire/Help/Article.php)

The `…Slug` parameter names are load-bearing — see
[03-modules.md](03-modules.md#help-centre-help-public).

---

## Controllers

All PDF/export controllers are thin wrappers around a Blade template under [resources/views/pdf/](../resources/views/pdf/) rendered via `barryvdh/laravel-dompdf`.

| Controller | Purpose |
|------------|---------|
| [IngredientExportController](../app/Http/Controllers/IngredientExportController.php) | CSV export of ingredient list (delegates to `CsvExportService`) |
| [IngredientPdfController](../app/Http/Controllers/IngredientPdfController.php) | PDF of the ingredient list |
| [RecipeCostPdfController](../app/Http/Controllers/RecipeCostPdfController.php) | Recipe cost PDFs — `single`, `all`, `summary`, `prepAll`, `prepSummary` |
| [PurchaseDocumentPdfController](../app/Http/Controllers/PurchaseDocumentPdfController.php) | Render PO/DO/GRN/PR PDFs (`{type}/{id}`) |
| [StockTakeCountSheetController](../app/Http/Controllers/StockTakeCountSheetController.php) | Print-friendly count sheet |
| [OtClaimPdfController](../app/Http/Controllers/OtClaimPdfController.php) | Overtime claim PDF for an employee |
| [ReferralTrackingController](../app/Http/Controllers/ReferralTrackingController.php) | `/r/{code}` — track click, set cookie, redirect |
| [VideoShareController](../app/Http/Controllers/VideoShareController.php) | Public video share (loginless) |
| [Webhook\ChipInWebhookController](../app/Http/Controllers/Webhook/) | CHIP-IN payment webhook |
| [Supplier\AuthController](../app/Http/Controllers/Supplier/) | Supplier portal auth |
| [Affiliate\AuthController](../app/Http/Controllers/Affiliate/), [Affiliate\DashboardController](../app/Http/Controllers/Affiliate/), [Affiliate\BankController](../app/Http/Controllers/Affiliate/) | Affiliate portal |
| [Lms\SopPdfController](../app/Http/Controllers/Lms/) | SOP PDF generation |

---

## PDF templates

Under [resources/views/pdf/](../resources/views/pdf/):

- `ai-analysis.blade.php`
- `cost-analysis-report.blade.php`
- `cost-summary.blade.php`
- `credit-note.blade.php`
- `delivery-order.blade.php`
- `goods-received-note.blade.php`
- `ingredients.blade.php`
- `labour-cost-report.blade.php`
- `layout.blade.php` — shared layout
- `ot-claims-all.blade.php`, `ot-claims.blade.php`
- `performance-report.blade.php`
- `price-history-report.blade.php`
- `procurement-invoice.blade.php`
- `purchase-order.blade.php`
- `purchase-request.blade.php`
- `recipe-cost-all.blade.php`, `recipe-cost-single.blade.php`, `recipe-cost-summary.blade.php`
- `sales-report.blade.php`
- `sop-all.blade.php`, `sop-single.blade.php`
- `stock-take-count-sheet.blade.php`
- `wastage-report.blade.php`
- `partials/` — reusable headers, totals blocks

The `layout.blade.php` receives the company logo, registration number, billing address, and currency context for consistent branding across PDFs.

---

## Health endpoint

`/up` is registered by Laravel's `withRouting(..., health: '/up', ...)` — basic uptime probe.
