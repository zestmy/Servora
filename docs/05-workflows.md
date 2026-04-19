# Servora Business Workflows

Developer reference guide tracing end-to-end flows through models, services, and Livewire components. Use relative paths like `app/Services/PoSplitService.php` to navigate implementations.

## 1. Procurement Lifecycle

**Narrative**: The procurement flow moves from requisition (PR) → authorization (PO approval) → delivery (DO) → goods receipt (GRN) → accounting (PurchaseRecord, ProcurementInvoice, CreditNote). At each step, Company-level toggles control branching: `require_pr_approval`, `require_po_approval`, `auto_generate_do`, `direct_supplier_order`, `ordering_mode`, `show_price_on_do_grn`, and `price_alert_threshold`. CPU (Central Purchasing Unit) mode handles multi-outlet consolidation.

| Step | What Happens | Writes To | Status Values | Key File(s) |
|------|--------------|-----------|---------------|-----------|
| **PR Draft** | User creates purchase request with lines (ingredient + qty + UOM). Requires outlet/department context. | PurchaseRequest (draft), PurchaseRequestLine | draft, submitted, approved, rejected, converted, cancelled | `app/Livewire/Purchasing/PurchaseRequestForm.php`, `app/Models/PurchaseRequest.php` |
| **PR Approval** | If `require_pr_approval=true` on Company, PrApprover role users must approve. User can also auto-approve own requests. | PurchaseRequest.approved_by, approved_at | submitted → approved | `app/Models/PrApprover.php` |
| **PR→PO Consolidation** | Approved PRs grouped by supplier (merge same-ingredient lines across outlets). Generates one PO per supplier. In CPU mode, routed via CentralPurchasingUnit. | PurchaseOrder, PurchaseOrderLine | draft (created) | `app/Services/PurchaseRequestService.php::consolidate()`, `app/Livewire/Purchasing/ConsolidateForm.php` |
| **PO Creation** | Manually create PO or from PR. Add lines (ingredient + qty + unit_cost). Split multi-supplier orders into separate POs. | PurchaseOrder (status=draft), PurchaseOrderLine | draft, sent, partial, received, cancelled | `app/Livewire/Purchasing/OrderForm.php`, `app/Services/PoSplitService.php::splitAndCreate()` |
| **PO Approval** | If `require_po_approval=true`, PoApprover role users approve. Email sent via PoEmailService. | PurchaseOrder.approved_by | sent (after approval) | `app/Models/PoApprover.php`, `app/Services/PoEmailService.php` |
| **DO Auto-Generate** | If `auto_generate_do=true`, pressing "Send" on PO auto-creates DeliveryOrder. Otherwise manual creation. DO inherits PO totals + tax. | DeliveryOrder, DeliveryOrderLine | pending → received | `app/Livewire/Purchasing/ConvertToDoForm.php` |
| **DO Manual Entry** | If `direct_supplier_order=true`, supplier sends DO directly (no PO prerequisite). Else linked to PO. Can have multiple DOs per PO (partial delivery mode). | DeliveryOrder (status=pending), DeliveryOrderLine | pending, received, partial, rejected | `app/Livewire/Purchasing/ConvertToDoForm.php` |
| **GRN Receive** | User confirms goods received, updates received_qty per line, selects condition (good/damaged/rejected). Generates GRN number. Price watcher updates current_cost if new price detected. | GoodsReceivedNote (status=pending → received), GoodsReceivedNoteLine (condition, received_qty), IngredientPriceHistory | pending, partial, received, rejected | `app/Livewire/Purchasing/GrnReceiveForm.php`, `app/Livewire/Purchasing/ReceiveForm.php` |
| **PurchaseRecord** | Non-PO direct purchases logged here (e.g., cash buys). Links to DeliveryOrder if available. Used in COGS. | PurchaseRecord, PurchaseRecordLine | — | `app/Models/PurchaseRecord.php`, `app/Models/PurchaseRecordLine.php` |
| **Invoice Issuance** | Auto-generated from GRN or manual entry. Captures supplier invoice number, due date, taxes. InvoiceMatchingService reconciles invoice qty vs. GRN qty. | ProcurementInvoice (status=issued), ProcurementInvoiceLine | draft, issued, paid, cancelled, overdue | `app/Services/ProcurementInvoiceService.php::createFromGrn()`, `app/Livewire/Purchasing/InvoiceReceive.php` |
| **Credit Note** | Supplier issue (qty adjustment, price variance, damage). Direction: supplier (inbound) or buyer (outbound). Auto-applied to invoice balance. | CreditNote (status=draft → issued → applied), CreditNoteLine | draft, issued, acknowledged, applied, cancelled | `app/Services/CreditNoteService.php`, `app/Livewire/Purchasing/CreditNoteForm.php` |

### Approval Roles & Company Settings

- **PrApprover** (table: `pr_approvers`): Designate users who approve purchase requests when `require_pr_approval=true`.
- **PoApprover** (table: `po_approvers`): Designate users who approve purchase orders when `require_po_approval=true`.
- **Company Flags**:
  - `require_pr_approval`: Block PR→PO conversion until approved.
  - `require_po_approval`: Block PO send until approved.
  - `auto_generate_do`: Automatically create DO when PO is sent.
  - `direct_supplier_order`: Allow suppliers to send DO without PO.
  - `show_price_on_do_grn`: Display unit costs on delivery order and GRN (for visibility/negotiation).
  - `ordering_mode`: `'outlet'` (each outlet orders independently) or `'cpu'` (CPU consolidates and distributes).
  - `price_alert_threshold`: Percentage change to trigger price alert when GRN cost differs from PO cost.

### Key Services & Files

- **[app/Services/PoSplitService.php](app/Services/PoSplitService.php)**: Group multi-supplier order lines, create separate PO per supplier.
- **[app/Services/PoEmailService.php](app/Services/PoEmailService.php)**: Send PO to supplier email (if configured).
- **[app/Services/PurchaseRequestService.php](app/Services/PurchaseRequestService.php)**: Generate PR numbers, consolidate approved PRs by supplier.
- **[app/Services/OrderAdjustmentService.php](app/Services/OrderAdjustmentService.php)**: Track qty/cost adjustments (received_quantity variance, price updates).
- **[app/Services/ProcurementInvoiceService.php](app/Services/ProcurementInvoiceService.php)**: Auto-create invoice from GRN, mark paid, cancel.
- **[app/Services/InvoiceMatchingService.php](app/Services/InvoiceMatchingService.php)**: Reconcile invoice lines vs. GRN lines (qty/cost mismatch detection).
- **[app/Services/RfqService.php](app/Services/RfqService.php)**: Request for quotation flow (get supplier quotes before ordering).
- **[app/Services/CreditNoteService.php](app/Services/CreditNoteService.php)**: Create and apply credit notes to supplier accounts.
- **[app/Services/SupplierNotificationService.php](app/Services/SupplierNotificationService.php)**: Notify suppliers of POs, delivery, invoice status changes.

### Key Livewire Components

- **[app/Livewire/Purchasing/PurchaseRequestForm.php](app/Livewire/Purchasing/PurchaseRequestForm.php)**: PR create/edit (lines, approval flow).
- **[app/Livewire/Purchasing/OrderForm.php](app/Livewire/Purchasing/OrderForm.php)**: PO create/edit (from template, from PR, manual, multi-supplier split).
- **[app/Livewire/Purchasing/ConvertToDoForm.php](app/Livewire/Purchasing/ConvertToDoForm.php)**: Create DO from PO, or manual DO entry.
- **[app/Livewire/Purchasing/GrnReceiveForm.php](app/Livewire/Purchasing/GrnReceiveForm.php)**: Receive goods, confirm qty/condition, trigger IngredientPriceHistory update.
- **[app/Livewire/Purchasing/ReceiveForm.php](app/Livewire/Purchasing/ReceiveForm.php)**: Alternative GRN receive flow (alternate UI).
- **[app/Livewire/Purchasing/InvoiceReceive.php](app/Livewire/Purchasing/InvoiceReceive.php)**: Capture supplier invoice, validate against GRN, record taxes.
- **[app/Livewire/Purchasing/ConsolidateForm.php](app/Livewire/Purchasing/ConsolidateForm.php)**: CPU flow: select approved PRs, consolidate by supplier, generate POs.
- **[app/Livewire/Purchasing/RfqForm.php](app/Livewire/Purchasing/RfqForm.php)**: Create quotation request, send to suppliers, compare quotes.

---

## 2. Ingredient Cost Chain & Price Watcher

**Narrative**: Purchase price flows from supplier invoice → GRN line → IngredientPriceHistory → Ingredient.current_cost. UOM conversion (base_uom → recipe_uom) applies yield percentage to compute effective cost per recipe unit. Price Watcher (ScanDocument → ReviewDocument) lets users upload supplier documents (PDF/image), AI-extracts items, matches to ingredients, and stages price updates for review.

| Step | What Happens | Writes To | Key File(s) |
|------|--------------|-----------|-----------|
| **Purchase Price** | On GRN receive, unit_cost from line updates Ingredient.current_cost if new supplier quote arrives. | Ingredient.current_cost, IngredientPriceHistory | `app/Livewire/Purchasing/GrnReceiveForm.php` (line 180+), `app/Models/Ingredient.php` |
| **UOM Conversion** | Ingredient has base_uom (purchase unit, e.g. kg) and recipe_uom (consumption unit, e.g. g). UomService converts cost: cost_per_recipe_uom = cost_per_base ÷ conversion_factor. Fallback: standard UOM ratio (kg→g = 1000). | — (computed on-demand) | `app/Services/UomService.php::convertCost()` |
| **Yield & Waste** | is_prep ingredients have prep_recipe_id (link to Recipe). Ingredient.yield_percent reduces effective quantity (e.g., 80% yield = 1kg → 0.8kg usable). RecipeLine.waste_percentage adds buffer (e.g., 5% waste increases cost). | Ingredient.yield_percent, RecipeLine.waste_percentage | `app/Models/Ingredient.php`, `app/Models/RecipeLine.php` |
| **Price Watcher: Upload** | User uploads supplier document (invoice PDF, quotation image, price list, delivery order) via camera or file picker. AI (OpenRouter) extracts supplier name, date, items (name, qty, price, UOM). Staged as ScannedDocument (status=pending). | ScannedDocument (status=pending) | `app/Livewire/Ingredients/ScanDocument.php` |
| **Price Watcher: Review** | User confirms detected supplier, effective date, matches extracted items to existing ingredients (or creates new). Marks items as: matched, new, or skipped. | ScannedDocument (status=importing/imported) | `app/Livewire/Ingredients/ReviewDocument.php` |
| **Price Watcher: Import** | Clicking "Import" updates supplier_ingredients.last_cost + updates Ingredient.current_cost. Creates IngredientPriceHistory row (ingredient_id, old_price, new_price, changed_by, changed_at). | IngredientPriceHistory, Ingredient.current_cost, supplier_ingredients.last_cost | `app/Livewire/Ingredients/ReviewDocument.php::import()` (line 280+) |
| **IngredientPriceHistory** | Audit trail of all cost changes. Populated on GRN receive, price watcher import, or manual price edit. Links to ingredient, captures old/new cost, change date, user. | IngredientPriceHistory | `app/Models/IngredientPriceHistory.php` |

### Key Files

- **[app/Models/Ingredient.php](app/Models/Ingredient.php)**: 
  - `current_cost`: Current purchase price per base_uom.
  - `yield_percent`: Usable percentage (affects effective cost).
  - `prep_recipe_id`: If is_prep=true, links to Recipe that produces this ingredient.
  - `recipeCost()`: Returns cost per recipe_uom (applies UOM conversion + yield).
  
- **[app/Services/UomService.php](app/Services/UomService.php)**:
  - `convertCost()`: Convert ingredient cost from base_uom to target_uom using ingredient-specific or standard conversions.
  - `convertQuantity()`: Convert qty between UOMs.

- **[app/Livewire/Ingredients/ScanDocument.php](app/Livewire/Ingredients/ScanDocument.php)**: 
  - Upload file, run AI extraction, store as ScannedDocument.
  
- **[app/Livewire/Ingredients/ReviewDocuments.php](app/Livewire/Ingredients/ReviewDocuments.php)**: 
  - List pending scanned documents.
  
- **[app/Livewire/Ingredients/ReviewDocument.php](app/Livewire/Ingredients/ReviewDocument.php)**: 
  - Match extracted items to ingredients, import prices, update current_cost.

- **[app/Models/IngredientPriceHistory.php](app/Models/IngredientPriceHistory.php)**: Audit of cost changes.

### Callers of IngredientPriceHistory::create

- `app/Livewire/Ingredients/ReviewDocument.php` (price watcher import)
- `app/Livewire/Purchasing/GrnReceiveForm.php` (GRN receive, new supplier quote)
- `app/Livewire/Purchasing/ReceiveForm.php` (alternate GRN flow)

---

## 3. Recipe Cost & Menu

**Narrative**: A Recipe composes multiple RecipeLines (each = ingredient + qty + UOM + waste%). Recipe total cost sums (ingredient.current_cost × qty × waste_factor + extra_costs + taxes). is_prep recipes auto-sync an Ingredient record (with prep_recipe_id link), so prep items can be consumed in other recipes. RecipePrice + RecipePriceClass enable tiered pricing (e.g., table: RM50, takeaway: RM48).

| Step | What Happens | Writes To | Key File(s) |
|------|--------------|-----------|-----------|
| **Recipe Creation** | Name, yield_quantity, yield_uom, category, is_active. If is_prep=true, auto-creates or syncs an Ingredient with prep_recipe_id link. | Recipe (is_prep=false or true) | `app/Models/Recipe.php`, `app/Livewire/Recipes/RecipeForm.php` |
| **RecipeLines** | Add ingredient lines: ingredient_id, quantity, uom_id, waste_percentage, sort_order, is_packaging (non-food items). | RecipeLine (qty, waste_percentage) | `app/Models/RecipeLine.php` |
| **Cost Calculation** | getTotalCostAttribute: sum of (line.cost_per_recipe_uom × line.quantity) + extra_costs + taxes. cost_per_recipe_uom = ingredient.current_cost (in recipe_uom) × (1 + waste_pct/100). | Recipe.cost_per_yield_unit (cached) | `app/Models/Recipe.php::getTotalCostAttribute()`, `app/Models/RecipeLine.php::getCostPerRecipeUomAttribute()` |
| **Prep Recipes** | If is_prep=true, a mirror Ingredient is created/synced (name, current_cost = recipe.cost_per_yield_unit, yield_percent=100). Ingredient.prep_recipe_id links back. When prep recipe cost updates, so does the ingredient cost. | Ingredient (prep_recipe_id) | `app/Models/Ingredient.php::prepRecipe()`, `app/Models/Recipe.php::ingredient()` |
| **Tiered Pricing** | RecipePrice rows store price per RecipePriceClass (table: RM50, delivery: RM52, etc.). Multiple prices per recipe for different contexts. | RecipePrice (recipe_id, price_class_id, selling_price) | `app/Models/RecipePrice.php`, `app/Models/RecipePriceClass.php` |

### Key Files

- **[app/Models/Recipe.php](app/Models/Recipe.php)**: 
  - `cost_per_yield_unit`: Decimal, computed from lines.
  - `is_prep`: If true, synced to Ingredient.
  - `getTotalCostAttribute()`: Sum of line costs + extras + taxes.
  - `lines()`: HasMany RecipeLines (ordered by sort_order).
  - `prices()`: HasMany RecipePrice (tiered selling prices).
  - `ingredient()`: HasOne synced Ingredient (if is_prep).

- **[app/Models/RecipeLine.php](app/Models/RecipeLine.php)**:
  - `cost_per_recipe_uom`: Ingredient cost converted to recipe_uom + waste factor.
  - `line_total_cost`: cost_per_recipe_uom × quantity.

- **[app/Models/RecipePrice.php](app/Models/RecipePrice.php)**: 
  - Links Recipe → PriceClass → selling_price.

- **[app/Models/RecipePriceClass.php](app/Models/RecipePriceClass.php)**: 
  - Enum-like class labels (table, delivery, takeaway, etc.).

---

## 4. Sales & P&L

**Narrative**: SalesRecord captures daily/period revenue by outlet. SalesRecordLine breaks down by sales_category and meal_period (breakfast, lunch, dinner). Z-report import (via VisionService OCR) auto-populates sales. CostSummaryService computes month P&L: Revenue (SalesRecordLine) − COGS (Opening + Purchases + Transfers In − Transfers Out − Closing) = Profit. Sources: StockTake (opening/closing), PurchaseRecord (purchases), OutletTransfer (moves), WastageRecord (shrink), StaffMealRecord (staff consumption).

| Step | What Happens | Writes To | Key File(s) |
|------|--------------|-----------|-----------|
| **Sales Entry** | Manual or Z-report import. Captures sale_date, meal_period (all_day/breakfast/lunch/tea_time/dinner/supper), total_revenue, pax (covers). | SalesRecord, SalesRecordLine (by sales_category) | `app/Models/SalesRecord.php`, `app/Livewire/Sales/Create.php` |
| **Z-Report Import** | Upload POS receipt image/PDF. VisionService extracts text, VisionService.parseZReport() parses departments & sessions. Auto-creates SalesRecord + lines by department. | SalesRecord (sale_date, total_revenue), SalesRecordLine (sales_category_id, amount) | `app/Livewire/Sales/ZReportImport.php`, `app/Services/VisionService.php::parseZReport()` |
| **Revenue by Category** | SalesRecordLine.sales_category_id groups revenue for P&L reporting. Department → SalesCategory mapping in CostSummaryService. | SalesRecordLine.sales_category_id, .amount | `app/Models/SalesRecordLine.php` |
| **COGS Formula** | Opening Stock (StockTake, prev month closing) + Purchases (PurchaseRecord) + Transfers In − Transfers Out − Closing Stock (StockTake, curr month closing) − Wastage − Staff Meals = COGS. | — (computed) | `app/Services/CostSummaryService.php::generate()` |
| **Purchase Costs** | Sum of PurchaseRecord.total_amount (grouped by department → sales_category). Includes tax + delivery. | — (queried from PurchaseRecord) | `app/Services/CostSummaryService.php::getPurchasesByDepartment()` |
| **Stock Values** | StockTake captures inventory at start (opening) and end (closing) of period. Lines: ingredient, qty_counted, unit_cost (at time of count). total_stock_cost computed. | StockTake.total_stock_cost, StockTakeLine.unit_cost | `app/Models/StockTake.php`, `app/Livewire/Inventory/StockTakeForm.php` |
| **Transfer Impact** | OutletTransfer lines deduct from source outlet, add to destination. Tracked in COGS by department. | OutletTransferLine.unit_cost (to compute transfer value) | `app/Services/CostSummaryService.php::getTransfersByDepartment()` |
| **Wastage & Staff Meals** | WastageRecord + StaffMealRecord subtract from COGS. Both track ingredient × qty × unit_cost. | WastageRecord, StaffMealRecord | `app/Models/WastageRecord.php`, `app/Models/StaffMealRecord.php` |
| **MTD Comparison** | Two modes: month-to-date (YM format, uses opening/closing stock) vs. custom range (weekly; skips stock takes). | — | `app/Services/CostSummaryService.php::generate(period, startDate, endDate)` |

### Key Files

- **[app/Models/SalesRecord.php](app/Models/SalesRecord.php)**: 
  - sale_date, total_revenue, pax, meal_period.
  - lines(): HasMany SalesRecordLine (breakdown by category).

- **[app/Models/SalesRecordLine.php](app/Models/SalesRecordLine.php)**: 
  - sales_category_id, amount, meal_period (inherit from parent).

- **[app/Services/CostSummaryService.php](app/Services/CostSummaryService.php)**: 
  - `generate()`: Month P&L with revenue, purchases, transfers, stock, wastage, staff meals.
  - Groups by department → sales_category.
  - Supports MTD (with opening/closing stock) or custom range (no stock).

- **[app/Services/VisionService.php](app/Services/VisionService.php)**: 
  - `parseZReport()`: OCR text → structured {date, departments[], sessions[], net_sales, total_bills}.

- **[app/Livewire/Sales/ZReportImport.php](app/Livewire/Sales/ZReportImport.php)**: 
  - Upload Z-report image, run VisionService, map departments to sales_categories, create SalesRecord + lines.

---

## 5. Inventory

**Narrative**: StockTake records actual inventory at a point in time, computing variance (system vs. actual) and total stock cost. Method: `detailed` (line-by-line per ingredient) or `summary` (aggregated per category). OutletTransfer moves stock between outlets (Draft → In Transit → Received or Cancelled state machine). Wastage and staff meals deduct from usable stock. StockTransferOrder (internal tool) may coexist with OutletTransfer (confirm if both used via grep).

| Step | What Happens | Writes To | Status Values | Key File(s) |
|------|--------------|-----------|---------------|-----------|
| **StockTake Create** | Select outlet, department, method (detailed/summary), count_date. If detailed: add lines (ingredient, qty_counted, unit_cost). If summary: add category aggregates. | StockTake (method, total_stock_cost), StockTakeLine (ingredient_id, qty_counted) | draft, submitted, approved, completed | `app/Livewire/Inventory/StockTakeForm.php` |
| **StockTake Variance** | total_variance_cost = sum of (expected_qty − counted_qty) × unit_cost. Variance flags discrepancies for investigation. | StockTake.total_variance_cost | — | `app/Models/StockTake.php` |
| **StockTake Cost** | total_stock_cost = sum of (qty_counted × unit_cost). Used as opening/closing stock value in COGS. | StockTake.total_stock_cost | — | `app/Livewire/Inventory/StockTakeForm.php` |
| **OutletTransfer Create** | Initiate transfer from outlet A → outlet B. Add lines (ingredient, qty, unit_cost). Saves as Draft. | OutletTransfer (status=draft), OutletTransferLine | draft, in_transit, received, cancelled | `app/Livewire/Inventory/OutletTransferForm.php` |
| **OutletTransfer Send** | User confirms and sends transfer. Status → in_transit. Receiving outlet notified. | OutletTransfer.status = in_transit | — | `app/Livewire/Inventory/OutletTransferForm.php` |
| **OutletTransfer Receive** | Receiving outlet confirms arrival, updates received_qty per line if different. Status → received. Completes transfer. | OutletTransferLine.received_qty, OutletTransfer.status = received | — | `app/Livewire/Inventory/OutletTransferForm.php` |
| **OutletTransfer Cancel** | Either outlet can cancel before received. Status → cancelled. | OutletTransfer.status = cancelled | — | — |
| **Wastage Record** | Log ingredient waste (spoilage, over-portioning, etc.). Ingredient, qty_wasted, reason. Deducts from COGS. | WastageRecord, WastageRecordLine | — | `app/Models/WastageRecord.php`, `app/Livewire/Inventory/WastageForm.php` |
| **Staff Meal Record** | Log staff meals consumed. Per employee, per meal, qty × unit_cost. Deducts from COGS, may be charged-back to staff. | StaffMealRecord, StaffMealRecordLine | — | `app/Models/StaffMealRecord.php`, `app/Livewire/Inventory/StaffMealForm.php` |

### StockTransferOrder vs. OutletTransfer

**Confirm in source**: `grep -r "StockTransferOrder" /c/WebDev/servora/app/Livewire --include="*.php" -l` to verify if both are actively used or if one is legacy.

### Key Files

- **[app/Models/StockTake.php](app/Models/StockTake.php)**: 
  - method: `detailed` or `summary`.
  - total_stock_cost, total_variance_cost.
  - lines(): HasMany StockTakeLine.

- **[app/Livewire/Inventory/StockTakeForm.php](app/Livewire/Inventory/StockTakeForm.php)**: 
  - Create/edit stock take, add lines, compute variance and totals.

- **[app/Models/OutletTransfer.php](app/Models/OutletTransfer.php)**: 
  - status: draft, in_transit, received, cancelled.
  - from_outlet_id, to_outlet_id.
  - lines(): HasMany OutletTransferLine.

- **[app/Models/WastageRecord.php](app/Models/WastageRecord.php)**:
  - Ingredient wastage (spoilage).

- **[app/Models/StaffMealRecord.php](app/Models/StaffMealRecord.php)**:
  - Staff meal consumption.

---

## 6. Kitchen / CPU (Central Production)

**Narrative**: ProductionOrder (from kitchen) or OutletPrepRequest (from outlet) initiates recipe production. Kitchen receives the order, produces recipes, updates KitchenInventory (consumption tracking). In CPU mode (Company.ordering_mode='cpu'), POs consolidate across outlets and distribute via CentralPurchasingUnit. Kitchen users switch workspace via OutletSwitcher (kitchen mode vs. outlet mode).

| Step | What Happens | Writes To | Status Values | Key File(s) |
|------|--------------|-----------|---------------|-----------|
| **OutletPrepRequest** | Outlet requests prep items (e.g., marinated chicken) from central kitchen. Specifies recipe, qty, needed_date. | OutletPrepRequest (status=draft), OutletPrepRequestLine | draft, submitted, approved, completed, cancelled | `app/Livewire/Kitchen/PrepRequestForm.php`, `app/Models/OutletPrepRequest.php` |
| **ProductionOrder Create** | Kitchen creates production order (manual or auto-converted from OutletPrepRequest). Specifies kitchen, production_date, needed_by_date, lines (recipe + qty + to_outlet). | ProductionOrder (status=draft), ProductionOrderLine | draft, approved, in_progress, completed, cancelled | `app/Livewire/Kitchen/ProductionOrderForm.php`, `app/Models/ProductionOrder.php` |
| **ProductionOrder Approve** | Kitchen manager approves order (if approval required). Status → approved. | ProductionOrder.approved_by, approved_at | approved | — |
| **ProductionOrder Execute** | Kitchen staff execute: start production (started_at), consume ingredients, batch cook recipes. Update KitchenInventory (deduct consumed, produce finished goods). | KitchenInventory (consumed_qty, produced_qty, unit_cost), ProductionLog | in_progress → completed | `app/Livewire/Kitchen/ProductionExecute.php`, `app/Models/ProductionLog.php` |
| **KitchenInventory Track** | Per-recipe tracking: ingredients consumed (reduce KI.consumed_qty), finished goods produced (increase KI.produced_qty). Used to compute production cost vs. budgeted. | KitchenInventory.consumed_qty, produced_qty, unit_cost | — | `app/Models/KitchenInventory.php` |
| **CPU Ordering** | Company.ordering_mode = 'cpu': outlets submit PRs to CPU, CPU consolidates by supplier, sends POs to suppliers, receives goods at CPU location, distributes to outlets via OutletTransfer. | PurchaseRequest.cpu_id, PurchaseOrder.cpu_id, OutletTransfer (from CPU outlet to destination outlets) | — | `app/Models/CentralPurchasingUnit.php`, `app/Livewire/Purchasing/ConsolidateForm.php` |
| **Kitchen User Role** | Users assigned to CentralKitchen (via kitchen_users pivot). Workspace mode: 'kitchen' (can only view/edit kitchen orders). Session.workspace_mode. | — | — | `app/Models/CentralKitchen.php`, `app/Models/User.php::workspaceMode()` |

### Key Files

- **[app/Models/ProductionOrder.php](app/Models/ProductionOrder.php)**: 
  - status: draft, approved, in_progress, completed, cancelled.
  - kitchen_id → CentralKitchen.
  - lines(): HasMany ProductionOrderLine (recipe + qty + to_outlet).
  - logs(): HasMany ProductionLog (audit trail).

- **[app/Models/OutletPrepRequest.php](app/Models/OutletPrepRequest.php)**: 
  - Outlet request for prep items.
  - status: draft, submitted, approved, completed, cancelled.

- **[app/Livewire/Kitchen/ProductionOrderForm.php](app/Livewire/Kitchen/ProductionOrderForm.php)**: 
  - Create/edit production order, add recipe lines.

- **[app/Livewire/Kitchen/ProductionExecute.php](app/Livewire/Kitchen/ProductionExecute.php)**: 
  - Execute production: confirm consumed qty, update KitchenInventory, log audit.

- **[app/Models/KitchenInventory.php](app/Models/KitchenInventory.php)**: 
  - Per-recipe production tracking.

- **[app/Models/CentralPurchasingUnit.php](app/Models/CentralPurchasingUnit.php)**: 
  - consolidation hub for PRs, POs, supplies.

- **[app/Models/User.php](app/Models/User.php)**: 
  - `workspace_mode()`: Session override or user default (outlet vs. kitchen).

---

## 7. SaaS Subscription, Onboarding, Usage, Referrals, Coupons

**Narrative**: New companies sign up → trial (Plan.trial_days) → Subscription (trialing/active/cancelled). Usage tracked per feature (UsageRecord). Onboarding Wizard guides setup (company details, first outlet, team invites, ingredients, recipes). Referrals track signups from unique codes (ReferralCode → Referral → Commission). Coupons grant trial extensions or plan upgrades. CHIP-IN payment gateway handles billing.

| Step | What Happens | Writes To | Status Values | Key File(s) |
|------|--------------|-----------|---------------|-----------|
| **Registration** | New company sign-up page (SaasRegister). Creates Company (trial_ends_at = now + Plan.trial_days), Subscription (trialing), first User (owner). | Company (trial_ends_at), Subscription (trialing), User | — | `app/Livewire/Auth/SaasRegister.php` |
| **Trial Creation** | SubscriptionService.createTrial(): sets trial_ends_at, creates Subscription.STATUS_TRIALING. | Subscription (trialing), Company.trial_ends_at | trialing | `app/Services/SubscriptionService.php::createTrial()` |
| **Onboarding Wizard** | Step 1: company details (phone, address, currency). Step 2: first outlet. Step 3: invite team. Records OnboardingStep (completed_at) for each. Company.onboarding_completed_at when all done. | Company (updated), Outlet, OnboardingStep (completed_at=now) | — | `app/Livewire/Onboarding/Wizard.php`, `app/Models/OnboardingStep.php` |
| **Usage Tracking** | UsageTrackingService logs feature usage per company (e.g., "recipe_created", "sales_record_count"). UsageRecord captures feature, value, date. | UsageRecord (feature_key, value, recorded_date) | — | `app/Services/UsageTrackingService.php`, `app/Models/UsageRecord.php` |
| **Referral Code Gen** | ReferralService.generateCode(User): creates ReferralCode (code, url, referrer_type=user, referrer_id). User shares code link /r/ABCD1234. | ReferralCode (code, url, is_active) | — | `app/Services/ReferralService.php::generateCode()`, `app/Http/Controllers/ReferralTrackingController.php` |
| **Referral Tracking** | Signup via referral link → ReferralTrackingController records ReferralCode click, creates Referral (referrer_id, referred_company_id, status=pending). On first paid invoice, status → completed + create Commission. | Referral (referred_company_id, status), Commission (referrer_id, amount) | pending, completed, cancelled | `app/Http/Controllers/ReferralTrackingController.php`, `app/Services/ReferralService.php::recordSignup()` |
| **Coupon Redemption** | CouponService.redeem(): validate coupon (active, not expired, not exhausted, not already used by company). Grant/extend Subscription period. Record CouponRedemption. | Subscription.current_period_end (extended), CouponRedemption (coupon_id, company_id) | — | `app/Services/CouponService.php::redeem()`, `app/Models/Coupon.php` |
| **Payment via CHIP-IN** | ChipInService.createPurchase(): generate payment request, create Payment (pending). CHIP-IN webhook callback on success → Payment.status=completed, Subscription.activate(). | Payment (status, amount), Subscription (active) | pending, completed, failed, cancelled | `app/Services/ChipInService.php::createPurchase()`, `app/Http/Controllers/Webhook/ChipInWebhookController.php`, `app/Services/InvoiceService.php` |
| **Subscription Lifecycle** | Subscription.renew(): extend period for next month/year. Subscription.cancel(): mark cancelled. Subscription.expire(): past due, mark expired. | Subscription.status, current_period_end | trialing → active, active → past_due, → expired, → cancelled | `app/Services/SubscriptionService.php` |

### Key Files

- **[app/Models/Plan.php](app/Models/Plan.php)**: 
  - price_monthly, price_yearly, trial_days, features (array).

- **[app/Models/Subscription.php](app/Models/Subscription.php)**: 
  - status: trialing, active, past_due, cancelled, expired.
  - trial_ends_at, current_period_start, current_period_end.
  - isActive(), isTrial(), isExpired(), isPastDue(), isCancelled().

- **[app/Services/SubscriptionService.php](app/Services/SubscriptionService.php)**: 
  - createTrial(), activate(), cancel(), renew(), expire(), changePlan(), canUseFeature().

- **[app/Livewire/Onboarding/Wizard.php](app/Livewire/Onboarding/Wizard.php)**: 
  - Multi-step onboarding flow.

- **[app/Models/OnboardingStep.php](app/Models/OnboardingStep.php)**: 
  - Tracks completion of setup steps.

- **[app/Services/ReferralService.php](app/Services/ReferralService.php)**: 
  - generateCode(), generateCodeForAffiliate(), trackClick(), recordSignup().

- **[app/Http/Controllers/ReferralTrackingController.php](app/Http/Controllers/ReferralTrackingController.php)**: 
  - Handles /r/{code} redirect + tracking.

- **[app/Models/Referral.php](app/Models/Referral.php)**: 
  - referrer_id, referred_company_id, status, commission_amount.

- **[app/Models/Commission.php](app/Models/Commission.php)**: 
  - Links referrer → referred company → amount earned.

- **[app/Services/CouponService.php](app/Services/CouponService.php)**: 
  - validate(), redeem().

- **[app/Models/Coupon.php](app/Models/Coupon.php)**: 
  - code, is_active, expires_at, redeemed_count, max_redeems, grant_type, grant_value.

- **[app/Services/ChipInService.php](app/Services/ChipInService.php)**: 
  - createPurchase(), handleCallback().

- **[app/Http/Controllers/Webhook/ChipInWebhookController.php](app/Http/Controllers/Webhook/ChipInWebhookController.php)**: 
  - Webhook endpoint for CHIP-IN payment callbacks.

- **[app/Services/UsageTrackingService.php](app/Services/UsageTrackingService.php)**: 
  - recordUsage(), getMonthlyUsage().

- **[app/Models/UsageRecord.php](app/Models/UsageRecord.php)**: 
  - Audit log of feature usage.

---

## 8. Tenancy, Outlet Switching, Workspace Modes

**Narrative**: All data is scoped by Company (CompanyScope global scope). Users can access multiple Outlets (via user_outlet pivot). Active outlet is tracked in session['active_outlet_id']. Workspace modes: 'outlet' (default, sales/inventory) vs. 'kitchen' (production). Middleware chain enforces company scope, onboarding, subscription, feature access, timezone.

| Step | What Happens | Session Key | Key Scope/Middleware | Key File(s) |
|------|--------------|-------------|---------------------|-----------|
| **CompanyScope** | Applied to all major models (Model::booted() → addGlobalScope). Filters queries to Auth::user()->company_id or app('currentCompany')->id. | — | Scope query: `where('company_id', $companyId)` | `app/Scopes/CompanyScope.php` |
| **Active Outlet** | User selects outlet → session['active_outlet_id'] = $outletId. Queries can call ScopesToActiveOutlet::scopeByOutlet() to auto-filter. | active_outlet_id | ScopesToActiveOutlet trait | `app/Livewire/OutletSwitcher.php`, `app/Traits/ScopesToActiveOutlet.php` |
| **Outlet Switcher UI** | Dropdown lists user's accessible outlets (or all outlets if user.canViewAllOutlets()). Excludes kitchen outlets (CentralKitchen.outlet_id). | active_outlet_id | OutletSwitcher component | `app/Livewire/OutletSwitcher.php` |
| **Workspace Mode** | User's default workspace_mode (outlet/kitchen). Can override via session. Kitchen users only see prep orders + production orders. | workspace_mode | User.workspaceMode() (session override) | `app/Models/User.php`, Middleware: `EnsureKitchenUser` |
| **Subdomain to Company** | Multi-tenant via subdomain (company.servora.app). ResolveCompanyFromSubdomain middleware extracts company from subdomain → app('currentCompany'). | currentCompany (app binding) | ResolveCompanyFromSubdomain | `app/Http/Middleware/ResolveCompanyFromSubdomain.php` |
| **Ensure Onboarding Complete** | Blocks access to operational pages until onboarding_completed_at is set. Redirects to /onboarding/wizard. | — | EnsureOnboardingComplete | `app/Http/Middleware/EnsureOnboardingComplete.php` |
| **Enforce Subscription** | Blocks access if subscription is cancelled or expired. Redirects to /billing. | — | EnforceSubscription | `app/Http/Middleware/EnforceSubscription.php` |
| **Feature Access** | Per-plan features (e.g., 'cpu_mode', 'kitchen_features'). CheckFeatureAccess blocks if subscription plan doesn't include feature. | — | CheckFeatureAccess | `app/Http/Middleware/CheckFeatureAccess.php` |
| **Display Timezone** | SetDisplayTimezone middleware sets view timezone to company.timezone for date/time display. | — | SetDisplayTimezone | `app/Http/Middleware/SetDisplayTimezone.php` |
| **Enforce Main Domain** | EnforceMainDomain redirects subdomains to main domain if not multi-tenant mode. | — | EnforceMainDomain | `app/Http/Middleware/EnforceMainDomain.php` |

### Key Files

- **[app/Scopes/CompanyScope.php](app/Scopes/CompanyScope.php)**: 
  - Global scope filtering queries to authenticated user's company_id or app('currentCompany').

- **[app/Traits/ScopesToActiveOutlet.php](app/Traits/ScopesToActiveOutlet.php)**: 
  - scopeByOutlet(): Filter query by active outlet (if set).

- **[app/Livewire/OutletSwitcher.php](app/Livewire/OutletSwitcher.php)**: 
  - Outlet dropdown; switchOutlet() updates session['active_outlet_id'].

- **[app/Http/Middleware/EnsureCompanyScope.php](app/Http/Middleware/EnsureCompanyScope.php)**: 
  - Ensures user is authenticated and company_id is set.

- **[app/Http/Middleware/EnsureOnboardingComplete.php](app/Http/Middleware/EnsureOnboardingComplete.php)**: 
  - Redirect to /onboarding/wizard if company.onboarding_completed_at is null.

- **[app/Http/Middleware/EnforceSubscription.php](app/Http/Middleware/EnforceSubscription.php)**: 
  - Redirect to /billing if subscription is not active (cancelled or expired).

- **[app/Http/Middleware/CheckFeatureAccess.php](app/Http/Middleware/CheckFeatureAccess.php)**: 
  - Verify subscription plan includes required feature.

- **[app/Http/Middleware/EnsureKitchenUser.php](app/Http/Middleware/EnsureKitchenUser.php)**: 
  - Kitchen-specific route protection.

- **[app/Http/Middleware/ResolveCompanyFromSubdomain.php](app/Http/Middleware/ResolveCompanyFromSubdomain.php)**: 
  - Multi-tenant: extract company from subdomain, bind to app('currentCompany').

- **[app/Http/Middleware/SetDisplayTimezone.php](app/Http/Middleware/SetDisplayTimezone.php)**: 
  - Set Laravel timezone to company.timezone for view rendering.

- **[app/Http/Middleware/EnforceMainDomain.php](app/Http/Middleware/EnforceMainDomain.php)**: 
  - Redirect subdomains to main domain (if single-tenant).

---

## 9. Supplier Portal & Affiliate Portal Auth

**Narrative**: SupplierUser (guard: 'supplier') logs into portal to view Orders, Invoices, Quotations scoped by supplier_id. AffiliateUser (guard: 'affiliate') manages referral dashboard, commissions, bank details. Both have separate authentication flows (password reset, email verification).

| Step | What Happens | Model | Guard | Scoping | Key File(s) |
|------|--------------|-------|-------|---------|-----------|
| **Supplier Registration** | Supplier admin creates SupplierUser accounts (email, password, role: admin/staff). Email verified before access. | SupplierUser | supplier | Where supplier_id = logged-in user's supplier_id | `app/Models/SupplierUser.php`, `app/Http/Controllers/Auth/SupplierRegister.php` |
| **Supplier Login** | Email + password via guard 'supplier'. Redirects to /supplier/dashboard. | SupplierUser | supplier | — | `app/Http/Controllers/Auth/SupplierLogin.php` |
| **Supplier Portal: Orders** | View POs, DOs scoped to own supplier_id. Filter by status (draft, sent, received). Download PO PDF. | PurchaseOrder (where supplier_id = auth('supplier')->user()->supplier_id) | supplier | supplier_id | `app/Livewire/SupplierPortal/Orders.php` |
| **Supplier Portal: Invoices** | View ProcurementInvoices scoped to own supplier_id. Download invoice PDF. | ProcurementInvoice (where supplier_id = ...) | supplier | supplier_id | `app/Livewire/SupplierPortal/Invoices.php` |
| **Supplier Portal: Quotations** | View and reply to QuotationRequest items (RFQs). | QuotationRequest, SupplierQuotation (where supplier_id = ...) | supplier | supplier_id | `app/Livewire/SupplierPortal/Quotations.php` |
| **Password Reset** | Supplier user requests reset link via email. Link validates token, allows new password. | SupplierUser | supplier | — | `app/Http/Controllers/Auth/SupplierForgotPassword.php` |
| **Affiliate Registration** | New affiliate signs up: email, password, bank details (name, account #, bank name). | Affiliate | affiliate | None (affiliates are not company-scoped; global entity) | `app/Http/Controllers/Auth/AffiliateRegister.php` |
| **Affiliate Login** | Email + password via guard 'affiliate'. Redirects to /affiliate/dashboard. | Affiliate | affiliate | — | `app/Http/Controllers/Auth/AffiliateLogin.php` |
| **Affiliate Dashboard** | View referral code, clicks, conversions, commissions (via Commission records). | ReferralCode, Referral, Commission (where referrer_id = auth('affiliate')->id()) | affiliate | referrer_id | `app/Livewire/Affiliate/Dashboard.php` |
| **Affiliate Bank Details** | Update bank info (bank_name, account_name, account_number) for commission payouts. | Affiliate (bank_* columns) | affiliate | — | `app/Livewire/Affiliate/BankDetails.php` |

### Key Files

- **[app/Models/SupplierUser.php](app/Models/SupplierUser.php)**: 
  - supplier_id, role (admin/staff), is_active, email_verified_at.
  - isAdmin(): role === 'admin'.

- **[app/Http/Controllers/Auth/SupplierRegister.php](app/Http/Controllers/Auth/SupplierRegister.php)**: 
  - Create new supplier user, email verification.

- **[app/Http/Controllers/Auth/SupplierLogin.php](app/Http/Controllers/Auth/SupplierLogin.php)**: 
  - Authenticate via guard 'supplier'.

- **[app/Models/Affiliate.php](app/Models/Affiliate.php)**: 
  - email, password, phone, bank_name, bank_account_name, bank_account_number, is_active.
  - referralCode(): Get active ReferralCode (referrer_type='affiliate').

- **[app/Http/Controllers/Auth/AffiliateRegister.php](app/Http/Controllers/Auth/AffiliateRegister.php)**: 
  - Create new affiliate, set bank details.

- **[app/Http/Controllers/Auth/AffiliateLogin.php](app/Http/Controllers/Auth/AffiliateLogin.php)**: 
  - Authenticate via guard 'affiliate'.

- **[app/Livewire/SupplierPortal/Orders.php](app/Livewire/SupplierPortal/Orders.php)**: 
  - Supplier views POs, DOs for their supplier_id.

- **[app/Livewire/SupplierPortal/Invoices.php](app/Livewire/SupplierPortal/Invoices.php)**: 
  - Supplier views procurement invoices.

- **[app/Livewire/SupplierPortal/Quotations.php](app/Livewire/SupplierPortal/Quotations.php)**: 
  - Supplier responds to RFQs.

- **[app/Livewire/Affiliate/Dashboard.php](app/Livewire/Affiliate/Dashboard.php)**: 
  - Affiliate views referral metrics, commissions, conversions.

- **[app/Livewire/Affiliate/BankDetails.php](app/Livewire/Affiliate/BankDetails.php)**: 
  - Affiliate updates payout bank account.

---

## Quick Reference: Status Constants

### PurchaseRequest
- draft, submitted, approved, rejected, converted, cancelled

### PurchaseOrder
- draft, sent, partial, received, cancelled

### DeliveryOrder
- pending, received, partial, rejected

### GoodsReceivedNote
- pending, partial, received, rejected

### GoodsReceivedNoteLine (condition)
- good, damaged, rejected

### ProcurementInvoice
- draft, issued, paid, cancelled, overdue

### CreditNote
- draft, issued, acknowledged, applied, cancelled

### StockTake
- draft, submitted, approved, completed

### OutletTransfer
- draft, in_transit, received, cancelled

### ProductionOrder
- draft, approved, in_progress, completed, cancelled

### OutletPrepRequest
- draft, submitted, approved, completed, cancelled

### Subscription
- trialing, active, past_due, cancelled, expired

### Payment
- pending, completed, failed, cancelled

---

*Generated as developer reference for Servora PMS. All paths are relative to project root.*
```

---

# Final Markdown Document

```markdown
# Servora Business Workflows

Developer reference guide tracing end-to-end flows through models, services, and Livewire components. Use relative paths like `app/Services/PoSplitService.php` to navigate implementations.

## 1. Procurement Lifecycle

**Narrative**: The procurement flow moves from requisition (PR) → authorization (PO approval) → delivery (DO) → goods receipt (GRN) → accounting (PurchaseRecord, ProcurementInvoice, CreditNote). At each step, Company-level toggles control branching: `require_pr_approval`, `require_po_approval`, `auto_generate_do`, `direct_supplier_order`, `ordering_mode`, `show_price_on_do_grn`, and `price_alert_threshold`. CPU (Central Purchasing Unit) mode handles multi-outlet consolidation.

| Step | What Happens | Writes To | Status Values | Key File(s) |
|------|--------------|-----------|---------------|-----------|
| **PR Draft** | User creates purchase request with lines (ingredient + qty + UOM). Requires outlet/department context. | PurchaseRequest (draft), PurchaseRequestLine | draft, submitted, approved, rejected, converted, cancelled | `app/Livewire/Purchasing/PurchaseRequestForm.php`, `app/Models/PurchaseRequest.php` |
| **PR Approval** | If `require_pr_approval=true` on Company, PrApprover role users must approve. User can also auto-approve own requests. | PurchaseRequest.approved_by, approved_at | submitted → approved | `app/Models/PrApprover.php` |
| **PR→PO Consolidation** | Approved PRs grouped by supplier (merge same-ingredient lines across outlets). Generates one PO per supplier. In CPU mode, routed via CentralPurchasingUnit. | PurchaseOrder, PurchaseOrderLine | draft (created) | `app/Services/PurchaseRequestService.php::consolidate()`, `app/Livewire/Purchasing/ConsolidateForm.php` |
| **PO Creation** | Manually create PO or from PR. Add lines (ingredient + qty + unit_cost). Split multi-supplier orders into separate POs. | PurchaseOrder (status=draft), PurchaseOrderLine | draft, sent, partial, received, cancelled | `app/Livewire/Purchasing/OrderForm.php`, `app/Services/PoSplitService.php::splitAndCreate()` |
| **PO Approval** | If `require_po_approval=true`, PoApprover role users approve. Email sent via PoEmailService. | PurchaseOrder.approved_by | sent (after approval) | `app/Models/PoApprover.php`, `app/Services/PoEmailService.php` |
| **DO Auto-Generate** | If `auto_generate_do=true`, pressing "Send" on PO auto-creates DeliveryOrder. Otherwise manual creation. DO inherits PO totals + tax. | DeliveryOrder, DeliveryOrderLine | pending → received | `app/Livewire/Purchasing/ConvertToDoForm.php` |
| **DO Manual Entry** | If `direct_supplier_order=true`, supplier sends DO directly (no PO prerequisite). Else linked to PO. Can have multiple DOs per PO (partial delivery mode). | DeliveryOrder (status=pending), DeliveryOrderLine | pending, received, partial, rejected | `app/Livewire/Purchasing/ConvertToDoForm.php` |
| **GRN Receive** | User confirms goods received, updates received_qty per line, selects condition (good/damaged/rejected). Generates GRN number. Price watcher updates current_cost if new price detected. | GoodsReceivedNote (status=pending → received), GoodsReceivedNoteLine (condition, received_qty), IngredientPriceHistory | pending, partial, received, rejected | `app/Livewire/Purchasing/GrnReceiveForm.php`, `app/Livewire/Purchasing/ReceiveForm.php` |
| **PurchaseRecord** | Non-PO direct purchases logged here (e.g., cash buys). Links to DeliveryOrder if available. Used in COGS. | PurchaseRecord, PurchaseRecordLine | — | `app/Models/PurchaseRecord.php`, `app/Models/PurchaseRecordLine.php` |
| **Invoice Issuance** | Auto-generated from GRN or manual entry. Captures supplier invoice number, due date, taxes. InvoiceMatchingService reconciles invoice qty vs. GRN qty. | ProcurementInvoice (status=issued), ProcurementInvoiceLine | draft, issued, paid, cancelled, overdue | `app/Services/ProcurementInvoiceService.php::createFromGrn()`, `app/Livewire/Purchasing/InvoiceReceive.php` |
| **Credit Note** | Supplier issue (qty adjustment, price variance, damage). Direction: supplier (inbound) or buyer (outbound). Auto-applied to invoice balance. | CreditNote (status=draft → issued → applied), CreditNoteLine | draft, issued, acknowledged, applied, cancelled | `app/Services/CreditNoteService.php`, `app/Livewire/Purchasing/CreditNoteForm.php` |

### Approval Roles & Company Settings

- **PrApprover** (table: `pr_approvers`): Designate users who approve purchase requests when `require_pr_approval=true`.
- **PoApprover** (table: `po_approvers`): Designate users who approve purchase orders when `require_po_approval=true`.
- **Company Flags**:
  - `require_pr_approval`: Block PR→PO conversion until approved.
  - `require_po_approval`: Block PO send until approved.
  - `auto_generate_do`: Automatically create DO when PO is sent.
  - `direct_supplier_order`: Allow suppliers to send DO without PO.
  - `show_price_on_do_grn`: Display unit costs on delivery order and GRN (for visibility/negotiation).
  - `ordering_mode`: `'outlet'` (each outlet orders independently) or `'cpu'` (CPU consolidates and distributes).
  - `price_alert_threshold`: Percentage change to trigger price alert when GRN cost differs from PO cost.

### Key Services & Files

- **[app/Services/PoSplitService.php](app/Services/PoSplitService.php)**: Group multi-supplier order lines, create separate PO per supplier.
- **[app/Services/PoEmailService.php](app/Services/PoEmailService.php)**: Send PO to supplier email (if configured).
- **[app/Services/PurchaseRequestService.php](app/Services/PurchaseRequestService.php)**: Generate PR numbers, consolidate approved PRs by supplier.
- **[app/Services/OrderAdjustmentService.php](app/Services/OrderAdjustmentService.php)**: Track qty/cost adjustments (received_quantity variance, price updates).
- **[app/Services/ProcurementInvoiceService.php](app/Services/ProcurementInvoiceService.php)**: Auto-create invoice from GRN, mark paid, cancel.
- **[app/Services/InvoiceMatchingService.php](app/Services/InvoiceMatchingService.php)**: Reconcile invoice lines vs. GRN lines (qty/cost mismatch detection).
- **[app/Services/RfqService.php](app/Services/RfqService.php)**: Request for quotation flow (get supplier quotes before ordering).
- **[app/Services/CreditNoteService.php](app/Services/CreditNoteService.php)**: Create and apply credit notes to supplier accounts.
- **[app/Services/SupplierNotificationService.php](app/Services/SupplierNotificationService.php)**: Notify suppliers of POs, delivery, invoice status changes.

### Key Livewire Components

- **[app/Livewire/Purchasing/PurchaseRequestForm.php](app/Livewire/Purchasing/PurchaseRequestForm.php)**: PR create/edit (lines, approval flow).
- **[app/Livewire/Purchasing/OrderForm.php](app/Livewire/Purchasing/OrderForm.php)**: PO create/edit (from template, from PR, manual, multi-supplier split).
- **[app/Livewire/Purchasing/ConvertToDoForm.php](app/Livewire/Purchasing/ConvertToDoForm.php)**: Create DO from PO, or manual DO entry.
- **[app/Livewire/Purchasing/GrnReceiveForm.php](app/Livewire/Purchasing/GrnReceiveForm.php)**: Receive goods, confirm qty/condition, trigger IngredientPriceHistory update.
- **[app/Livewire/Purchasing/ReceiveForm.php](app/Livewire/Purchasing/ReceiveForm.php)**: Alternative GRN receive flow (alternate UI).
- **[app/Livewire/Purchasing/InvoiceReceive.php](app/Livewire/Purchasing/InvoiceReceive.php)**: Capture supplier invoice, validate against GRN, record taxes.
- **[app/Livewire/Purchasing/ConsolidateForm.php](app/Livewire/Purchasing/ConsolidateForm.php)**: CPU flow: select approved PRs, consolidate by supplier, generate POs.
- **[app/Livewire/Purchasing/RfqForm.php](app/Livewire/Purchasing/RfqForm.php)**: Create quotation request, send to suppliers, compare quotes.

---

## 2. Ingredient Cost Chain & Price Watcher

**Narrative**: Purchase price flows from supplier invoice → GRN line → IngredientPriceHistory → Ingredient.current_cost. UOM conversion (base_uom → recipe_uom) applies yield percentage to compute effective cost per recipe unit. Price Watcher (ScanDocument → ReviewDocument) lets users upload supplier documents (PDF/image), AI-extracts items, matches to ingredients, and stages price updates for review.

| Step | What Happens | Writes To | Key File(s) |
|------|--------------|-----------|-----------|
| **Purchase Price** | On GRN receive, unit_cost from line updates Ingredient.current_cost if new supplier quote arrives. | Ingredient.current_cost, IngredientPriceHistory | `app/Livewire/Purchasing/GrnReceiveForm.php` (line 180+), `app/Models/Ingredient.php` |
| **UOM Conversion** | Ingredient has base_uom (purchase unit, e.g. kg) and recipe_uom (consumption unit, e.g. g). UomService converts cost: cost_per_recipe_uom = cost_per_base ÷ conversion_factor. Fallback: standard UOM ratio (kg→g = 1000). | — (computed on-demand) | `app/Services/UomService.php::convertCost()` |
| **Yield & Waste** | is_prep ingredients have prep_recipe_id (link to Recipe). Ingredient.yield_percent reduces effective quantity (e.g., 80% yield = 1kg → 0.8kg usable). RecipeLine.waste_percentage adds buffer (e.g., 5% waste increases cost). | Ingredient.yield_percent, RecipeLine.waste_percentage | `app/Models/Ingredient.php`, `app/Models/RecipeLine.php` |
| **Price Watcher: Upload** | User uploads supplier document (invoice PDF, quotation image, price list, delivery order) via camera or file picker. AI (OpenRouter) extracts supplier name, date, items (name, qty, price, UOM). Staged as ScannedDocument (status=pending). | ScannedDocument (status=pending) | `app/Livewire/Ingredients/ScanDocument.php` |
| **Price Watcher: Review** | User confirms detected supplier, effective date, matches extracted items to existing ingredients (or creates new). Marks items as: matched, new, or skipped. | ScannedDocument (status=importing/imported) | `app/Livewire/Ingredients/ReviewDocument.php` |
| **Price Watcher: Import** | Clicking "Import" updates supplier_ingredients.last_cost + updates Ingredient.current_cost. Creates IngredientPriceHistory row (ingredient_id, old_price, new_price, changed_by, changed_at). | IngredientPriceHistory, Ingredient.current_cost, supplier_ingredients.last_cost | `app/Livewire/Ingredients/ReviewDocument.php::import()` (line 280+) |
| **IngredientPriceHistory** | Audit trail of all cost changes. Populated on GRN receive, price watcher import, or manual price edit. Links to ingredient, captures old/new cost, change date, user. | IngredientPriceHistory | `app/Models/IngredientPriceHistory.php` |

### Key Files

- **[app/Models/Ingredient.php](app/Models/Ingredient.php)**: 
  - `current_cost`: Current purchase price per base_uom.
  - `yield_percent`: Usable percentage (affects effective cost).
  - `prep_recipe_id`: If is_prep=true, links to Recipe that produces this ingredient.
  - `recipeCost()`: Returns cost per recipe_uom (applies UOM conversion + yield).
  
- **[app/Services/UomService.php](app/Services/UomService.php)**:
  - `convertCost()`: Convert ingredient cost from base_uom to target_uom using ingredient-specific or standard conversions.
  - `convertQuantity()`: Convert qty between UOMs.

- **[app/Livewire/Ingredients/ScanDocument.php](app/Livewire/Ingredients/ScanDocument.php)**: 
  - Upload file, run AI extraction, store as ScannedDocument.
  
- **[app/Livewire/Ingredients/ReviewDocuments.php](app/Livewire/Ingredients/ReviewDocuments.php)**: 
  - List pending scanned documents.
  
- **[app/Livewire/Ingredients/ReviewDocument.php](app/Livewire/Ingredients/ReviewDocument.php)**: 
  - Match extracted items to ingredients, import prices, update current_cost.

- **[app/Models/IngredientPriceHistory.php](app/Models/IngredientPriceHistory.php)**: Audit of cost changes.

### Callers of IngredientPriceHistory::create

- `app/Livewire/Ingredients/ReviewDocument.php` (price watcher import)
- `app/Livewire/Purchasing/GrnReceiveForm.php` (GRN receive, new supplier quote)
- `app/Livewire/Purchasing/ReceiveForm.php` (alternate GRN flow)

---

## 3. Recipe Cost & Menu

**Narrative**: A Recipe composes multiple RecipeLines (each = ingredient + qty + UOM + waste%). Recipe total cost sums (ingredient.current_cost × qty × waste_factor + extra_costs + taxes). is_prep recipes auto-sync an Ingredient record (with prep_recipe_id link), so prep items can be consumed in other recipes. RecipePrice + RecipePriceClass enable tiered pricing (e.g., table: RM50, takeaway: RM48).

| Step | What Happens | Writes To | Key File(s) |
|------|--------------|-----------|-----------|
| **Recipe Creation** | Name, yield_quantity, yield_uom, category, is_active. If is_prep=true, auto-creates or syncs an Ingredient with prep_recipe_id link. | Recipe (is_prep=false or true) | `app/Models/Recipe.php`, `app/Livewire/Recipes/RecipeForm.php` |
| **RecipeLines** | Add ingredient lines: ingredient_id, quantity, uom_id, waste_percentage, sort_order, is_packaging (non-food items). | RecipeLine (qty, waste_percentage) | `app/Models/RecipeLine.php` |
| **Cost Calculation** | getTotalCostAttribute: sum of (line.cost_per_recipe_uom × line.quantity) + extra_costs + taxes. cost_per_recipe_uom = ingredient.current_cost (in recipe_uom) × (1 + waste_pct/100). | Recipe.cost_per_yield_unit (cached) | `app/Models/Recipe.php::getTotalCostAttribute()`, `app/Models/RecipeLine.php::getCostPerRecipeUomAttribute()` |
| **Prep Recipes** | If is_prep=true, a mirror Ingredient is created/synced (name, current_cost = recipe.cost_per_yield_unit, yield_percent=100). Ingredient.prep_recipe_id links back. When prep recipe cost updates, so does the ingredient cost. | Ingredient (prep_recipe_id) | `app/Models/Ingredient.php::prepRecipe()`, `app/Models/Recipe.php::ingredient()` |
| **Tiered Pricing** | RecipePrice rows store price per RecipePriceClass (table: RM50, delivery: RM52, etc.). Multiple prices per recipe for different contexts. | RecipePrice (recipe_id, price_class_id, selling_price) | `app/Models/RecipePrice.php`, `app/Models/RecipePriceClass.php` |

### Key Files

- **[app/Models/Recipe.php](app/Models/Recipe.php)**: 
  - `cost_per_yield_unit`: Decimal, computed from lines.
  - `is_prep`: If true, synced to Ingredient.
  - `getTotalCostAttribute()`: Sum of line costs + extras + taxes.
  - `lines()`: HasMany RecipeLines (ordered by sort_order).
  - `prices()`: HasMany RecipePrice (tiered selling prices).
  - `ingredient()`: HasOne synced Ingredient (if is_prep).

- **[app/Models/RecipeLine.php](app/Models/RecipeLine.php)**:
  - `cost_per_recipe_uom`: Ingredient cost converted to recipe_uom + waste factor.
  - `line_total_cost`: cost_per_recipe_uom × quantity.

- **[app/Models/RecipePrice.php](app/Models/RecipePrice.php)**: 
  - Links Recipe → PriceClass → selling_price.

- **[app/Models/RecipePriceClass.php](app/Models/RecipePriceClass.php)**: 
  - Enum-like class labels (table, delivery, takeaway, etc.).

---

## 4. Sales & P&L

**Narrative**: SalesRecord captures daily/period revenue by outlet. SalesRecordLine breaks down by sales_category and meal_period (breakfast, lunch, dinner). Z-report import (via VisionService OCR) auto-populates sales. CostSummaryService computes month P&L: Revenue (SalesRecordLine) − COGS (Opening + Purchases + Transfers In − Transfers Out − Closing) = Profit. Sources: StockTake (opening/closing), PurchaseRecord (purchases), OutletTransfer (moves), WastageRecord (shrink), StaffMealRecord (staff consumption).

| Step | What Happens | Writes To | Key File(s) |
|------|--------------|-----------|-----------|
| **Sales Entry** | Manual or Z-report import. Captures sale_date, meal_period (all_day/breakfast/lunch/tea_time/dinner/supper), total_revenue, pax (covers). | SalesRecord, SalesRecordLine (by sales_category) | `app/Models/SalesRecord.php`, `app/Livewire/Sales/Create.php` |
| **Z-Report Import** | Upload POS receipt image/PDF. VisionService extracts text, VisionService.parseZReport() parses departments & sessions. Auto-creates SalesRecord + lines by department. | SalesRecord (sale_date, total_revenue), SalesRecordLine (sales_category_id, amount) | `app/Livewire/Sales/ZReportImport.php`, `app/Services/VisionService.php::parseZReport()` |
| **Revenue by Category** | SalesRecordLine.sales_category_id groups revenue for P&L reporting. Department → SalesCategory mapping in CostSummaryService. | SalesRecordLine.sales_category_id, .amount | `app/Models/SalesRecordLine.php` |
| **COGS Formula** | Opening Stock (StockTake, prev month closing) + Purchases (PurchaseRecord) + Transfers In − Transfers Out − Closing Stock (StockTake, curr month closing) − Wastage − Staff Meals = COGS. | — (computed) | `app/Services/CostSummaryService.php::generate()` |
| **Purchase Costs** | Sum of PurchaseRecord.total_amount (grouped by department → sales_category). Includes tax + delivery. | — (queried from PurchaseRecord) | `app/Services/CostSummaryService.php::getPurchasesByDepartment()` |
| **Stock Values** | StockTake captures inventory at start (opening) and end (closing) of period. Lines: ingredient, qty_counted, unit_cost (at time of count). total_stock_cost computed. | StockTake.total_stock_cost, StockTakeLine.unit_cost | `app/Models/StockTake.php`, `app/Livewire/Inventory/StockTakeForm.php` |
| **Transfer Impact** | OutletTransfer lines deduct from source outlet, add to destination. Tracked in COGS by department. | OutletTransferLine.unit_cost (to compute transfer value) | `app/Services/CostSummaryService.php::getTransfersByDepartment()` |
| **Wastage & Staff Meals** | WastageRecord + StaffMealRecord subtract from COGS. Both track ingredient × qty × unit_cost. | WastageRecord, StaffMealRecord | `app/Models/WastageRecord.php`, `app/Models/StaffMealRecord.php` |
| **MTD Comparison** | Two modes: month-to-date (YM format, uses opening/closing stock) vs. custom range (weekly; skips stock takes). | — | `app/Services/CostSummaryService.php::generate(period, startDate, endDate)` |

### Key Files

- **[app/Models/SalesRecord.php](app/Models/SalesRecord.php)**: 
  - sale_date, total_revenue, pax, meal_period.
  - lines(): HasMany SalesRecordLine (breakdown by category).

- **[app/Models/SalesRecordLine.php](app/Models/SalesRecordLine.php)**: 
  - sales_category_id, amount, meal_period (inherit from parent).

- **[app/Services/CostSummaryService.php](app/Services/CostSummaryService.php)**: 
  - `generate()`: Month P&L with revenue, purchases, transfers, stock, wastage, staff meals.
  - Groups by department → sales_category.
  - Supports MTD (with opening/closing stock) or custom range (no stock).

- **[app/Services/VisionService.php](app/Services/VisionService.php)**: 
  - `parseZReport()`: OCR text → structured {date, departments[], sessions[], net_sales, total_bills}.

- **[app/Livewire/Sales/ZReportImport.php](app/Livewire/Sales/ZReportImport.php)**: 
  - Upload Z-report image, run VisionService, map departments to sales_categories, create SalesRecord + lines.

---

## 5. Inventory

**Narrative**: StockTake records actual inventory at a point in time, computing variance (system vs. actual) and total stock cost. Method: `detailed` (line-by-line per ingredient) or `summary` (aggregated per category). OutletTransfer moves stock between outlets (Draft → In Transit → Received or Cancelled state machine). Wastage and staff meals deduct from usable stock. StockTransferOrder (internal tool) may coexist with OutletTransfer (confirm if both used via grep).

| Step | What Happens | Writes To | Status Values | Key File(s) |
|------|--------------|-----------|---------------|-----------|
| **StockTake Create** | Select outlet, department, method (detailed/summary), count_date. If detailed: add lines (ingredient, qty_counted, unit_cost). If summary: add category aggregates. | StockTake (method, total_stock_cost), StockTakeLine (ingredient_id, qty_counted) | draft, submitted, approved, completed | `app/Livewire/Inventory/StockTakeForm.php` |
| **StockTake Variance** | total_variance_cost = sum of (expected_qty − counted_qty) × unit_cost. Variance flags discrepancies for investigation. | StockTake.total_variance_cost | — | `app/Models/StockTake.php` |
| **StockTake Cost** | total_stock_cost = sum of (qty_counted × unit_cost). Used as opening/closing stock value in COGS. | StockTake.total_stock_cost | — | `app/Livewire/Inventory/StockTakeForm.php` |
| **OutletTransfer Create** | Initiate transfer from outlet A → outlet B. Add lines (ingredient, qty, unit_cost). Saves as Draft. | OutletTransfer (status=draft), OutletTransferLine | draft, in_transit, received, cancelled | `app/Livewire/Inventory/OutletTransferForm.php` |
| **OutletTransfer Send** | User confirms and sends transfer. Status → in_transit. Receiving outlet notified. | OutletTransfer.status = in_transit | — | `app/Livewire/Inventory/OutletTransferForm.php` |
| **OutletTransfer Receive** | Receiving outlet confirms arrival, updates received_qty per line if different. Status → received. Completes transfer. | OutletTransferLine.received_qty, OutletTransfer.status = received | — | `app/Livewire/Inventory/OutletTransferForm.php` |
| **OutletTransfer Cancel** | Either outlet can cancel before received. Status → cancelled. | OutletTransfer.status = cancelled | — | — |
| **Wastage Record** | Log ingredient waste (spoilage, over-portioning, etc.). Ingredient, qty_wasted, reason. Deducts from COGS. | WastageRecord, WastageRecordLine | — | `app/Models/WastageRecord.php`, `app/Livewire/Inventory/WastageForm.php` |
| **Staff Meal Record** | Log staff meals consumed. Per employee, per meal, qty × unit_cost. Deducts from COGS, may be charged-back to staff. | StaffMealRecord, StaffMealRecordLine | — | `app/Models/StaffMealRecord.php`, `app/Livewire/Inventory/StaffMealForm.php` |

### StockTransferOrder vs. OutletTransfer

**Confirm in source**: `grep -r "StockTransferOrder" /c/WebDev/servora/app/Livewire --include="*.php" -l` to verify if both are actively used or if one is legacy.

### Key Files

- **[app/Models/StockTake.php](app/Models/StockTake.php)**: 
  - method: `detailed` or `summary`.
  - total_stock_cost, total_variance_cost.
  - lines(): HasMany StockTakeLine.

- **[app/Livewire/Inventory/StockTakeForm.php](app/Livewire/Inventory/StockTakeForm.php)**: 
  - Create/edit stock take, add lines, compute variance and totals.

- **[app/Models/OutletTransfer.php](app/Models/OutletTransfer.php)**: 
  - status: draft, in_transit, received, cancelled.
  - from_outlet_id, to_outlet_id.
  - lines(): HasMany OutletTransferLine.

- **[app/Models/WastageRecord.php](app/Models/WastageRecord.php)**:
  - Ingredient wastage (spoilage).

- **[app/Models/StaffMealRecord.php](app/Models/StaffMealRecord.php)**:
  - Staff meal consumption.

---

## 6. Kitchen / CPU (Central Production)

**Narrative**: ProductionOrder (from kitchen) or OutletPrepRequest (from outlet) initiates recipe production. Kitchen receives the order, produces recipes, updates KitchenInventory (consumption tracking). In CPU mode (Company.ordering_mode='cpu'), POs consolidate across outlets and distribute via CentralPurchasingUnit. Kitchen users switch workspace via OutletSwitcher (kitchen mode vs. outlet mode).

| Step | What Happens | Writes To | Status Values | Key File(s) |
|------|--------------|-----------|---------------|-----------|
| **OutletPrepRequest** | Outlet requests prep items (e.g., marinated chicken) from central kitchen. Specifies recipe, qty, needed_date. | OutletPrepRequest (status=draft), OutletPrepRequestLine | draft, submitted, approved, completed, cancelled | `app/Livewire/Kitchen/PrepRequestForm.php`, `app/Models/OutletPrepRequest.php` |
| **ProductionOrder Create** | Kitchen creates production order (manual or auto-converted from OutletPrepRequest). Specifies kitchen, production_date, needed_by_date, lines (recipe + qty + to_outlet). | ProductionOrder (status=draft), ProductionOrderLine | draft, approved, in_progress, completed, cancelled | `app/Livewire/Kitchen/ProductionOrderForm.php`, `app/Models/ProductionOrder.php` |
| **ProductionOrder Approve** | Kitchen manager approves order (if approval required). Status → approved. | ProductionOrder.approved_by, approved_at | approved | — |
| **ProductionOrder Execute** | Kitchen staff execute: start production (started_at), consume ingredients, batch cook recipes. Update KitchenInventory (deduct consumed, produce finished goods). | KitchenInventory (consumed_qty, produced_qty, unit_cost), ProductionLog | in_progress → completed | `app/Livewire/Kitchen/ProductionExecute.php`, `app/Models/ProductionLog.php` |
| **KitchenInventory Track** | Per-recipe tracking: ingredients consumed (reduce KI.consumed_qty), finished goods produced (increase KI.produced_qty). Used to compute production cost vs. budgeted. | KitchenInventory.consumed_qty, produced_qty, unit_cost | — | `app/Models/KitchenInventory.php` |
| **CPU Ordering** | Company.ordering_mode = 'cpu': outlets submit PRs to CPU, CPU consolidates by supplier, sends POs to suppliers, receives goods at CPU location, distributes to outlets via OutletTransfer. | PurchaseRequest.cpu_id, PurchaseOrder.cpu_id, OutletTransfer (from CPU outlet to destination outlets) | — | `app/Models/CentralPurchasingUnit.php`, `app/Livewire/Purchasing/ConsolidateForm.php` |
| **Kitchen User Role** | Users assigned to CentralKitchen (via kitchen_users pivot). Workspace mode: 'kitchen' (can only view/edit kitchen orders). Session.workspace_mode. | — | — | `app/Models/CentralKitchen.php`, `app/Models/User.php::workspaceMode()` |

### Key Files

- **[app/Models/ProductionOrder.php](app/Models/ProductionOrder.php)**: 
  - status: draft, approved, in_progress, completed, cancelled.
  - kitchen_id → CentralKitchen.
  - lines(): HasMany ProductionOrderLine (recipe + qty + to_outlet).
  - logs(): HasMany ProductionLog (audit trail).

- **[app/Models/OutletPrepRequest.php](app/Models/OutletPrepRequest.php)**: 
  - Outlet request for prep items.
  - status: draft, submitted, approved, completed, cancelled.

- **[app/Livewire/Kitchen/ProductionOrderForm.php](app/Livewire/Kitchen/ProductionOrderForm.php)**: 
  - Create/edit production order, add recipe lines.

- **[app/Livewire/Kitchen/ProductionExecute.php](app/Livewire/Kitchen/ProductionExecute.php)**: 
  - Execute production: confirm consumed qty, update KitchenInventory, log audit.

- **[app/Models/KitchenInventory.php](app/Models/KitchenInventory.php)**: 
  - Per-recipe production tracking.

- **[app/Models/CentralPurchasingUnit.php](app/Models/CentralPurchasingUnit.php)**: 
  - consolidation hub for PRs, POs, supplies.

- **[app/Models/User.php](app/Models/User.php)**: 
  - `workspace_mode()`: Session override or user default (outlet vs. kitchen).

---

## 7. SaaS Subscription, Onboarding, Usage, Referrals, Coupons

**Narrative**: New companies sign up → trial (Plan.trial_days) → Subscription (trialing/active/cancelled). Usage tracked per feature (UsageRecord). Onboarding Wizard guides setup (company details, first outlet, team invites, ingredients, recipes). Referrals track signups from unique codes (ReferralCode → Referral → Commission). Coupons grant trial extensions or plan upgrades. CHIP-IN payment gateway handles billing.

| Step | What Happens | Writes To | Status Values | Key File(s) |
|------|--------------|-----------|---------------|-----------|
| **Registration** | New company sign-up page (SaasRegister). Creates Company (trial_ends_at = now + Plan.trial_days), Subscription (trialing), first User (owner). | Company (trial_ends_at), Subscription (trialing), User | — | `app/Livewire/Auth/SaasRegister.php` |
| **Trial Creation** | SubscriptionService.createTrial(): sets trial_ends_at, creates Subscription.STATUS_TRIALING. | Subscription (trialing), Company.trial_ends_at | trialing | `app/Services/SubscriptionService.php::createTrial()` |
| **Onboarding Wizard** | Step 1: company details (phone, address, currency). Step 2: first outlet. Step 3: invite team. Records OnboardingStep (completed_at) for each. Company.onboarding_completed_at when all done. | Company (updated), Outlet, OnboardingStep (completed_at=now) | — | `app/Livewire/Onboarding/Wizard.php`, `app/Models/OnboardingStep.php` |
| **Usage Tracking** | UsageTrackingService logs feature usage per company (e.g., "recipe_created", "sales_record_count"). UsageRecord captures feature, value, date. | UsageRecord (feature_key, value, recorded_date) | — | `app/Services/UsageTrackingService.php`, `app/Models/UsageRecord.php` |
| **Referral Code Gen** | ReferralService.generateCode(User): creates ReferralCode (code, url, referrer_type=user, referrer_id). User shares code link /r/ABCD1234. | ReferralCode (code, url, is_active) | — | `app/Services/ReferralService.php::generateCode()`, `app/Http/Controllers/ReferralTrackingController.php` |
| **Referral Tracking** | Signup via referral link → ReferralTrackingController records ReferralCode click, creates Referral (referrer_id, referred_company_id, status=pending). On first paid invoice, status → completed + create Commission. | Referral (referred_company_id, status), Commission (referrer_id, amount) | pending, completed, cancelled | `app/Http/Controllers/ReferralTrackingController.php`, `app/Services/ReferralService.php::recordSignup()` |
| **Coupon Redemption** | CouponService.redeem(): validate coupon (active, not expired, not exhausted, not already used by company). Grant/extend Subscription period. Record CouponRedemption. | Subscription.current_period_end (extended), CouponRedemption (coupon_id, company_id) | — | `app/Services/CouponService.php::redeem()`, `app/Models/Coupon.php` |
| **Payment via CHIP-IN** | ChipInService.createPurchase(): generate payment request, create Payment (pending). CHIP-IN webhook callback on success → Payment.status=completed, Subscription.activate(). | Payment (status, amount), Subscription (active) | pending, completed, failed, cancelled | `app/Services/ChipInService.php::createPurchase()`, `app/Http/Controllers/Webhook/ChipInWebhookController.php`, `app/Services/InvoiceService.php` |
| **Subscription Lifecycle** | Subscription.renew(): extend period for next month/year. Subscription.cancel(): mark cancelled. Subscription.expire(): past due, mark expired. | Subscription.status, current_period_end | trialing → active, active → past_due, → expired, → cancelled | `app/Services/SubscriptionService.php` |

### Key Files

- **[app/Models/Plan.php](app/Models/Plan.php)**: 
  - price_monthly, price_yearly, trial_days, features (array).

- **[app/Models/Subscription.php](app/Models/Subscription.php)**: 
  - status: trialing, active, past_due, cancelled, expired.
  - trial_ends_at, current_period_start, current_period_end.
  - isActive(), isTrial(), isExpired(), isPastDue(), isCancelled().

- **[app/Services/SubscriptionService.php](app/Services/SubscriptionService.php)**: 
  - createTrial(), activate(), cancel(), renew(), expire(), changePlan(), canUseFeature().

- **[app/Livewire/Onboarding/Wizard.php](app/Livewire/Onboarding/Wizard.php)**: 
  - Multi-step onboarding flow.

- **[app/Models/OnboardingStep.php](app/Models/OnboardingStep.php)**: 
  - Tracks completion of setup steps.

- **[app/Services/ReferralService.php](app/Services/ReferralService.php)**: 
  - generateCode(), generateCodeForAffiliate(), trackClick(), recordSignup().

- **[app/Http/Controllers/ReferralTrackingController.php](app/Http/Controllers/ReferralTrackingController.php)**: 
  - Handles /r/{code} redirect + tracking.

- **[app/Models/Referral.php](app/Models/Referral.php)**: 
  - referrer_id, referred_company_id, status, commission_amount.

- **[app/Models/Commission.php](app/Models/Commission.php)**: 
  - Links referrer → referred company → amount earned.

- **[app/Services/CouponService.php](app/Services/CouponService.php)**: 
  - validate(), redeem().

- **[app/Models/Coupon.php](app/Models/Coupon.php)**: 
  - code, is_active, expires_at, redeemed_count, max_redeems, grant_type, grant_value.

- **[app/Services/ChipInService.php](app/Services/ChipInService.php)**: 
  - createPurchase(), handleCallback().

- **[app/Http/Controllers/Webhook/ChipInWebhookController.php](app/Http/Controllers/Webhook/ChipInWebhookController.php)**: 
  - Webhook endpoint for CHIP-IN payment callbacks.

- **[app/Services/UsageTrackingService.php](app/Services/UsageTrackingService.php)**: 
  - recordUsage(), getMonthlyUsage().

- **[app/Models/UsageRecord.php](app/Models/UsageRecord.php)**: 
  - Audit log of feature usage.

---

## 8. Tenancy, Outlet Switching, Workspace Modes

**Narrative**: All data is scoped by Company (CompanyScope global scope). Users can access multiple Outlets (via user_outlet pivot). Active outlet is tracked in session['active_outlet_id']. Workspace modes: 'outlet' (default, sales/inventory) vs. 'kitchen' (production). Middleware chain enforces company scope, onboarding, subscription, feature access, timezone.

| Step | What Happens | Session Key | Key Scope/Middleware | Key File(s) |
|------|--------------|-------------|---------------------|-----------|
| **CompanyScope** | Applied to all major models (Model::booted() → addGlobalScope). Filters queries to Auth::user()->company_id or app('currentCompany')->id. | — | Scope query: `where('company_id', $companyId)` | `app/Scopes/CompanyScope.php` |
| **Active Outlet** | User selects outlet → session['active_outlet_id'] = $outletId. Queries can call ScopesToActiveOutlet::scopeByOutlet() to auto-filter. | active_outlet_id | ScopesToActiveOutlet trait | `app/Livewire/OutletSwitcher.php`, `app/Traits/ScopesToActiveOutlet.php` |
| **Outlet Switcher UI** | Dropdown lists user's accessible outlets (or all outlets if user.canViewAllOutlets()). Excludes kitchen outlets (CentralKitchen.outlet_id). | active_outlet_id | OutletSwitcher component | `app/Livewire/OutletSwitcher.php` |
| **Workspace Mode** | User's default workspace_mode (outlet/kitchen). Can override via session. Kitchen users only see prep orders + production orders. | workspace_mode | User.workspaceMode() (session override) | `app/Models/User.php`, Middleware: `EnsureKitchenUser` |
| **Subdomain to Company** | Multi-tenant via subdomain (company.servora.app). ResolveCompanyFromSubdomain middleware extracts company from subdomain → app('currentCompany'). | currentCompany (app binding) | ResolveCompanyFromSubdomain | `app/Http/Middleware/ResolveCompanyFromSubdomain.php` |
| **Ensure Onboarding Complete** | Blocks access to operational pages until onboarding_completed_at is set. Redirects to /onboarding/wizard. | — | EnsureOnboardingComplete | `app/Http/Middleware/EnsureOnboardingComplete.php` |
| **Enforce Subscription** | Blocks access if subscription is cancelled or expired. Redirects to /billing. | — | EnforceSubscription | `app/Http/Middleware/EnforceSubscription.php` |
| **Feature Access** | Per-plan features (e.g., 'cpu_mode', 'kitchen_features'). CheckFeatureAccess blocks if subscription plan doesn't include feature. | — | CheckFeatureAccess | `app/Http/Middleware/CheckFeatureAccess.php` |
| **Display Timezone** | SetDisplayTimezone middleware sets view timezone to company.timezone for date/time display. | — | SetDisplayTimezone | `app/Http/Middleware/SetDisplayTimezone.php` |
| **Enforce Main Domain** | EnforceMainDomain redirects subdomains to main domain if not multi-tenant mode. | — | EnforceMainDomain | `app/Http/Middleware/EnforceMainDomain.php` |

### Key Files

- **[app/Scopes/CompanyScope.php](app/Scopes/CompanyScope.php)**: 
  - Global scope filtering queries to authenticated user's company_id or app('currentCompany').

- **[app/Traits/ScopesToActiveOutlet.php](app/Traits/ScopesToActiveOutlet.php)**: 
  - scopeByOutlet(): Filter query by active outlet (if set).

- **[app/Livewire/OutletSwitcher.php](app/Livewire/OutletSwitcher.php)**: 
  - Outlet dropdown; switchOutlet() updates session['active_outlet_id'].

- **[app/Http/Middleware/EnsureCompanyScope.php](app/Http/Middleware/EnsureCompanyScope.php)**: 
  - Ensures user is authenticated and company_id is set.

- **[app/Http/Middleware/EnsureOnboardingComplete.php](app/Http/Middleware/EnsureOnboardingComplete.php)**: 
  - Redirect to /onboarding/wizard if company.onboarding_completed_at is null.

- **[app/Http/Middleware/EnforceSubscription.php](app/Http/Middleware/EnforceSubscription.php)**: 
  - Redirect to /billing if subscription is not active (cancelled or expired).

- **[app/Http/Middleware/CheckFeatureAccess.php](app/Http/Middleware/CheckFeatureAccess.php)**: 
  - Verify subscription plan includes required feature.

- **[app/Http/Middleware/EnsureKitchenUser.php](app/Http/Middleware/EnsureKitchenUser.php)**: 
  - Kitchen-specific route protection.

- **[app/Http/Middleware/ResolveCompanyFromSubdomain.php](app/Http/Middleware/ResolveCompanyFromSubdomain.php)**: 
  - Multi-tenant: extract company from subdomain, bind to app('currentCompany').

- **[app/Http/Middleware/SetDisplayTimezone.php](app/Http/Middleware/SetDisplayTimezone.php)**: 
  - Set Laravel timezone to company.timezone for view rendering.

- **[app/Http/Middleware/EnforceMainDomain.php](app/Http/Middleware/EnforceMainDomain.php)**: 
  - Redirect subdomains to main domain (if single-tenant).

---

## 9. Supplier Portal & Affiliate Portal Auth

**Narrative**: SupplierUser (guard: 'supplier') logs into portal to view Orders, Invoices, Quotations scoped by supplier_id. AffiliateUser (guard: 'affiliate') manages referral dashboard, commissions, bank details. Both have separate authentication flows (password reset, email verification).

| Step | What Happens | Model | Guard | Scoping | Key File(s) |
|------|--------------|-------|-------|---------|-----------|
| **Supplier Registration** | Supplier admin creates SupplierUser accounts (email, password, role: admin/staff). Email verified before access. | SupplierUser | supplier | Where supplier_id = logged-in user's supplier_id | `app/Models/SupplierUser.php`, `app/Http/Controllers/Auth/SupplierRegister.php` |
| **Supplier Login** | Email + password via guard 'supplier'. Redirects to /supplier/dashboard. | SupplierUser | supplier | — | `app/Http/Controllers/Auth/SupplierLogin.php` |
| **Supplier Portal: Orders** | View POs, DOs scoped to own supplier_id. Filter by status (draft, sent, received). Download PO PDF. | PurchaseOrder (where supplier_id = auth('supplier')->user()->supplier_id) | supplier | supplier_id | `app/Livewire/SupplierPortal/Orders.php` |
| **Supplier Portal: Invoices** | View ProcurementInvoices scoped to own supplier_id. Download invoice PDF. | ProcurementInvoice (where supplier_id = ...) | supplier | supplier_id | `app/Livewire/SupplierPortal/Invoices.php` |
| **Supplier Portal: Quotations** | View and reply to QuotationRequest items (RFQs). | QuotationRequest, SupplierQuotation (where supplier_id = ...) | supplier | supplier_id | `app/Livewire/SupplierPortal/Quotations.php` |
| **Password Reset** | Supplier user requests reset link via email. Link validates token, allows new password. | SupplierUser | supplier | — | `app/Http/Controllers/Auth/SupplierForgotPassword.php` |
| **Affiliate Registration** | New affiliate signs up: email, password, bank details (name, account #, bank name). | Affiliate | affiliate | None (affiliates are not company-scoped; global entity) | `app/Http/Controllers/Auth/AffiliateRegister.php` |
| **Affiliate Login** | Email + password via guard 'affiliate'. Redirects to /affiliate/dashboard. | Affiliate | affiliate | — | `app/Http/Controllers/Auth/AffiliateLogin.php` |
| **Affiliate Dashboard** | View referral code, clicks, conversions, commissions (via Commission records). | ReferralCode, Referral, Commission (where referrer_id = auth('affiliate')->id()) | affiliate | referrer_id | `app/Livewire/Affiliate/Dashboard.php` |
| **Affiliate Bank Details** | Update bank info (bank_name, account_name, account_number) for commission payouts. | Affiliate (bank_* columns) | affiliate | — | `app/Livewire/Affiliate/BankDetails.php` |

### Key Files

- **[app/Models/SupplierUser.php](app/Models/SupplierUser.php)**: 
  - supplier_id, role (admin/staff), is_active, email_verified_at.
  - isAdmin(): role === 'admin'.

- **[app/Http/Controllers/Auth/SupplierRegister.php](app/Http/Controllers/Auth/SupplierRegister.php)**: 
  - Create new supplier user, email verification.

- **[app/Http/Controllers/Auth/SupplierLogin.php](app/Http/Controllers/Auth/SupplierLogin.php)**: 
  - Authenticate via guard 'supplier'.

- **[app/Models/Affiliate.php](app/Models/Affiliate.php)**: 
  - email, password, phone, bank_name, bank_account_name, bank_account_number, is_active.
  - referralCode(): Get active ReferralCode (referrer_type='affiliate').

- **[app/Http/Controllers/Auth/AffiliateRegister.php](app/Http/Controllers/Auth/AffiliateRegister.php)**: 
  - Create new affiliate, set bank details.

- **[app/Http/Controllers/Auth/AffiliateLogin.php](app/Http/Controllers/Auth/AffiliateLogin.php)**: 
  - Authenticate via guard 'affiliate'.

- **[app/Livewire/SupplierPortal/Orders.php](app/Livewire/SupplierPortal/Orders.php)**: 
  - Supplier views POs, DOs for their supplier_id.

- **[app/Livewire/SupplierPortal/Invoices.php](app/Livewire/SupplierPortal/Invoices.php)**: 
  - Supplier views procurement invoices.

- **[app/Livewire/SupplierPortal/Quotations.php](app/Livewire/SupplierPortal/Quotations.php)**: 
  - Supplier responds to RFQs.

- **[app/Livewire/Affiliate/Dashboard.php](app/Livewire/Affiliate/Dashboard.php)**: 
  - Affiliate views referral metrics, commissions, conversions.

- **[app/Livewire/Affiliate/BankDetails.php](app/Livewire/Affiliate/BankDetails.php)**: 
  - Affiliate updates payout bank account.

---

## Quick Reference: Status Constants

### PurchaseRequest
- draft, submitted, approved, rejected, converted, cancelled

### PurchaseOrder
- draft, sent, partial, received, cancelled

### DeliveryOrder
- pending, received, partial, rejected

### GoodsReceivedNote
- pending, partial, received, rejected

### GoodsReceivedNoteLine (condition)
- good, damaged, rejected

### ProcurementInvoice
- draft, issued, paid, cancelled, overdue

### CreditNote
- draft, issued, acknowledged, applied, cancelled

### StockTake
- draft, submitted, approved, completed

### OutletTransfer
- draft, in_transit, received, cancelled

### ProductionOrder
- draft, approved, in_progress, completed, cancelled

### OutletPrepRequest
- draft, submitted, approved, completed, cancelled

### Subscription
- trialing, active, past_due, cancelled, expired

### Payment
- pending, completed, failed, cancelled

---

*Generated as developer reference for Servora PMS. All paths are relative to project root.*
