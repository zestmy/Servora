# Servora Domain Model

Quick model lookup by business domain. Each model row gives key columns, relationships, and notable behaviors. Open the linked file for accessors, casts, and observers.

> **Multi-tenancy.** Most tenant models apply `CompanyScope` in `booted()` to scope all queries by `company_id`. Models without the scope expect explicit filtering. See [docs/01-architecture.md](01-architecture.md) for details.

---

## 1. Tenancy & Access

| Model | Key columns / traits | Key relationships | Notable behaviors |
|-------|----------------------|-------------------|-------------------|
| [Company](../app/Models/Company.php) | `name`, `brand_name`, `currency`, `tax_percent`, `timezone`, `ordering_mode`, `require_po_approval`, `require_pr_approval`, `auto_generate_do`, `direct_supplier_order`, `show_price_on_do_grn`, `price_alert_threshold`, `trial_ends_at`, `onboarding_completed_at` | `HasMany` → outlets, users, suppliers, ingredients, recipes, cpus; `HasOne` → activeSubscription | SoftDeletes; helpers `isOnTrial()`, `isCpuMode()`; numerous feature toggles |
| [Outlet](../app/Models/Outlet.php) | `company_id`, `name`, `code`, `phone`, `address`, `is_active` | `BelongsTo` → Company; `BelongsToMany` → users (pivot), recipes, groups | SoftDeletes; **no** CompanyScope (queries must filter) |
| [OutletGroup](../app/Models/OutletGroup.php) | `company_id`, `name`, `sort_order`, `is_active` | `BelongsTo` → Company; `BelongsToMany` → outlets | CompanyScope |
| [User](../app/Models/User.php) | `email`, `password`, `company_id`, `outlet_id`, `timezone`, `workspace_mode`, `roles` (Spatie) | `BelongsTo` → Company, Outlet; `BelongsToMany` → outlets | Helpers `canAccessOutlet()`, `activeOutletId()`, `isSystemRole()` |
| [SupplierUser](../app/Models/SupplierUser.php) | `supplier_id`, `email`, `password`, `is_admin` | `BelongsTo` → Supplier | Separate `supplier` auth guard |
| [LmsUser](../app/Models/LmsUser.php) | `company_id`, `outlet_id`, `email`, `password`, `approver_id` | `BelongsTo` → Company, Outlet, approver | Training/LMS guard; `scopeApproved()` |
| [Affiliate](../app/Models/Affiliate.php) | `email`, `password`, bank fields | `HasOne` → referralCode | Separate `affiliate` auth guard |
| [Department](../app/Models/Department.php) | `company_id`, `name`, `sort_order`, `is_active`, `sales_category_id` | `BelongsTo` → Company, SalesCategory | CompanyScope; scopes `active()`, `ordered()` |
| [Section](../app/Models/Section.php) | `company_id`, `name`, `sort_order`, `is_active` | `BelongsTo` → Company; `HasMany` → employees | CompanyScope |

---

## 2. Catalog

| Model | Key columns / traits | Key relationships | Notable behaviors |
|-------|----------------------|-------------------|-------------------|
| [Ingredient](../app/Models/Ingredient.php) | `company_id`, `name` (uppercased), `code`, `base_uom_id`, `recipe_uom_id`, `purchase_price`, `pack_size`, `yield_percent`, `current_cost`, `ingredient_category_id`, `tax_rate_id`, `is_active`, `is_prep`, `prep_recipe_id`, `remark` | Company, IngredientCategory, TaxRate, baseUom, recipeUom, prepRecipe; `HasMany` → uomConversions, priceHistory; `BelongsToMany` → outlets | CompanyScope + SoftDeletes; `saving()` uppercases name; `effectiveTaxRate()` resolves ingredient or company default |
| [IngredientCategory](../app/Models/IngredientCategory.php) | `company_id`, `parent_id`, `name`, `color`, `sort_order`, `type`, `is_active` | self-referential parent/child | CompanyScope; 2-level hierarchy |
| [IngredientUomConversion](../app/Models/IngredientUomConversion.php) | `ingredient_id`, `from_uom_id`, `to_uom_id`, `conversion_factor` | | Used by `UomService::convertQuantity/convertCost` |
| [IngredientPriceHistory](../app/Models/IngredientPriceHistory.php) | `ingredient_id`, `price`, `effective_date`, `supplier_id` | | Audit trail — written on GRN receipt, Price Watcher import, manual edit |
| [UnitOfMeasure](../app/Models/UnitOfMeasure.php) | `name`, `code`, `abbreviation`, `base_unit_factor`, `serving` | | Global (not CompanyScoped) reference data |
| [Recipe](../app/Models/Recipe.php) | `company_id`, `name` (uppercased), `code`, `description`, `video_url`, `yield_quantity`, `yield_uom_id`, `selling_price`, `cost_per_yield_unit`, `extra_costs` (array), `department_id`, `ingredient_category_id`, `is_active`, `is_prep`, `exclude_from_lms`, `menu_sort_order` | Company, Department, IngredientCategory, yieldUom; `HasMany` → lines, images, steps, prices; `BelongsToMany` → outlets; `HasOne` → ingredient (for prep recipes) | CompanyScope + SoftDeletes; `getTotalCostAttribute()` accessor |
| [RecipeLine](../app/Models/RecipeLine.php) | `recipe_id`, `ingredient_id`, `quantity`, `uom_id`, `waste_percentage`, `is_packaging`, `sort_order` | | |
| [RecipeCategory](../app/Models/RecipeCategory.php) | `company_id`, `parent_id`, `name`, `color`, `sort_order`, `is_active` | parent/child | CompanyScope |
| [RecipeImage](../app/Models/RecipeImage.php) | `recipe_id`, `type` (dine-in / takeaway), `image_path`, `sort_order` | | |
| [RecipeStep](../app/Models/RecipeStep.php) | `recipe_id`, `sort_order`, `title`, `instruction`, `image_path` | | SOP training content |
| [RecipePrice](../app/Models/RecipePrice.php) | `recipe_id`, `recipe_price_class_id`, `selling_price` | | Multi-price per recipe |
| [RecipePriceClass](../app/Models/RecipePriceClass.php) | `company_id`, `name`, `sort_order`, `is_default` | | CompanyScope |
| [TaxRate](../app/Models/TaxRate.php) | `name`, `percentage`, `is_active`, `is_inclusive` | | `defaultForCompany()` static |

---

## 3. Suppliers

| Model | Key columns / traits | Key relationships | Notable behaviors |
|-------|----------------------|-------------------|-------------------|
| [Supplier](../app/Models/Supplier.php) | `company_id` (nullable for global), `name`, `code`, `contact_person`, `email`, `phone`, `address`, `is_active`, `portal_enabled`, `payment_terms`, `whatsapp_number`, `notification_preference` | Company; `BelongsToMany` → ingredients (pivot: supplier_sku, last_cost, uom_id, pack_size, is_preferred); `HasMany` → POs, users, products | CompanyScope + SoftDeletes |
| [SupplierIngredient](../app/Models/SupplierIngredient.php) | pivot: `supplier_id`, `ingredient_id`, `supplier_sku`, `last_cost`, `uom_id`, `pack_size`, `is_preferred` | | Source of truth for supplier-specific pricing |
| [SupplierProduct](../app/Models/SupplierProduct.php) | `supplier_id`, `sku`, `name`, `category`, `description` | Supplier | CompanyScope via supplier |
| [SupplierProductMapping](../app/Models/SupplierProductMapping.php) | `company_id`, `supplier_product_id`, `ingredient_id` | | CompanyScope — ties external product → internal ingredient |
| [SupplierItemAlias](../app/Models/SupplierItemAlias.php) | `company_id`, `supplier_id`, `ingredient_id`, `alias_name` | | CompanyScope — used by `InvoiceMatchingService` for fuzzy matching |
| [SupplierQuotation](../app/Models/SupplierQuotation.php) | `quotation_request_id`, `supplier_id`, `quotation_number`, `status`, `valid_until`, `subtotal`, `tax_amount`, `delivery_charges`, `total_amount` | QuotationRequest, Supplier, TaxRate, `HasMany` → lines | `generateNumber()` → `QTN-YYYYMMDD-NNN` |
| [SupplierPriceAlert](../app/Models/SupplierPriceAlert.php) | `company_id`, `supplier_id`, `ingredient_id`, `alert_type`, `threshold_value` | | CompanyScope; evaluated by `PriceMonitoringService::checkAlerts()` |
| [QuotationRequest](../app/Models/QuotationRequest.php) | `company_id`, `outlet_id`, `reference_number`, `status`, `needed_date`, `created_by` | `HasMany` → quotations, suppliers, lines | CompanyScope |
| [QuotationRequestSupplier](../app/Models/QuotationRequestSupplier.php) | `quotation_request_id`, `supplier_id`, `status` | | Join table tracking per-supplier status |
| [PriceChangeNotification](../app/Models/PriceChangeNotification.php) | `company_id`, `ingredient_id`, `old_price`, `new_price`, `change_percentage`, `notified_at` | | CompanyScope — written by `PriceMonitoringService::autoDetectChanges()` |

---

## 4. Purchasing

| Model | Key columns / traits | Key relationships | Notable behaviors |
|-------|----------------------|-------------------|-------------------|
| [PurchaseRequest](../app/Models/PurchaseRequest.php) | `company_id`, `outlet_id`, `pr_number`, `status` (draft/submitted/approved/rejected/converted/cancelled), `needed_date`, `department_id`, `created_by`, `approved_by` | Company, Outlet, Department, creator/approver; `HasMany` → lines | CompanyScope + SoftDeletes |
| [PurchaseRequestLine](../app/Models/PurchaseRequestLine.php) | `purchase_request_id`, `ingredient_id`, `quantity`, `uom_id`, `source` (outlet/kitchen) | | |
| [PurchaseOrder](../app/Models/PurchaseOrder.php) | `company_id`, `outlet_id`, `supplier_id`, `po_number`, `status` (draft/sent/partial/received/cancelled), `order_date`, `expected_delivery_date`, `subtotal`, `tax_percent`, `tax_amount`, `total_amount`, `department_id`, `purchase_request_id`, `cpu_id`, `parent_po_id`, `is_multi_supplier`, `delivery_charges`, `tax_rate_id`, `source` | Company, Outlet, Supplier, Department, creator/approver, PR, CPU, TaxRate; `HasMany` → lines, DOs, GRNs | CompanyScope + SoftDeletes; multi-supplier split via `PoSplitService` |
| [PurchaseOrderLine](../app/Models/PurchaseOrderLine.php) | `purchase_order_id`, `ingredient_id`, `quantity`, `unit_price`, `line_total`, `uom_id`, `original_quantity`, `adjusted_at` | | Adjustment tracking columns |
| [DeliveryOrder](../app/Models/DeliveryOrder.php) | `company_id`, `purchase_order_id`, `do_number`, `delivery_date`, `status`, `notes` | PO; `HasMany` → lines | CompanyScope + SoftDeletes; partial-delivery fields |
| [DeliveryOrderLine](../app/Models/DeliveryOrderLine.php) | `delivery_order_id`, `purchase_order_line_id`, `quantity_delivered` | | |
| [GoodsReceivedNote](../app/Models/GoodsReceivedNote.php) | `company_id`, `purchase_order_id`, `grn_number`, `received_date`, `status`, `received_by` | PO; `HasMany` → lines | CompanyScope + SoftDeletes |
| [GoodsReceivedNoteLine](../app/Models/GoodsReceivedNoteLine.php) | `grn_id`, `po_line_id`, `quantity_received`, `quantity_damaged`, `remarks` | | |
| [PurchaseRecord](../app/Models/PurchaseRecord.php) | `company_id`, `outlet_id`, `supplier_id`, `pr_number`, `record_date`, `total_amount` | `HasMany` → lines | CompanyScope + SoftDeletes — historical purchases (cash/non-PO) |
| [PurchaseRecordLine](../app/Models/PurchaseRecordLine.php) | `purchase_record_id`, `ingredient_id`, `quantity`, `unit_price`, `line_total` | | |
| [ProcurementInvoice](../app/Models/ProcurementInvoice.php) | `company_id`, `supplier_id`, `invoice_number`, `invoice_date`, `due_date`, `subtotal`, `tax_amount`, `total_amount`, `status`, `credit_applied` | Supplier; `HasMany` → lines | CompanyScope; `ProcurementInvoiceService::createFromGrn` |
| [ProcurementInvoiceLine](../app/Models/ProcurementInvoiceLine.php) | `procurement_invoice_id`, `description`, `quantity`, `unit_price`, `line_total` | | |
| [CreditNote](../app/Models/CreditNote.php) | `company_id`, `supplier_id`, `credit_note_number`, `date`, `reason`, `total_amount` | `HasMany` → lines | CompanyScope |
| [CreditNoteLine](../app/Models/CreditNoteLine.php) | `credit_note_id`, `ingredient_id`, `quantity`, `unit_price`, `line_total` | | |
| [PoApprover](../app/Models/PoApprover.php) | `company_id`, `outlet_id`, `department_id`, `user_id`, `assigned_by` | | CompanyScope — approval matrix |
| [PrApprover](../app/Models/PrApprover.php) | `company_id`, `outlet_id`, `department_id`, `user_id`, `assigned_by` | | CompanyScope |
| [FormTemplate](../app/Models/FormTemplate.php) | `company_id`, `name`, `form_type`, `supplier_id`, `department_id`, `is_active`, `sort_order`, header fields | `HasMany` → lines | CompanyScope — reusable PO templates |
| [FormTemplateLine](../app/Models/FormTemplateLine.php) | `form_template_id`, `ingredient_id`, `quantity`, `uom_id`, `sort_order` | | |
| [OrderAdjustmentLog](../app/Models/OrderAdjustmentLog.php) | `order_type`, `order_id`, `previous_value`, `new_value`, `adjusted_by`, `reason`, `adjusted_at` | | Written by `OrderAdjustmentService` |

---

## 5. Inventory

| Model | Key columns / traits | Key relationships | Notable behaviors |
|-------|----------------------|-------------------|-------------------|
| [StockTake](../app/Models/StockTake.php) | `company_id`, `outlet_id`, `department_id`, `reference_number`, `status`, `method` (detailed/summary), `stock_take_date`, `total_stock_cost`, `total_variance_cost` | `HasMany` → lines | CompanyScope + SoftDeletes |
| [StockTakeLine](../app/Models/StockTakeLine.php) | `stock_take_id`, `ingredient_id`, `system_qty`, `counted_qty`, `variance_qty`, `variance_cost` | | |
| [WastageRecord](../app/Models/WastageRecord.php) | `company_id`, `outlet_id`, `reference_number`, `wastage_date` | `HasMany` → lines | CompanyScope + SoftDeletes |
| [WastageRecordLine](../app/Models/WastageRecordLine.php) | `wastage_record_id`, `ingredient_id`, `recipe_id`, `quantity`, `reason`, `cost` | | |
| [StaffMealRecord](../app/Models/StaffMealRecord.php) | `company_id`, `outlet_id`, `date` | `HasMany` → lines | CompanyScope + SoftDeletes |
| [StaffMealRecordLine](../app/Models/StaffMealRecordLine.php) | `staff_meal_record_id`, `recipe_id`/`ingredient_id`, `quantity` | | |
| [OutletTransfer](../app/Models/OutletTransfer.php) | `company_id`, `from_outlet_id`, `to_outlet_id`, `reference_number`, `transfer_date`, `status` (draft/in_transit/received/cancelled) | `HasMany` → lines | CompanyScope + SoftDeletes; `ScopesToActiveOutlet` applicable in queries |
| [OutletTransferLine](../app/Models/OutletTransferLine.php) | `outlet_transfer_id`, `ingredient_id`, `quantity`, `uom_id` | | |
| [IngredientParLevel](../app/Models/IngredientParLevel.php) | `company_id`, `outlet_id`, `ingredient_id`, `par_level`, `reorder_point` | | CompanyScope — drives auto-ordering in `OrderForm` |
| [StockTransferOrder](../app/Models/StockTransferOrder.php) | `company_id`, `from_outlet_id`, `to_outlet_id`, `order_number`, `status`, `needed_date`, `is_chargeable` | `HasMany` → lines | CompanyScope — chargeable transfers generate ProcurementInvoice |
| [StockTransferOrderLine](../app/Models/StockTransferOrderLine.php) | `stock_transfer_order_id`, `ingredient_id`, `quantity_requested`, `quantity_sent` | | |
| [KitchenInventory](../app/Models/KitchenInventory.php) | `kitchen_id`, `ingredient_id`, `quantity_on_hand`, `last_updated` | | Central-kitchen on-hand |

---

## 6. Sales

| Model | Key columns / traits | Key relationships | Notable behaviors |
|-------|----------------------|-------------------|-------------------|
| [SalesRecord](../app/Models/SalesRecord.php) | `company_id`, `outlet_id`, `reference_number`, `sale_date`, `total_revenue`, `total_cost`, `pax`, `meal_period` (all_day/breakfast/lunch/tea_time/dinner/supper) | `HasMany` → lines, attachments | CompanyScope + SoftDeletes; `mealPeriodLabel()` helper |
| [SalesRecordLine](../app/Models/SalesRecordLine.php) | `sales_record_id`, `recipe_id`, `sales_category_id`, `quantity_sold`, `unit_price`, `line_total` | | |
| [SalesRecordAttachment](../app/Models/SalesRecordAttachment.php) | `sales_record_id`, `file_path`, `file_name` | | Receipts / Z-report uploads |
| [SalesCategory](../app/Models/SalesCategory.php) | `company_id`, `name`, `color`, `sort_order`, `is_active`, `is_revenue`, `ingredient_category_id` | | CompanyScope |
| [SalesClosure](../app/Models/SalesClosure.php) | `company_id`, `outlet_id`, `date`, `status`, `total_revenue` | | CompanyScope |
| [SalesTarget](../app/Models/SalesTarget.php) | `company_id`, `outlet_id`, `period`, `target_amount`, `target_pax` | | CompanyScope |

---

## 7. Kitchen / Production / CPU

| Model | Key columns / traits | Key relationships | Notable behaviors |
|-------|----------------------|-------------------|-------------------|
| [CentralKitchen](../app/Models/CentralKitchen.php) | `company_id`, `name`, `code`, `is_active` | `HasMany` → productionOrders | CompanyScope |
| [CentralPurchasingUnit](../app/Models/CentralPurchasingUnit.php) | `company_id`, `name`, `code`, contact fields | `HasMany` → POs | CompanyScope — enables `ordering_mode = cpu` |
| [ProductionOrder](../app/Models/ProductionOrder.php) | `company_id`, `kitchen_id`, `order_number`, `status`, `production_date`, `needed_by_date`, `started_at`, `completed_at` | `HasMany` → lines, logs | CompanyScope + SoftDeletes; `generateNumber()` → `PROD-YYYYMMDD-NNN` |
| [ProductionOrderLine](../app/Models/ProductionOrderLine.php) | `production_order_id`, `recipe_id`, `quantity_needed`, `quantity_produced` | | |
| [ProductionRecipe](../app/Models/ProductionRecipe.php) | `company_id`, `name`, `yield_quantity`, `yield_uom_id` | `HasMany` → lines | CompanyScope; kitchen-specific recipes |
| [ProductionRecipeLine](../app/Models/ProductionRecipeLine.php) | `production_recipe_id`, `ingredient_id`, `quantity`, `uom_id` | | |
| [ProductionLog](../app/Models/ProductionLog.php) | `production_order_id`, `stage`, `notes`, `completed_at` | | |
| [OutletPrepRequest](../app/Models/OutletPrepRequest.php) | `company_id`, `outlet_id`, `kitchen_id`, `reference_number`, `status`, `needed_date` | `HasMany` → lines | CompanyScope |
| [OutletPrepRequestLine](../app/Models/OutletPrepRequestLine.php) | `outlet_prep_request_id`, `recipe_id`, `quantity_needed`, `quantity_received` | | |

---

## 8. HR & Labour

| Model | Key columns / traits | Key relationships | Notable behaviors |
|-------|----------------------|-------------------|-------------------|
| [Employee](../app/Models/Employee.php) | `company_id`, `outlet_id`, `section_id`, `staff_id`, `name`, `designation`, `email`, `phone`, `is_active` | | CompanyScope |
| [LabourCost](../app/Models/LabourCost.php) | `company_id`, `outlet_id`, `period`, `base_salary`, created/approved by, `overtime_amount` | `HasMany` → allowances | CompanyScope + SoftDeletes |
| [LabourCostAllowance](../app/Models/LabourCostAllowance.php) | `labour_cost_id`, `label`, `amount` | | |
| [OvertimeClaim](../app/Models/OvertimeClaim.php) | `company_id`, `outlet_id`, `section_id`, `employee_id`, `claim_date`, `hours`, `hourly_rate`, `status` | `HasMany` → approvers | CompanyScope + SoftDeletes |
| [OvertimeClaimApprover](../app/Models/OvertimeClaimApprover.php) | `company_id`, `user_id`, `outlet_id`, `section_id` | | CompanyScope |

---

## 9. AI / Documents / Audit

| Model | Key columns / traits | Key relationships | Notable behaviors |
|-------|----------------------|-------------------|-------------------|
| [AiAnalysisLog](../app/Models/AiAnalysisLog.php) | `company_id`, `model_type`, `model_id`, `analysis_type`, `result` (JSON), `confidence_score` | | CompanyScope — cached AI output |
| [AiInvoiceScan](../app/Models/AiInvoiceScan.php) | `company_id`, `supplier_id`, `document_id`, `extracted_data` (JSON), `extracted_amount`, `extracted_date`, `confidence_score` | ScannedDocument | CompanyScope |
| [ScannedDocument](../app/Models/ScannedDocument.php) | `company_id`, `document_type`, `file_path`, `uploaded_by` | | CompanyScope + SoftDeletes |
| [AuditLog](../app/Models/AuditLog.php) | `user_id`, `model_type`, `model_id`, `action`, `changes` (JSON), `ip_address` | | No CompanyScope — system-wide |

---

## 10. SaaS Billing, Marketing, Referrals

| Model | Key columns / traits | Key relationships | Notable behaviors |
|-------|----------------------|-------------------|-------------------|
| [Plan](../app/Models/Plan.php) | `name`, `slug`, `price_monthly`, `price_yearly`, feature flags, limit fields, `api_rate_limit` | `HasMany` → subscriptions | Global (not CompanyScoped) |
| [Subscription](../app/Models/Subscription.php) | `company_id`, `plan_id`, `status` (trialing/active/past_due/cancelled/expired), `billing_cycle`, `trial_ends_at`, `current_period_start`, `current_period_end`, `cancelled_at` | Company, Plan | Helpers `isActive()`, `isTrial()`, `daysRemaining()` |
| [UsageRecord](../app/Models/UsageRecord.php) | `company_id`, `feature_key`, `usage_count`, `period_start`, `period_end` | | Written by `UsageTrackingService::snapshot` |
| [Payment](../app/Models/Payment.php) | `company_id`, `subscription_id`, `amount`, `status`, `payment_method`, `transaction_id` | | |
| [Invoice](../app/Models/Invoice.php) | `company_id`, `subscription_id`, `invoice_number`, `amount`, `status` | | Written by `InvoiceService::createFromPayment` |
| [Coupon](../app/Models/Coupon.php) | `code`, `discount_percent`, `discount_amount`, `max_uses`, `uses_count`, `valid_from`, `valid_until`, `is_active` | `HasMany` → redemptions | Global |
| [CouponRedemption](../app/Models/CouponRedemption.php) | `coupon_id`, `company_id`, `order_id`, `redeemed_at` | Coupon | |
| [Announcement](../app/Models/Announcement.php) | `title`, `body`, `type`, `is_active`, `starts_at`, `ends_at` | | Global system banners |
| [Page](../app/Models/Page.php) | `slug`, `title`, `content`, `is_published`, `external_url` | | Static CMS pages |
| [OnboardingStep](../app/Models/OnboardingStep.php) | `company_id`, `step`, `completed_at` | | Onboarding wizard progress |
| [VideoShareToken](../app/Models/VideoShareToken.php) | `token`, `recipe_id`, `company_id` | | Loginless video sharing |
| [Referral](../app/Models/Referral.php) | `referrer_id`, `referred_company_id`, `status`, `commission_amount`, `completed_at` | | |
| [ReferralCode](../app/Models/ReferralCode.php) | `affiliate_id`/`user_id`, `code`, `is_active` | Affiliate/User | 6-char alphanumeric, generated by `ReferralService` |
| [ReferralProgram](../app/Models/ReferralProgram.php) | `name`, `commission_percent`, `commission_amount`, `is_recurring`, `max_payouts`, `is_active` | `HasMany` → referrals | Commission rules |
| [Commission](../app/Models/Commission.php) | `affiliate_id`, `referral_id`, `amount`, `status`, `paid_at` | | |
| [Affiliate](../app/Models/Affiliate.php) | `email`, `password`, bank fields | `HasOne` → referralCode | Separate guard |

---

## 11. Config

| Model | Key columns | Notes |
|-------|-------------|-------|
| [AppSetting](../app/Models/AppSetting.php) | `key`, `value` | Global key-value config |
| [CalendarEvent](../app/Models/CalendarEvent.php) | `company_id`, `title`, `starts_at`, `ends_at`, `event_type` | CompanyScope |

---

## Cross-cutting patterns

### `CompanyScope`
Applied in `booted()` on 60+ tenant models. Source: [app/Scopes/CompanyScope.php](../app/Scopes/CompanyScope.php). Uses `Auth::user()->company_id`. To bypass for admin queries, use `withoutGlobalScope(CompanyScope::class)`.

### SoftDeletes
Used by header/master models (Ingredient, Recipe, Supplier, PurchaseOrder, PurchaseRequest, DeliveryOrder, GRN, PurchaseRecord, SalesRecord, StockTake, WastageRecord, StaffMealRecord, OutletTransfer, LabourCost, OvertimeClaim, ScannedDocument, ProductionOrder). Detail/line tables usually don't soft-delete.

### Auto-generated numbers
- `PurchaseOrder.po_number`, `PurchaseRequest.pr_number`, `DeliveryOrder.do_number`, `GoodsReceivedNote.grn_number` — generated in services/Livewire.
- `SupplierQuotation::generateNumber()` → `QTN-YYYYMMDD-NNN`.
- `ProductionOrder::generateNumber()` → `PROD-YYYYMMDD-NNN`.
- `StockTransferService::generateStoNumber()` → `STO-YYYYMMDD-NNN`.

### Cast patterns
- Decimals: `decimal:2` (prices, totals), `decimal:4` (conversion factors).
- Booleans: `is_active`, `is_prep`, `is_preferred`, `portal_enabled`, `is_admin`.
- Dates/DateTimes: `_date`, `_at` fields cast accordingly.
- JSON: `extra_costs`, `features`, `extracted_data`, `changes`.

### `ScopesToActiveOutlet` trait
[app/Traits/ScopesToActiveOutlet.php](../app/Traits/ScopesToActiveOutlet.php). Livewire components use this to filter listings by the outlet in session.
