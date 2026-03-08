# F&B OS — Product Planning Document

## Vision

A back-office operating system for restaurant and F&B operators that replaces spreadsheets with a unified platform covering costing, purchasing, inventory, and financial visibility — across multiple outlets, companies, and users.

---

## 1. Architecture Overview

### Tech Stack

| Layer | Choice | Rationale |
|---|---|---|
| Backend | Laravel 11 (PHP 8.3) | Proven for SaaS, excellent ORM, built-in auth, queues, scheduling |
| Frontend | Blade + Livewire 3 + Alpine.js + Tailwind | Reactive UI without SPA complexity, stays in Laravel ecosystem |
| Database | MySQL 8 | Battle-tested for multi-tenant transactional data |
| Auth | Laravel Breeze / Jetstream + Spatie Permission | Multi-user, role-based, team/org scoping out of the box |
| File Storage | Laravel Filesystem (S3 / local) | PO attachments, DO signatures, receipts |
| Queue / Jobs | Laravel Queue (Redis or DB driver) | Background cost recalculations, report generation |
| Hosting | VPS (Forge / Ploi) or shared Laravel hosting | Full control, easy MySQL provisioning |
| Key Packages | `spatie/laravel-permission`, `maatwebsite/excel`, `barryvdh/laravel-dompdf`, `livewire/livewire` | Roles, Excel import/export, PDF generation |

### Multi-Tenancy Model

```
Company (tenant)
  └── Outlet[]
        └── Users[] (with roles scoped per outlet or company-wide)
```

**Strategy: Shared database, row-level isolation via `company_id`.**

Every core table carries `company_id` as a mandatory foreign key. A global Laravel middleware (`EnsureCompanyScope`) injects the tenant context from the authenticated session and applies automatic Eloquent global scopes. This is simpler than schema-per-tenant and sufficient for hundreds of companies.

---

## 2. User Roles & Permissions

| Role | Scope | Capabilities |
|---|---|---|
| **Super Admin** | Company | Full access to all outlets, settings, users |
| **Outlet Manager** | Outlet | Manage recipes, purchasing, stock, wastage for their outlet |
| **Kitchen Staff** | Outlet | View recipes, record wastage |
| **Purchaser** | Company / Outlet | Create POs, record purchases, manage suppliers |
| **Viewer / Accountant** | Company | Read-only dashboards, cost summaries, exports |

Permissions are managed via `spatie/laravel-permission` with role + scope pairs (e.g., `purchaser @ outlet_3`), checked via middleware on every request.

---

## 3. Core Data Model

### 3.1 Organization Layer

```
Company
  id, name, currency, tax_settings, logo, created_at

Outlet
  id, company_id, name, address, type (restaurant/bar/cafe/central-kitchen)

User
  id, email, name, password_hash

UserRole
  id, user_id, company_id, outlet_id (nullable = company-wide), role
```

### 3.2 Units of Measure (UOM) System

The UOM system supports a **primary (purchase) unit** and one or more **secondary (recipe/usage) units** per ingredient, with defined conversion factors.

```
UnitOfMeasure
  id, company_id
  name                     -- e.g. "kilogram", "piece", "litre"
  abbreviation             -- e.g. "kg", "pcs", "L"
  type                     -- WEIGHT | VOLUME | COUNT | LENGTH | PACK
  is_system                -- true for built-in units, false for custom
```

**System-seeded UOMs:**

| Type | Units |
|---|---|
| WEIGHT | kg, g, mg, lb, oz |
| VOLUME | L, ml, gal, fl oz |
| COUNT | pcs, dozen, pack, box, carton, tray |
| LENGTH | m, cm |

### 3.3 Master Ingredient Cost List

The foundation of the entire costing engine. Each ingredient has a **purchase UOM** (how you buy it) and a **recipe UOM** (how you use it), linked by a conversion factor.

```
Ingredient
  id, company_id
  name                     -- e.g. "Prawn (Medium)"
  category                 -- FOOD | BEVERAGE | CONSUMABLE | MERCHANDISE
  purchase_uom_id          -- FK → UnitOfMeasure, e.g. "kg"
  current_cost_per_purchase_uom   -- e.g. RM 32.00 / kg
  manual_cost_override     -- optional: operator can pin a cost
  is_active
  created_at, updated_at

IngredientUomConversion
  id, ingredient_id
  from_uom_id              -- typically the purchase UOM
  to_uom_id                -- the recipe/secondary UOM
  conversion_factor        -- e.g. 1 kg = 30 pcs → factor = 30
  is_default_recipe_uom    -- flags which secondary UOM to pre-select in recipes
  notes                    -- e.g. "Based on medium-size prawns ~33g each"

IngredientPriceHistory
  id, ingredient_id
  supplier_id
  cost_per_unit
  uom_id                   -- FK → UnitOfMeasure
  effective_date
  source                   -- PURCHASE | MANUAL | IMPORT
```

**Example — Prawns:**

| Field | Value |
|---|---|
| Ingredient | Prawn (Medium) |
| Purchase UOM | kg |
| Cost per Purchase UOM | RM 32.00 / kg |
| Recipe UOM | pcs |
| Conversion | 1 kg = 30 pcs |
| **Derived cost per pcs** | **RM 32.00 ÷ 30 = RM 1.067 / pcs** |

**Example — Cooking Oil:**

| Field | Value |
|---|---|
| Ingredient | Cooking Oil |
| Purchase UOM | carton (6 × 1L bottles) |
| Cost per Purchase UOM | RM 48.00 / carton |
| Recipe UOM | ml |
| Conversion | 1 carton = 6000 ml |
| **Derived cost per ml** | **RM 48.00 ÷ 6000 = RM 0.008 / ml** |

**Key behaviours:**
- `current_cost_per_purchase_uom` is recalculated every time a new Purchase Record is saved (weighted average or latest price — configurable per company).
- The cost in **any** UOM is derived at query time: `cost_in_recipe_uom = cost_per_purchase_uom ÷ conversion_factor`.
- An ingredient can have **multiple secondary UOMs** (e.g., prawns: kg → pcs, kg → g) but one is flagged as `is_default_recipe_uom`.
- Price history is immutable — never deleted, only appended.
- Recipe costs cascade-update whenever ingredient costs change (via Laravel Job dispatched on `IngredientPriceUpdated` event).
- Conversion factors are per-ingredient, not global — because "1 kg = ? pcs" depends on the specific item.

### 3.4 Recipe Costing

```
Recipe
  id, company_id
  name                     -- e.g. "Nasi Lemak Set A"
  category                 -- FOOD | BEVERAGE
  selling_price
  target_food_cost_pct     -- e.g. 30%
  yield_qty                -- e.g. 1 portion
  yield_unit
  total_cost               -- auto-summed from lines
  cost_pct                 -- (total_cost / selling_price) × 100
  is_active
  notes

RecipeLine
  id, recipe_id
  ingredient_id
  quantity                 -- e.g. 5 (pcs of prawn)
  uom_id                   -- FK → UnitOfMeasure (recipe UOM, e.g. "pcs")
  cost_per_unit            -- derived: purchase cost ÷ conversion factor
  line_cost                -- quantity × cost_per_unit

SubRecipe (self-referencing)
  id, parent_recipe_id, child_recipe_id, quantity, unit
```

**UOM in action — Nasi Lemak example:**

| Ingredient | Qty | Recipe UOM | Cost/Unit | Line Cost |
|---|---|---|---|---|
| Prawn (Medium) | 5 | pcs | RM 1.067 | RM 5.33 |
| Rice | 200 | g | RM 0.003 | RM 0.60 |
| Sambal (sub-recipe) | 50 | ml | RM 0.02 | RM 1.00 |
| **Total** | | | | **RM 6.93** |

Selling price: RM 18.90 → Cost %: 36.7% → ⚠️ Over 30% target

**Key behaviour:**
- When adding an ingredient to a recipe, the UI pre-selects the `is_default_recipe_uom` and auto-fills the derived cost per recipe unit.
- The operator can switch between available UOMs for that ingredient (e.g., use prawns in grams instead of pieces).
- Sub-recipes allow a "Sambal" recipe to be embedded in "Nasi Lemak" with proper cost roll-up.
- A "Recalculate All" button refreshes every recipe's cost from the master ingredient list.
- Dashboard flags any recipe where `cost_pct > target_food_cost_pct`.

### 3.4 Supplier Management

```
Supplier
  id, company_id
  name, contact_person, phone, email
  payment_terms            -- e.g. "COD", "Net 14", "Net 30"
  bank_details
  is_active
  notes

SupplierIngredient
  id, supplier_id, ingredient_id
  unit_price, unit, min_order_qty
  lead_time_days
```

This links suppliers to ingredients with per-supplier pricing, enabling price comparison during PO creation.

### 3.5 Purchase Order (PO)

```
PurchaseOrder
  id, company_id, outlet_id
  supplier_id
  po_number                -- auto-generated: PO-2026-0001
  status                   -- DRAFT | SENT | PARTIALLY_RECEIVED | RECEIVED | CANCELLED
  order_date
  expected_delivery_date
  notes
  created_by (user_id)

PurchaseOrderLine
  id, po_id
  ingredient_id
  ordered_qty, uom_id      -- FK → UnitOfMeasure (purchase UOM)
  unit_price
  line_total
```

### 3.6 Delivery Order (DO)

Receiving goods against a PO (or standalone).

```
DeliveryOrder
  id, company_id, outlet_id
  po_id (nullable — can receive without a PO)
  supplier_id
  do_number
  delivery_date
  received_by (user_id)
  notes, attachment_url

DeliveryOrderLine
  id, do_id
  po_line_id (nullable)
  ingredient_id
  received_qty, uom_id     -- FK → UnitOfMeasure
  unit_price               -- actual price paid (may differ from PO)
  accepted_qty             -- after inspection
  rejected_qty
  reject_reason
```

**Key behaviour:**
- Receiving a DO auto-updates `Ingredient.current_cost_per_unit` and creates a `PurchaseRecord`.
- Partial delivery updates the PO status to `PARTIALLY_RECEIVED`.

### 3.7 Purchase Record

The financial ledger of every purchase made.

```
PurchaseRecord
  id, company_id, outlet_id
  supplier_id
  do_id (nullable)
  invoice_number
  purchase_date
  payment_status           -- UNPAID | PARTIAL | PAID
  payment_due_date
  total_amount
  category                 -- FOOD | BEVERAGE | CONSUMABLE | MERCHANDISE | OTHER
  notes, attachment_url

PurchaseRecordLine
  id, purchase_record_id
  ingredient_id
  qty, uom_id, unit_price, line_total
```

### 3.8 Sales Record

```
SalesRecord
  id, company_id, outlet_id
  record_date
  source                   -- POS_IMPORT | MANUAL
  total_revenue
  total_covers (pax)
  notes

SalesRecordLine
  id, sales_record_id
  recipe_id (nullable)
  item_name
  category                 -- FOOD | BEVERAGE | MERCHANDISE | OTHER
  qty_sold
  unit_price
  line_total
```

**Key behaviour:**
- Ideally integrates with Zest POS or other POS via API/CSV import.
- Used to calculate actual food cost % = total purchases / total revenue.

### 3.9 Wastage Record

```
WastageRecord
  id, company_id, outlet_id
  record_date
  recorded_by (user_id)
  reason                   -- EXPIRED | SPOILED | OVERPRODUCTION | ACCIDENT | OTHER
  notes

WastageRecordLine
  id, wastage_record_id
  ingredient_id
  recipe_id (nullable)     -- if wasting a prepared item
  qty, uom_id              -- FK → UnitOfMeasure
  cost_per_unit
  line_cost
```

### 3.10 Inter-Outlet Transfer

```
OutletTransfer
  id, company_id
  from_outlet_id
  to_outlet_id
  transfer_number          -- TRF-2026-0001
  transfer_date
  status                   -- DRAFT | SENT | RECEIVED | DISPUTED
  created_by, received_by
  notes

OutletTransferLine
  id, transfer_id
  ingredient_id
  qty, uom_id              -- FK → UnitOfMeasure
  cost_per_unit            -- at time of transfer
  line_cost
```

**Key behaviour:**
- Transfer-Out decrements stock at source outlet.
- Transfer-In increments stock at destination outlet.
- Both sides must acknowledge — `SENT` → `RECEIVED` flow prevents discrepancies.

### 3.11 Monthly Stock Take (Closing Inventory)

```
StockTake
  id, company_id, outlet_id
  period                   -- "2026-03"
  status                   -- IN_PROGRESS | COMPLETED | LOCKED
  started_at, completed_at
  conducted_by (user_id)
  notes

StockTakeLine
  id, stock_take_id
  ingredient_id
  expected_qty             -- system-calculated
  actual_qty               -- physically counted
  uom_id                   -- FK → UnitOfMeasure (always in purchase UOM for consistency)
  cost_per_unit
  variance_qty             -- actual - expected
  variance_cost            -- variance × cost
```

**System-calculated expected quantity formula:**

```
Expected Closing Stock =
    Previous Month Closing Stock
  + Purchases (from Purchase Records)
  + Transfer-In
  − Sales Usage (from Sales × Recipes)
  − Wastage
  − Transfer-Out
```

This is the core inventory equation. Variance analysis highlights shrinkage, theft, or recording errors.

---

## 4. Cost Summary Engine

The crown jewel — a monthly P&L-style cost summary.

### Data Flow

```
┌─────────────┐     ┌──────────────┐     ┌──────────────┐
│  Purchases   │     │    Sales     │     │   Wastage    │
│  Records     │     │   Records    │     │   Records    │
└──────┬───────┘     └──────┬───────┘     └──────┬───────┘
       │                    │                    │
       ▼                    ▼                    ▼
┌──────────────────────────────────────────────────────────┐
│              MONTHLY COST SUMMARY ENGINE                  │
│                                                          │
│  Opening Stock (prev month closing)                      │
│  + Purchases                                             │
│  + Transfer In                                           │
│  − Transfer Out                                          │
│  − Closing Stock (from stock take)                       │
│  ─────────────────────────────                           │
│  = COST OF GOODS CONSUMED                                │
│                                                          │
│  Broken down by: FOOD | BEVERAGE | CONSUMABLE | MERCH    │
│  Compared against: Revenue per category                  │
│  Result: Actual Cost % per category                      │
└──────────────────────────────────────────────────────────┘
```

### Summary Table Structure

| Metric | Food | Beverage | Consumable | Merch | Total |
|---|---|---|---|---|---|
| Revenue | | | | | |
| Opening Stock | | | | | |
| (+) Purchases | | | | | |
| (+) Transfer In | | | | | |
| (−) Transfer Out | | | | | |
| (−) Closing Stock | | | | | |
| **= COGS** | | | | | |
| **Cost %** | | | | | |
| Wastage | | | | | |
| Variance (Shrinkage) | | | | | |

---

## 5. Key Screens / UI Map

```
F&B OS
├── Dashboard
│   ├── Cost % gauges (Food / Bev / Consumable)
│   ├── Revenue vs COGS trend (12-month chart)
│   ├── Alerts (recipes over target, POs pending, stock take due)
│   └── Quick actions
│
├── Ingredients
│   ├── Master List (search, filter by category)
│   ├── Ingredient Detail (price history, linked suppliers, linked recipes)
│   ├── UOM Conversions (purchase UOM → recipe UOM per ingredient)
│   └── Import / Export CSV
│
├── Recipes
│   ├── Recipe List (with live cost % badges)
│   ├── Recipe Builder (drag ingredients, sub-recipes, auto-calc)
│   └── Recipe Costing Report
│
├── Purchasing
│   ├── Purchase Orders (create, send, track status)
│   ├── Delivery Orders (receive against PO)
│   ├── Purchase Records (all purchases, filterable)
│   └── Supplier Management
│
├── Sales
│   ├── Daily Sales Entry (manual or CSV import)
│   └── Sales Reports
│
├── Inventory
│   ├── Stock Take (monthly count sheets)
│   ├── Wastage Log
│   ├── Inter-Outlet Transfers
│   └── Stock Movement Report (per ingredient)
│
├── Reports
│   ├── Monthly Cost Summary (the P&L view)
│   ├── Purchase Analysis (by supplier, by ingredient, by period)
│   ├── Wastage Analysis
│   ├── Price Variance Report
│   └── Export to Excel
│
└── Settings
    ├── Company Profile
    ├── Outlets
    ├── Users & Roles
    ├── Units of Measure (manage custom UOMs)
    ├── Cost Calculation Method (weighted avg / latest price)
    ├── Numbering Series (PO, DO, TRF prefixes)
    └── Categories & Tags
```

---

## 6. MVP Scope (Phase 1)

Ship the smallest thing that replaces spreadsheets:

| Include | Exclude (Phase 2+) |
|---|---|
| Single-company, multi-outlet | Multi-company |
| Master Ingredients + manual cost entry | Auto-cost from PO/DO flow |
| Recipe Costing | Sub-recipe nesting |
| Purchase Records (manual entry) | Full PO → DO → Purchase flow |
| Sales Records (manual entry) | POS integration |
| Monthly Stock Take | Auto-calculated expected stock |
| Basic Cost Summary | Full P&L with variance analysis |
| Wastage Records | Wastage analytics |
| Supplier list (basic) | Supplier price comparison |
| User roles (Admin + Staff) | Granular permissions |

### MVP Timeline Estimate

| Phase | Duration | Focus |
|---|---|---|
| **Foundation** | 2 weeks | Laravel scaffold, auth, multi-outlet, migrations, Livewire base UI shell |
| **Ingredients + Recipes** | 2 weeks | Master list, recipe builder, cost calculation |
| **Purchasing + Sales** | 2 weeks | Purchase records, sales records, supplier list |
| **Inventory** | 2 weeks | Stock take, wastage, transfers |
| **Cost Summary + Polish** | 2 weeks | Monthly report, dashboard, CSV exports |
| **Testing + Launch** | 2 weeks | Beta with 3–5 real operators |
| **Total MVP** | **~12 weeks** | |

---

## 7. Integration Opportunities

| System | Integration | Value |
|---|---|---|
| **Zest POS Lite** | Auto-sync daily sales data | Eliminates manual sales entry |
| **Zest Loyalty** | Link menu items to recipes | Cost-aware promotions |
| **WASAP CRM** | PO notifications to suppliers via WhatsApp | Frictionless ordering |
| **Accounting (Xero/SQL)** | Export purchase summaries | Close the books faster |

---

## 8. Monetisation Model

| Plan | Target | Price (est.) |
|---|---|---|
| **Starter** | 1 outlet, 2 users, basic features | RM 99/mo |
| **Growth** | Up to 5 outlets, full features, 10 users | RM 299/mo |
| **Enterprise** | Unlimited outlets, multi-company, API access | RM 599/mo |

Upsell lever: existing Zest Loyalty merchants already trust the ecosystem — bundle pricing drives adoption.

---

## 9. Technical Notes

### Numbering System
All document numbers follow the pattern: `{PREFIX}-{OUTLET_CODE}-{YYYY}-{SEQ}` — e.g., `PO-KL01-2026-0042`. Sequence resets annually per outlet.

### Audit Trail
Every create/update/delete on financial records writes to an `audit_logs` table with `user_id`, `action`, `auditable_type`, `auditable_id`, `old_values` (JSON), `new_values` (JSON), `timestamp`. Implemented via Laravel model observers or the `owen-it/laravel-auditing` package. This is non-negotiable for F&B operators who need accountability.

### Unit Conversion Engine
UOM conversion is **per-ingredient** via the `IngredientUomConversion` table (see section 3.3). The system also maintains a `UnitOfMeasure` master table with system-seeded units. Within the same unit type (e.g., WEIGHT), standard conversions are global (1 kg = 1000 g). Cross-type conversions (e.g., kg → pcs) are ingredient-specific and must be defined by the operator.

Laravel helper example:

```php
// App\Services\UomService
public function convertCost(Ingredient $ingredient, UnitOfMeasure $targetUom): float
{
    if ($ingredient->purchase_uom_id === $targetUom->id) {
        return $ingredient->current_cost_per_purchase_uom;
    }

    $conversion = $ingredient->uomConversions()
        ->where('to_uom_id', $targetUom->id)
        ->firstOrFail();

    return $ingredient->current_cost_per_purchase_uom / $conversion->conversion_factor;
}
```

### Soft Deletes
All master data (ingredients, recipes, suppliers) uses Laravel's `SoftDeletes` trait (`deleted_at` timestamp) to preserve historical cost integrity.

---

*This document serves as the product blueprint. Next step: pick a feature to wireframe or start building the Laravel migrations and Eloquent models.*
