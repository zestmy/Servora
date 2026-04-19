# Services Reference

Domain logic lives in `app/Services/`. Before writing new code, check here — most reusable business logic (cost calc, invoice match, consolidation, SaaS lifecycle) is already implemented.

---

## UomService
**File:** [app/Services/UomService.php](../app/Services/UomService.php)
**Purpose:** Unit-of-measure conversions for ingredients.
**Public methods:**
- `convertCost(Ingredient, UnitOfMeasure): float` — convert ingredient cost from base UOM → target UOM.
- `convertQuantity(float, UnitOfMeasure, UnitOfMeasure, ?int): float` — convert between two UOMs with optional ingredient-specific factor.
**Used by:** `Recipes/Form`, `RecipeLine` accessors, `SmartImport`, `WastageForm`.
**Notes:** Checks ingredient-specific `IngredientUomConversion` first, falls back to standard `base_unit_factor`. Handles reverse conversions.

---

## CostSummaryService
**File:** [app/Services/CostSummaryService.php](../app/Services/CostSummaryService.php)
**Purpose:** Monthly P&L engine — COGS, cost %, category breakdown.
**Public methods:**
- `generate(string $period, ?int $outletId, ?string $startDate, ?string $endDate): array` — returns revenue, opening/closing stock, purchases, transfers in/out, wastage, staff meals, COGS, cost %.
**Used by:** [Reports/Index](../app/Livewire/Reports/Index.php), [Dashboard](../app/Livewire/Dashboard.php).
**Notes:** Groups by department → sales_category. Custom date ranges skip stock takes. Handles multi-outlet rollup and MTD comparison by calling with different period bounds.

---

## CsvExportService
**File:** [app/Services/CsvExportService.php](../app/Services/CsvExportService.php)
**Purpose:** Streamed CSV download helper (UTF-8 BOM for Excel).
**Public methods:**
- `download(string $filename, array $headers, iterable $rows): StreamedResponse`
**Used by:** Ingredients export, Sales export, Purchasing export, etc.
**Notes:** Uses `php://output` — memory-efficient for large exports.

---

## AiAnalyticsService
**File:** [app/Services/AiAnalyticsService.php](../app/Services/AiAnalyticsService.php)
**Purpose:** Call Claude/OpenRouter for operational insights and cache results.
**Public methods:**
- `analyze(string $type, ?int $outletId, string $period, ?string $customContext = null): array` — run analysis, cached 24h.
- `buildContext(string $period, ?int $outletId): array` — gather cost, sales, events, historical context.
- `buildPrompt(array $context, string $type, ?string $custom): string`
**Used by:** [Sales/Index](../app/Livewire/Sales/Index.php), [Analytics/Index](../app/Livewire/Analytics/Index.php).
**Notes:** Cache key = hash of prompt. Logs to `AiAnalysisLog`. Prompt includes cost comparisons, daily trends, events, targets.

---

## AiInvoiceExtractionService
**File:** [app/Services/AiInvoiceExtractionService.php](../app/Services/AiInvoiceExtractionService.php)
**Purpose:** Vision-API extraction of supplier invoice data.
**Public methods:**
- `extract(string $path): array` — returns `{data, tokens, model}`.
**Used by:** [Purchasing/InvoiceReceive](../app/Livewire/Purchasing/InvoiceReceive.php), Price Watcher flow.
**Notes:** Static. OpenRouter Claude Vision API; supports PDF/image. Writes an `AiInvoiceScan` when chained into `ProcurementInvoiceService::createFromAiScan`.

---

## VisionService
**File:** [app/Services/VisionService.php](../app/Services/VisionService.php)
**Purpose:** OCR + parser for Z-reports and POS receipts.
**Public methods:**
- `extractText(string $path): string` — Google Vision OCR.
- `parseZReport(string $ocr): array` — returns `{date, departments, sessions, net_sales, total_bills}`.
- `parseSalesText(string $ocr): array` — line items `{item_name, quantity, unit_price, total_revenue}`.
**Used by:** [Sales/Import](../app/Livewire/Sales/Import.php).
**Notes:** Multiple date formats supported. Regex-based — handles both tabular and compact POS receipts.

---

## ChipInService
**File:** [app/Services/ChipInService.php](../app/Services/ChipInService.php)
**Purpose:** CHIP-IN payment gateway integration for subscription billing.
**Public methods:**
- `createPurchase(Company, Subscription, float $amount, string $currency, ?string $couponCode): array` — returns `{success, payment_id, checkout_url, purchase_id}`.
- `getPaymentStatus(string $purchaseId): ?array`
- `verifyWebhook(string $payload, string $signature): bool` — HMAC-SHA256.
**Used by:** [Billing/Checkout](../app/Livewire/Billing/Checkout.php), [ChipInWebhookController](../app/Http/Controllers/Webhook/).
**Notes:** Creates pending `Payment` row before calling API. Amount encoded in cents.

---

## CompanyRegistrationService
**File:** [app/Services/CompanyRegistrationService.php](../app/Services/CompanyRegistrationService.php)
**Purpose:** Atomically register a new tenant.
**Public methods:**
- `register(array $data): array` — returns `{company, user, outlet, subscription}`.
**Used by:** [Auth/SaasRegister](../app/Livewire/Auth/SaasRegister.php).
**Notes:** DB transaction. Generates unique slug, assigns Super Admin role, grants permissions, tracks referral cookie via `ReferralService::recordSignup`.

---

## CouponService
**File:** [app/Services/CouponService.php](../app/Services/CouponService.php)
**Purpose:** Validate and redeem subscription coupons.
**Public methods:**
- `validate(string $code, Company): Coupon` — throws on invalid.
- `redeem(Coupon, Company, ?int $extendMonths): Subscription` — extends subscription period.
**Used by:** [Billing/Index](../app/Livewire/Billing/Index.php).
**Notes:** DB transaction with row lock. Caps subscription end at 2037-12-31 (MySQL TIMESTAMP overflow).

---

## CreditNoteService
**File:** [app/Services/CreditNoteService.php](../app/Services/CreditNoteService.php)
**Purpose:** Debit/credit notes from GRN variances; apply credits to invoices.
**Public methods:**
- `generateFromGrn(GoodsReceivedNote): ?CreditNote` — auto-create for damaged/rejected/short-delivered lines.
- `applyToInvoice(CreditNote): void` — offset `ProcurementInvoice` balance.
**Used by:** [Purchasing/GrnReceiveForm](../app/Livewire/Purchasing/GrnReceiveForm.php), [Purchasing/CreditNoteForm](../app/Livewire/Purchasing/CreditNoteForm.php).

---

## EngineMailerService
**File:** [app/Services/EngineMailerService.php](../app/Services/EngineMailerService.php)
**Purpose:** Transactional email via EngineMailer V2 REST API.
**Public methods:**
- `testConnection(string, string): array`
- `send(string $to, string $subject, string $html, string $from, string $fromName, array $cc = [], array $attachments = []): array`
**Used by:** [PoEmailService](../app/Services/PoEmailService.php), [SupplierNotificationService](../app/Services/SupplierNotificationService.php).
**Notes:** API key via `APIKey` header. HTTP 200 even on errors — always check body `StatusCode`.

---

## InvoiceMatchingService
**File:** [app/Services/InvoiceMatchingService.php](../app/Services/InvoiceMatchingService.php)
**Purpose:** Fuzzy-match extracted invoice data to suppliers, POs, GRNs, and ingredient lines.
**Public methods:**
- `match(array $extracted, int $companyId): array` — returns matched records + exception flags.
**Used by:** [Purchasing/InvoiceReceive](../app/Livewire/Purchasing/InvoiceReceive.php).
**Notes:** Static. Word-overlap fuzzy matching (30% confidence threshold). Checks `SupplierItemAlias` first (learned aliases), then PO lines. Detects duplicate invoices, total mismatches, price/qty variances.

---

## InvoiceService
**File:** [app/Services/InvoiceService.php](../app/Services/InvoiceService.php)
**Purpose:** Create billing `Invoice` rows from `Payment` records.
**Public methods:**
- `createFromPayment(Payment): Invoice`
**Used by:** [ChipInWebhookController](../app/Http/Controllers/Webhook/).

---

## OrderAdjustmentService
**File:** [app/Services/OrderAdjustmentService.php](../app/Services/OrderAdjustmentService.php)
**Purpose:** Track PO line qty/cost adjustments with audit logs.
**Public methods:**
- `adjustQuantity(PurchaseOrderLine, float, ?string $reason): void`
- `adjustUnitCost(PurchaseOrderLine, float, ?string $reason): void`
- `recalculatePoTotals(PurchaseOrder): void`
- `getHistory(PurchaseOrderLine): Collection`
- `nextDeliverySequence(int $poId): int`
**Used by:** [Purchasing/OrderForm](../app/Livewire/Purchasing/OrderForm.php), [Purchasing/ConvertToDoForm](../app/Livewire/Purchasing/ConvertToDoForm.php).
**Notes:** Writes `OrderAdjustmentLog`. Preserves `original_quantity` on first adjustment.

---

## PoEmailService
**File:** [app/Services/PoEmailService.php](../app/Services/PoEmailService.php)
**Purpose:** Email approved POs to suppliers with PDF attachment.
**Public methods:**
- `sendApprovedPoEmail(PurchaseOrder): array`
**Used by:** [Purchasing/Index](../app/Livewire/Purchasing/Index.php), [SupplierNotificationService](../app/Services/SupplierNotificationService.php).
**Notes:** Static. Generates PDF via [pdf/purchase-order.blade.php](../resources/views/pdf/purchase-order.blade.php). De-duplicates CC list (approver, receiver, creator, company notify list), removes supplier email from CC.

---

## PoSplitService
**File:** [app/Services/PoSplitService.php](../app/Services/PoSplitService.php)
**Purpose:** Split multi-supplier order lines into one PO per supplier.
**Public methods:**
- `splitAndCreate(array $lines, array $meta): array` — returns created PO IDs.
**Used by:** [Purchasing/OrderForm](../app/Livewire/Purchasing/OrderForm.php) when `is_multi_supplier=true`.
**Notes:** DB transaction. Generates PO numbers with date + sequence. Calculates tax per supplier group.

---

## PriceMonitoringService
**File:** [app/Services/PriceMonitoringService.php](../app/Services/PriceMonitoringService.php)
**Purpose:** Detect price changes, raise notifications, evaluate alerts.
**Public methods:**
- `autoDetectChanges(Company): int` — scan `SupplierIngredient`, write `PriceChangeNotification` when over threshold.
- `compareSupplierPrices(int $ingredientId): Collection`
- `checkAlerts(): array` — evaluate `SupplierPriceAlert`, log matches.
- `getRecentPriceChanges(int $days, float $minChange): Collection`
**Used by:** Scheduled tasks, [Settings/PriceAlerts](../app/Livewire/Settings/PriceAlerts.php), [Reports/PriceHistory](../app/Livewire/Reports/PriceHistory.php).

---

## ProcurementInvoiceService
**File:** [app/Services/ProcurementInvoiceService.php](../app/Services/ProcurementInvoiceService.php)
**Purpose:** Supplier invoice lifecycle — create from GRN, mark paid, cancel.
**Public methods:**
- `createFromGrn(GoodsReceivedNote): ProcurementInvoice` — tax calculated via `TaxCalculationService`, due date +30d.
- `markPaid(ProcurementInvoice): void`
- `cancel(ProcurementInvoice): void`
- `createFromAiScan(array $data, array $meta, AiInvoiceScan): ProcurementInvoice`
**Used by:** [Purchasing/GrnReceiveForm](../app/Livewire/Purchasing/GrnReceiveForm.php), [Purchasing/InvoiceReceive](../app/Livewire/Purchasing/InvoiceReceive.php).

---

## PurchaseRequestService
**File:** [app/Services/PurchaseRequestService.php](../app/Services/PurchaseRequestService.php)
**Purpose:** PR number generation + PR→PO consolidation.
**Public methods:**
- `generatePrNumber(): string` — `PR-YYYYMMDD-NNN`.
- `consolidate(array $prIds, int $companyId): array` — one PO per supplier, kitchen items split into production orders.
- `consolidationPreview(array $prIds): Collection`
- `consolidationPreviewWithCosts(array $prIds): array`
- `consolidateFromCustomized(array $prIds, int $companyId, array $customized): array` — honors user edits (exclude lines, reassign suppliers).
**Used by:** [Purchasing/ConsolidateForm](../app/Livewire/Purchasing/ConsolidateForm.php).
**Notes:** DB transactions. Merges same-ingredient qty per supplier.

---

## ReferralService
**File:** [app/Services/ReferralService.php](../app/Services/ReferralService.php)
**Purpose:** Referral codes, click/signup tracking, commission calc.
**Public methods:**
- `generateCode(User): ReferralCode`
- `generateCodeForAffiliate(Affiliate): ReferralCode`
- `trackClick(string $code): ?ReferralCode`
- `recordSignup(Company, string $code): ?Referral` — prevents self-referrals.
- `calculateCommission(Payment): ?Commission`
**Used by:** [CompanyRegistrationService](../app/Services/CompanyRegistrationService.php), [Billing/Checkout](../app/Livewire/Billing/Checkout.php), [ReferralTrackingController](../app/Http/Controllers/ReferralTrackingController.php).
**Notes:** 6-char alphanumeric codes. Honors `max_payouts`, `is_recurring`, per-plan rules.

---

## RfqService
**File:** [app/Services/RfqService.php](../app/Services/RfqService.php)
**Purpose:** RFQ send + quotation accept → PO.
**Public methods:**
- `send(QuotationRequest): void` — mark suppliers pending, set RFQ sent, notify suppliers.
- `acceptAndCreatePo(SupplierQuotation): PurchaseOrder` — reject other quotations, close RFQ, generate PO.
**Used by:** [Purchasing/RfqShow](../app/Livewire/Purchasing/RfqShow.php), [Purchasing/RfqForm](../app/Livewire/Purchasing/RfqForm.php).
**Notes:** DB transaction. Uses `SupplierNotificationService`.

---

## StockTransferService
**File:** [app/Services/StockTransferService.php](../app/Services/StockTransferService.php)
**Purpose:** Stock transfer orders between outlets/kitchen, optionally chargeable.
**Public methods:**
- `generateStoNumber(): string` — `STO-YYYYMMDD-NNN`.
- `create(array $meta, array $lines): StockTransferOrder` — tax auto-calculated if `is_chargeable=true`.
- `generateInvoice(StockTransferOrder): ProcurementInvoice`
**Used by:** [Purchasing/StockTransferForm](../app/Livewire/Purchasing/StockTransferForm.php).

---

## SubscriptionService
**File:** [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php)
**Purpose:** Subscription lifecycle + feature gate + usage limits.
**Public methods:**
- `createTrial(Company, Plan, ?string): Subscription`
- `activate(Subscription): Subscription`
- `cancel(Subscription): Subscription`
- `renew(Subscription): Subscription`
- `markPastDue(Subscription): Subscription`
- `expire(Subscription): Subscription`
- `changePlan(Subscription, Plan): Subscription`
- `canUseFeature(Company, string $feature): bool`
- `checkUsage(Company, string $metric): array` — returns `{allowed, current, limit}`.
- `enforceLimit(Company, string $metric): void` — throws `LimitReachedException`.
- `getActiveSubscription(Company): ?Subscription`
**Used by:** [CheckFeatureAccess](../app/Http/Middleware/CheckFeatureAccess.php), [EnforceSubscription](../app/Http/Middleware/EnforceSubscription.php), [Billing/Index](../app/Livewire/Billing/Index.php).
**Notes:** Grandfathered companies (no subscription) get unlimited. Metrics: `outlets`, `users`, `recipes`, `ingredients`, `lms_users`.

---

## SupplierNotificationService
**File:** [app/Services/SupplierNotificationService.php](../app/Services/SupplierNotificationService.php)
**Purpose:** Notify suppliers (PO, RFQ) via email or WhatsApp.
**Public methods:**
- `notifyPo(PurchaseOrder): array`
- `notifyRfq(Supplier, QuotationRequest): array` — includes supplier portal login URL.
**Used by:** [RfqService](../app/Services/RfqService.php), [PoEmailService](../app/Services/PoEmailService.php).
**Notes:** WhatsApp currently a placeholder. Dispatch driven by `supplier.notification_preference`.

---

## TaxCalculationService
**File:** [app/Services/TaxCalculationService.php](../app/Services/TaxCalculationService.php)
**Purpose:** Tax calculation with inclusive/exclusive rates and company defaults.
**Public methods:**
- `calculate(float $subtotal, ?int $taxRateId, ?Company): array` — returns `{tax_amount, total, rate, name}`.
- `grandTotal(float $subtotal, float $tax, float $delivery): float`
**Used by:** [ProcurementInvoiceService](../app/Services/ProcurementInvoiceService.php), [StockTransferService](../app/Services/StockTransferService.php), [PoSplitService](../app/Services/PoSplitService.php).
**Notes:** Resolves in order: explicit `TaxRate` → company default → legacy `tax_percent`.

---

## UsageTrackingService
**File:** [app/Services/UsageTrackingService.php](../app/Services/UsageTrackingService.php)
**Purpose:** Snapshot and track metered usage (for billing & feature limits).
**Public methods:**
- `snapshot(Company): array` — writes `UsageRecord`, returns `{metric => count}`.
- `snapshotAll(): int` — snapshot all active companies.
- `getCurrentCounts(Company): array`
**Used by:** Scheduled tasks, [Admin/CompanyHealth](../app/Livewire/Admin/CompanyHealth.php).
**Notes:** Metrics: `outlets`, `users`, `recipes`, `ingredients`, `lms_users`.

---

## Patterns

- **Static vs instance** — mixed. Most are static with DB-only dependencies; a few (`UomService`) are instance-based for Ioc.
- **DB transactions** — services that write multiple tables wrap in `DB::transaction` (PoSplit, RfqService, CouponService, CompanyRegistration, PurchaseRequestService).
- **Audit trails** — `OrderAdjustmentLog`, `IngredientPriceHistory`, `AiAnalysisLog`, `AuditLog` populated by services, not controllers.
- **External APIs** — `ChipInService`, `EngineMailerService`, `AiInvoiceExtractionService`, `VisionService`, `AiAnalyticsService`. Keep API keys in `.env`.
