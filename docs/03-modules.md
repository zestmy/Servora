# Livewire Components by Module

Find the right component to edit fast. Routes are pulled from [routes/web.php](../routes/web.php) and [routes/auth.php](../routes/auth.php). Unless noted, routes are inside the `auth + verified + company.scope + enforce.subscription` middleware group.

---

## Top-level

| Component | Route | Purpose |
|-----------|-------|---------|
| [Dashboard](../app/Livewire/Dashboard.php) | `/dashboard` | Ops hub — pending POs, recent transactions, KPIs, smart alerts. Uses `ScopesToActiveOutlet`. |
| [OutletSwitcher](../app/Livewire/OutletSwitcher.php) | embedded in layout | Changes `active_outlet_id` in session; emits events on switch. |

---

## Admin (`/admin`, System Admin only)

| Component | Route | Purpose |
|-----------|-------|---------|
| [Admin/Plans/Index](../app/Livewire/Admin/Plans/Index.php) | `/admin/plans` | List subscription plans, toggle active. |
| [Admin/Plans/Form](../app/Livewire/Admin/Plans/Form.php) | `/admin/plans/create`, `/admin/plans/{id}/edit` | Create/edit plan with feature flags & limits. |
| [Admin/Subscriptions/Index](../app/Livewire/Admin/Subscriptions/Index.php) | `/admin/subscriptions` | Monitor all customer subs; cancel/extend. |
| [Admin/Referrals/Dashboard](../app/Livewire/Admin/Referrals/Dashboard.php) | `/admin/referrals` | Track referral commissions; approve/pay/reject. |
| [Admin/Referrals/Programs](../app/Livewire/Admin/Referrals/Programs.php) | `/admin/referrals/programs` | Manage referral programs & rates. |
| [Admin/TrialDashboard](../app/Livewire/Admin/TrialDashboard.php) | `/admin/trials` | Extend trials / convert to paid. |
| [Admin/CompanyHealth](../app/Livewire/Admin/CompanyHealth.php) | `/admin/company-health` | Company health metrics. |
| [Admin/Announcements](../app/Livewire/Admin/Announcements.php) | `/admin/announcements` | Global system banners. |
| [Admin/Pages](../app/Livewire/Admin/Pages.php) | `/admin/pages` | Marketing CMS pages. |
| [Admin/Coupons](../app/Livewire/Admin/Coupons.php) | `/admin/coupons` | Discount codes with usage limits. |

---

## Analytics

| Component | Route | Purpose |
|-----------|-------|---------|
| [Analytics/Index](../app/Livewire/Analytics/Index.php) | `/analytics` | AI-powered KPI dashboard with monthly/weekly trend analysis. `#runAnalysis()` calls Claude via `AiAnalyticsService`. Gated by `check.feature:analytics`. |

---

## Auth

| Component | Route | Purpose |
|-----------|-------|---------|
| [Auth/SaasRegister](../app/Livewire/Auth/SaasRegister.php) | `/register/start` | Public signup — creates tenant via `CompanyRegistrationService`, tracks referral cookie. |
| [Forms/LoginForm](../app/Livewire/Forms/LoginForm.php) | used by Breeze login | SaaS login form. |

---

## Billing

| Component | Route | Purpose |
|-----------|-------|---------|
| [Billing/Index](../app/Livewire/Billing/Index.php) | `/billing` | Invoices, payment history, upgrade options, coupon redemption. |
| [Billing/Checkout](../app/Livewire/Billing/Checkout.php) | `/billing/checkout/{planSlug}` | Payment flow via CHIP-IN (`ChipInService`). |
| [Billing/ReferralDashboard](../app/Livewire/Billing/ReferralDashboard.php) | `/refer` | User's referral code + earnings. |

---

## Components & Forms (shared)

| Component | Usage | Purpose |
|-----------|-------|---------|
| [Components/AnnouncementBanner](../app/Livewire/Components/AnnouncementBanner.php) | layout | Show active announcements. |
| [Components/UpgradePrompt](../app/Livewire/Components/UpgradePrompt.php) | modal | Plan-limit warning with upgrade CTA. |

---

## HR

| Component | Route | Purpose |
|-----------|-------|---------|
| [Hr/Employees](../app/Livewire/Hr/Employees.php) | `/hr/employees` | Staff list with filters (outlet, section, status). |
| [Hr/OvertimeClaims](../app/Livewire/Hr/OvertimeClaims.php) | `/hr/overtime-claims` | OT claim form + auto-calc hours; approval workflow. |

---

## Ingredients

Two-step **Price Watcher** flow: `ScanDocument` stages → `ReviewDocuments` lists → `ReviewDocument` matches & imports.

| Component | Route | Purpose |
|-----------|-------|---------|
| [Ingredients/Index](../app/Livewire/Ingredients/Index.php) | `/ingredients` | Master list + filters (category, status, supplier, missing UOM factor). Inline quick-edit, bulk select, CSV export. |
| [Ingredients/Import](../app/Livewire/Ingredients/Import.php) | `/ingredients/import` | CSV/Excel import with AI-assisted UOM matching. |
| [Ingredients/ScanDocument](../app/Livewire/Ingredients/ScanDocument.php) | `/ingredients/scan-document` | **Step 1**: upload supplier doc; AI extracts supplier + line items into ScannedDocument staging. |
| [Ingredients/ReviewDocuments](../app/Livewire/Ingredients/ReviewDocuments.php) | `/ingredients/review-documents` | List staged docs awaiting review. |
| [Ingredients/ReviewDocument](../app/Livewire/Ingredients/ReviewDocument.php) | `/ingredients/review-documents/{document}` | **Step 2**: match extracted rows → ingredients, fix UOM, action = import/skip/create; updates `Ingredient.current_cost` + writes `IngredientPriceHistory`. |

Legacy redirects: `/ingredients/price-watcher` and `/ingredients/supplier-match` → `/ingredients/scan-document`.

---

## Inventory

| Component | Route | Purpose |
|-----------|-------|---------|
| [Inventory/Index](../app/Livewire/Inventory/Index.php) | `/inventory` | 5-tab view: Stock Takes, Wastage, Staff Meals, Prep Items, Transfers. |
| [Inventory/StockTakeForm](../app/Livewire/Inventory/StockTakeForm.php) | `/inventory/stock-takes/create`, `/inventory/stock-takes/{id}` | Detailed (per-ingredient variance) or summary (one total) stock take. Template picker. |
| [Inventory/WastageForm](../app/Livewire/Inventory/WastageForm.php) | `/inventory/wastage/create`, `/inventory/wastage/{id}` | Log wastage by ingredient or recipe; cost auto-calc. |
| [Inventory/StaffMealForm](../app/Livewire/Inventory/StaffMealForm.php) | `/inventory/staff-meals/create`, `/inventory/staff-meals/{id}` | Staff consumption; ingredient + recipe lines. |
| [Inventory/PrepItemForm](../app/Livewire/Inventory/PrepItemForm.php) | `/inventory/prep-items/create`, `/inventory/prep-items/{id}` | Prep item definition with steps and outlet availability. |
| [Inventory/TransferForm](../app/Livewire/Inventory/TransferForm.php) | `/inventory/transfers/create`, `/inventory/transfers/{id}` | Inter-outlet transfer with cost. |

---

## Kitchen (requires `kitchen.user` middleware)

| Component | Route | Purpose |
|-----------|-------|---------|
| [Kitchen/Index](../app/Livewire/Kitchen/Index.php) | `/kitchen` | Production orders + prep requests; tab + status filter. |
| [Kitchen/ProductionOrderForm](../app/Livewire/Kitchen/ProductionOrderForm.php) | `/kitchen/orders/create`, `/kitchen/orders/{id}/edit` | Create/edit batch production; add recipes + target outlets. |
| [Kitchen/ProductionExecute](../app/Livewire/Kitchen/ProductionExecute.php) | `/kitchen/orders/{id}/execute` | Log actual yield, record waste, complete order. |
| [Kitchen/PrepRequestForm](../app/Livewire/Kitchen/PrepRequestForm.php) | `/kitchen/prep-requests/create`, `/kitchen/prep-requests/{id}/edit` | Outlet requests prep items from CK. |
| [Kitchen/ProductionRecipes](../app/Livewire/Kitchen/ProductionRecipes.php) | `/kitchen/recipes` | Kitchen-only recipe list. |
| [Kitchen/ProductionRecipeForm](../app/Livewire/Kitchen/ProductionRecipeForm.php) | `/kitchen/recipes/create`, `/kitchen/recipes/{id}/edit` | Kitchen recipe definition. |

---

## LMS / Training

| Component | Usage | Purpose |
|-----------|-------|---------|
| [Lms/*](../app/Livewire/Lms/) | Varies | Training portal — recipe/SOP browsing. LMS users have their own auth guard. |

SOP PDF routes: `/training/sop/{id}/pdf`, `/training/sop/pdf-all` (see [SopPdfController](../app/Http/Controllers/Lms/)).

---

## Marketing (public, no auth)

| Component | Route | Purpose |
|-----------|-------|---------|
| [Marketing/Home](../app/Livewire/Marketing/Home.php) | `/` | Landing page (logged-in users redirect to dashboard). |
| [Marketing/Pricing](../app/Livewire/Marketing/Pricing.php) | `/pricing` | Plan grid. |
| [Marketing/Features](../app/Livewire/Marketing/Features.php) | `/features` | Feature overview. |
| [Marketing/ForSuppliers](../app/Livewire/Marketing/ForSuppliers.php) | `/for-suppliers` | Supplier portal pitch. |
| [Marketing/Marketplace](../app/Livewire/Marketing/Marketplace.php) | `/marketplace` | Marketplace directory. |
| [Marketing/ReferralProgram](../app/Livewire/Marketing/ReferralProgram.php) | `/referral` | Public referral program info. |
| [Marketing/PageView](../app/Livewire/Marketing/PageView.php) | `/page/{slug}` | CMS page renderer. |

---

## Onboarding

| Component | Route | Purpose |
|-----------|-------|---------|
| [Onboarding/Wizard](../app/Livewire/Onboarding/Wizard.php) | `/onboarding` | Multi-step setup: outlets, categories, suppliers, users. Writes `OnboardingStep`. |

---

## Purchasing

Hub tabs: POs, PRs, RFQs, Invoices, Transfers, Consolidations.

| Component | Route | Purpose |
|-----------|-------|---------|
| [Purchasing/Index](../app/Livewire/Purchasing/Index.php) | `/purchasing` | Tab hub with filters (status/supplier/outlet/date). CSV export. |
| [Purchasing/OrderForm](../app/Livewire/Purchasing/OrderForm.php) | `/purchasing/orders/create`, `/purchasing/orders/{id}/edit` | Create/edit PO; pre-fill from PR via `?pr_id=`; template picker; par-level auto-ordering; multi-supplier split via `PoSplitService`. |
| [Purchasing/ReceiveForm](../app/Livewire/Purchasing/ReceiveForm.php) | `/purchasing/orders/{id}/receive`, `/purchasing/receive` | Record GRN against PO. |
| [Purchasing/ConvertToDoForm](../app/Livewire/Purchasing/ConvertToDoForm.php) | `/purchasing/orders/{id}/convert-to-do` | Generate DeliveryOrder from PO. Auto-triggered if `auto_generate_do=true`. |
| [Purchasing/GrnReceiveForm](../app/Livewire/Purchasing/GrnReceiveForm.php) | `/purchasing/grn/{id}/receive` | Receive GRN → inventory. Updates `Ingredient.current_cost` + writes `IngredientPriceHistory` on price change. |
| [Purchasing/PurchaseRequestForm](../app/Livewire/Purchasing/PurchaseRequestForm.php) | `/purchasing/requests/create`, `/purchasing/requests/{id}/edit` | PR form with approval gate. |
| [Purchasing/ConsolidateForm](../app/Livewire/Purchasing/ConsolidateForm.php) | `/purchasing/consolidate` | CPU flow: consolidate approved PRs by supplier via `PurchaseRequestService`. |
| [Purchasing/StockTransferForm](../app/Livewire/Purchasing/StockTransferForm.php) | `/purchasing/transfers/create` | STO via `StockTransferService`; chargeable transfers generate ProcurementInvoice. |
| [Purchasing/InvoiceIndex](../app/Livewire/Purchasing/InvoiceIndex.php) | `/purchasing/invoices` | Supplier invoice list. |
| [Purchasing/InvoiceShow](../app/Livewire/Purchasing/InvoiceShow.php) | `/purchasing/invoices/{id}` | Invoice detail, GRN linkage. |
| [Purchasing/InvoiceReceive](../app/Livewire/Purchasing/InvoiceReceive.php) | `/purchasing/invoices/receive` | Three-way match via `InvoiceMatchingService`; AI invoice scan via `AiInvoiceExtractionService`. |
| [Purchasing/PriceComparison](../app/Livewire/Purchasing/PriceComparison.php) | `/purchasing/price-comparison` | Compare ingredient prices across suppliers over time. |
| [Purchasing/SupplierDirectory](../app/Livewire/Purchasing/SupplierDirectory.php) | `/purchasing/suppliers` | Supplier master view with perf metrics. |
| [Purchasing/CreditNoteIndex](../app/Livewire/Purchasing/CreditNoteIndex.php) | `/purchasing/credit-notes` | List credit notes. |
| [Purchasing/CreditNoteForm](../app/Livewire/Purchasing/CreditNoteForm.php) | `/purchasing/credit-notes/create`, `/purchasing/credit-notes/{id}` | Create/edit credit note. |
| [Purchasing/RfqIndex](../app/Livewire/Purchasing/RfqIndex.php) | `/purchasing/rfq` | RFQ list. |
| [Purchasing/RfqForm](../app/Livewire/Purchasing/RfqForm.php) | `/purchasing/rfq/create`, `/purchasing/rfq/{id}/edit` | RFQ composition. |
| [Purchasing/RfqShow](../app/Livewire/Purchasing/RfqShow.php) | `/purchasing/rfq/{id}` | Quote comparison + accept → PO via `RfqService::acceptAndCreatePo`. |

---

## Recipes

| Component | Route | Purpose |
|-----------|-------|---------|
| [Recipes/Index](../app/Livewire/Recipes/Index.php) | `/recipes` | Master recipe library with category/status filters. |
| [Recipes/Form](../app/Livewire/Recipes/Form.php) | `/recipes/create`, `/recipes/{id}/edit` | Create/edit recipe: ingredients + packaging lines, outlet tagging, yield, tiered pricing, image upload (dine-in / takeaway), video. |
| [Recipes/Show](../app/Livewire/Recipes/Show.php) | `/recipes/{id}` | Read-only view with cost breakdown and yield analysis. |
| [Recipes/SmartImport](../app/Livewire/Recipes/SmartImport.php) | `/recipes/import` | Bulk recipe upload with AI-assisted ingredient/UOM matching. |

---

## Reports

Hub at `/reports` with themed sub-reports. Uses `ScopesToActiveOutlet` trait. See [`Reports/Index`](../app/Livewire/Reports/Index.php) for the Cost Summary + MTD comparison engine.

### Index

| Component | Route | Purpose |
|-----------|-------|---------|
| [Reports/Hub](../app/Livewire/Reports/Hub.php) | `/reports` | Navigation hub. |
| [Reports/Index](../app/Livewire/Reports/Index.php) | `/reports/cost-summary` | **Cost Summary** — tabs: cost_summary, performance, cost_analysis, wastage, labour_cost. Monthly/weekly. MTD comparison. Uses `CostSummaryService::generate`. |
| [Reports/PriceHistory](../app/Livewire/Reports/PriceHistory.php) | `/reports/price-history` | Ingredient price trend + supplier comparison. |

### Purchase

| Component | Route |
|-----------|-------|
| [Reports/Purchase/PurchaseAnalysis](../app/Livewire/Reports/Purchase/PurchaseAnalysis.php) | `/reports/purchase-analysis` |
| [Reports/Purchase/PoSummary](../app/Livewire/Reports/Purchase/PoSummary.php) | `/reports/po-summary` |

### Order

| Component | Route |
|-----------|-------|
| [Reports/Order/OrderHistory](../app/Livewire/Reports/Order/OrderHistory.php) | `/reports/order-history` |
| [Reports/Order/OrderSummary](../app/Livewire/Reports/Order/OrderSummary.php) | `/reports/order-summary` |
| [Reports/Order/OrderItemsByBranch](../app/Livewire/Reports/Order/OrderItemsByBranch.php) | `/reports/order-items-by-branch` |
| [Reports/Order/DeliveryOrderReport](../app/Livewire/Reports/Order/DeliveryOrderReport.php) | `/reports/delivery-order` |
| [Reports/Order/GrnReport](../app/Livewire/Reports/Order/GrnReport.php) | `/reports/grn-report` |
| [Reports/Order/InvoiceSummary](../app/Livewire/Reports/Order/InvoiceSummary.php) | `/reports/invoice-summary` |

### Inventory (balances)

| Component | Route |
|-----------|-------|
| [Reports/Inventory/StockBalancePackage](../app/Livewire/Reports/Inventory/StockBalancePackage.php) | `/reports/stock-balance-package` |
| [Reports/Inventory/StockBalanceProduct](../app/Livewire/Reports/Inventory/StockBalanceProduct.php) | `/reports/stock-balance-product` |
| [Reports/Inventory/StockCard](../app/Livewire/Reports/Inventory/StockCard.php) | `/reports/stock-card` |

### Inventory actions

| Component | Route |
|-----------|-------|
| [Reports/InventoryAction/StockCount](../app/Livewire/Reports/InventoryAction/StockCount.php) | `/reports/stock-count` |
| [Reports/InventoryAction/StockCountAnalysis](../app/Livewire/Reports/InventoryAction/StockCountAnalysis.php) | `/reports/stock-count-analysis` |
| [Reports/InventoryAction/StockWastage](../app/Livewire/Reports/InventoryAction/StockWastage.php) | `/reports/stock-wastage` |
| [Reports/InventoryAction/StockTransferHistory](../app/Livewire/Reports/InventoryAction/StockTransferHistory.php) | `/reports/stock-transfer-history` |
| [Reports/InventoryAction/StockAdjustment](../app/Livewire/Reports/InventoryAction/StockAdjustment.php) | `/reports/stock-adjustment` |

### Menu

| Component | Route |
|-----------|-------|
| [Reports/Menu/MenuIngredients](../app/Livewire/Reports/Menu/MenuIngredients.php) | `/reports/menu-ingredients` |
| [Reports/Menu/SalesMenuIngredients](../app/Livewire/Reports/Menu/SalesMenuIngredients.php) | `/reports/sales-menu-ingredients` |

### Kitchen

| Component | Route |
|-----------|-------|
| [Reports/Kitchen/ProductionHistory](../app/Livewire/Reports/Kitchen/ProductionHistory.php) | `/reports/production-history` |
| [Reports/Kitchen/YieldAnalysis](../app/Livewire/Reports/Kitchen/YieldAnalysis.php) | `/reports/yield-analysis` |

### Others

| Component | Route |
|-----------|-------|
| [Reports/Others/InventoryVariance](../app/Livewire/Reports/Others/InventoryVariance.php) | `/reports/inventory-variance` |

---

## Sales

| Component | Route | Purpose |
|-----------|-------|---------|
| [Sales/Index](../app/Livewire/Sales/Index.php) | `/sales` | Sales log + filters. AI insights button calls `AiAnalyticsService`. |
| [Sales/SalesForm](../app/Livewire/Sales/SalesForm.php) | `/sales/create`, `/sales/{id}/edit` | Record sale: meal period, pax, per-category revenue, attachments. |
| [Sales/Import](../app/Livewire/Sales/Import.php) | `/sales/import` | Z-report / POS import; OCR via `VisionService`. |

---

## Settings

Each component below manages one config table.

| Component | Route | Purpose |
|-----------|-------|---------|
| [Settings/Index](../app/Livewire/Settings/Index.php) | `/settings` | Settings hub. |
| [Settings/Suppliers](../app/Livewire/Settings/Suppliers.php) | `/settings/suppliers` | Supplier master. |
| [Settings/Categories](../app/Livewire/Settings/Categories.php) | `/settings/categories` | Ingredient category tree. |
| [Settings/RecipeCategories](../app/Livewire/Settings/RecipeCategories.php) | `/settings/recipe-categories` | Recipe category tree. |
| [Settings/PriceClasses](../app/Livewire/Settings/PriceClasses.php) | `/settings/price-classes` | Price tiers for tiered recipe pricing. |
| [Settings/SalesCategories](../app/Livewire/Settings/SalesCategories.php) | `/settings/sales-categories` | Sales categories (revenue/non-revenue). |
| [Settings/FormTemplates](../app/Livewire/Settings/FormTemplates.php) | `/settings/form-templates` | PO / stock-take templates list. |
| [Settings/FormTemplateEdit](../app/Livewire/Settings/FormTemplateEdit.php) | `/settings/form-templates/{id}/edit` | Template fields + lines. |
| [Settings/ApiKeys](../app/Livewire/Settings/ApiKeys.php) | `/settings/api-keys` | API keys (Super/System Admin only). |
| [Settings/Outlets](../app/Livewire/Settings/Outlets.php) | `/settings/outlets` | Outlet/branch master. |
| [Settings/Users](../app/Livewire/Settings/Users.php) | `/settings/users` | Users + role + outlet assignments. |
| [Settings/CompanyDetails](../app/Livewire/Settings/CompanyDetails.php) | `/settings/company-details` | Logo, address, registration, feature toggles. |
| [Settings/PoApprovers](../app/Livewire/Settings/PoApprovers.php) | `/settings/po-approvers` | PO approval matrix. |
| [Settings/CalendarEvents](../app/Livewire/Settings/CalendarEvents.php) | `/settings/calendar-events` | Events/reminders. |
| [Settings/SalesTargets](../app/Livewire/Settings/SalesTargets.php) | `/settings/sales-targets` | Monthly/daily revenue targets per outlet. |
| [Settings/Departments](../app/Livewire/Settings/Departments.php) | `/settings/departments` | Department master. |
| [Settings/Sections](../app/Livewire/Settings/Sections.php) | `/settings/sections` | Staff sections. |
| [Settings/ParLevels](../app/Livewire/Settings/ParLevels.php) | `/settings/par-levels` | Par levels per ingredient per outlet (auto-ordering source). |
| [Settings/OutletGroups](../app/Livewire/Settings/OutletGroups.php) | `/settings/outlet-groups` | Group outlets for bulk operations. |
| [Settings/LabourCosts](../app/Livewire/Settings/LabourCosts.php) | `/settings/labour-costs` | Labour cost (shows in cost report). |
| [Settings/LmsUsers](../app/Livewire/Settings/LmsUsers.php) | `/settings/lms-users` | LMS/training users. |
| [Settings/CpuManagement](../app/Livewire/Settings/CpuManagement.php) | `/settings/cpu-management` | Central Purchasing Unit config. |
| [Settings/KitchenManagement](../app/Livewire/Settings/KitchenManagement.php) | `/settings/kitchen-management` | Central Kitchen config + user access. |
| [Settings/TaxRates](../app/Livewire/Settings/TaxRates.php) | `/settings/tax-rates` | Tax rate master. |
| [Settings/SupplierProductMapping](../app/Livewire/Settings/SupplierProductMapping.php) | `/settings/supplier-mapping` | Map supplier products → ingredients. |
| [Settings/PriceAlerts](../app/Livewire/Settings/PriceAlerts.php) | `/settings/price-alerts` | Alert thresholds per ingredient/supplier. |
| [Settings/OtApprovers](../app/Livewire/Settings/OtApprovers.php) | `/settings/ot-approvers` | Overtime claim approval routing. |

---

## Supplier Portal (separate `supplier` guard, `/supplier/*`)

| Component | Route | Purpose |
|-----------|-------|---------|
| [Supplier/Dashboard](../app/Livewire/Supplier/Dashboard.php) | `/supplier/dashboard` | KPI summary. |
| [Supplier/Products](../app/Livewire/Supplier/Products.php) | `/supplier/products` | Supplier catalog. |
| [Supplier/Orders](../app/Livewire/Supplier/Orders.php) | `/supplier/orders` | POs received. |
| [Supplier/OrderShow](../app/Livewire/Supplier/OrderShow.php) | `/supplier/orders/{id}` | PO detail + acknowledge. |
| [Supplier/Invoices](../app/Livewire/Supplier/Invoices.php) | `/supplier/invoices` | Invoice history. |
| [Supplier/Quotations](../app/Livewire/Supplier/Quotations.php) | `/supplier/quotations` | RFQs to respond to. |
| [Supplier/QuotationResponse](../app/Livewire/Supplier/QuotationResponse.php) | `/supplier/quotations/{id}/respond` | Submit quote. |
| [Supplier/CreditNotes](../app/Livewire/Supplier/CreditNotes.php) | `/supplier/credit-notes` | Credit notes issued. |
| [Supplier/Profile](../app/Livewire/Supplier/Profile.php) | `/supplier/profile` | Supplier profile + bank details. |

---

## Patterns

- **Form lifecycle** — `mount(?int $id)` dual-mode create/edit, `protected function rules()` + `messages()`, `#save()` persists & flashes.
- **Index filtering** — `WithPagination`; `updatedXxx()` hooks call `$this->resetPage()` and clear bulk selection.
- **Scoping** — index components include `use ScopesToActiveOutlet;`; reports also use `ReportFilters` trait.
- **Template pickers** — `OrderForm`, `StockTakeForm`, `PurchaseRequestForm` expose `$selectedTemplateId` to pre-fill from `FormTemplate`.
- **Two-step flows** — Price Watcher (Ingredients), Approval chain (PR → PO → DO → GRN → Invoice), RFQ → PO.
