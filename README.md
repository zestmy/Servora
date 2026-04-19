# Servora

A comprehensive multi-tenant Food & Beverage operations management system built with Laravel 12, Livewire 3, and Tailwind CSS. Manage ingredients, recipes, purchasing, sales, inventory, and cost reporting across multiple outlets from a single platform.

> **Developer docs:** see [docs/README.md](docs/README.md) — architecture, domain model, Livewire modules, services, workflows, routes, database schema, and a feature playbook for speedy feature development.

## Tech Stack

| Layer       | Technology                                    |
|-------------|-----------------------------------------------|
| Backend     | Laravel 12, PHP 8.2+                          |
| Frontend    | Livewire 3, Alpine.js, Tailwind CSS, Vite     |
| Database    | MySQL 8                                       |
| Auth        | Laravel Breeze (Livewire stack)               |
| Roles       | Spatie Laravel Permission 6.24                |
| PDF         | barryvdh/laravel-dompdf                       |
| Export      | maatwebsite/excel, custom CsvExportService    |
| AI          | Claude API (Anthropic) for analytics          |

## Features

### Dashboard
- 6 stat cards: Ingredients, Recipes, Pending POs, Today Revenue, Month Revenue, Month Purchases
- Cost % gauges per category with color-coded thresholds
- 6-month Revenue vs Purchases bar chart
- COGS breakdown table by cost center
- Smart alerts: over-cost recipes, pending POs, stock take reminders
- Role-based dashboard variants (Business Manager, Operations, Chef, Purchasing, Finance)
- Calendar events and reminders

### Ingredients
- Full CRUD with code, category, base/recipe UOM, purchase price, yield %
- Automatic cost chain: Purchase Price → Yield Loss → Effective Cost → UOM Conversion → Recipe Cost
- UOM conversions with live cost preview
- Supplier linking with SKU, cost per UOM, and preferred supplier flag
- 2-level category hierarchy (parent/sub) with color-coded dots
- **CSV Export** — download all ingredients for offline editing in Excel
- **Bulk Update** — upload edited CSV to mass-update name, code, price, yield, and active status; auto-recalculates effective cost
- **CSV Import** — bulk create new ingredients from spreadsheet

### Recipes
- Recipe builder with ingredient search, quantity, UOM, and waste % per line
- Live cost summary: total cost, cost per serving, food cost %, gross profit
- Color-coded food cost benchmarks: ≤25% Excellent, 25–35% Good, 35–45% High, >45% Review
- **Menu Categories** — hierarchical main category / sub-category system (e.g. Main Course > Pasta, Pizza)
- **Cost Centers** — separate cost category assignment for P&L reporting
- **Product Images** — upload final plating photos organized into **Dine-In** and **Takeaway** tabs for staff reference
- CSV/Excel import for bulk recipe creation

### Purchasing
- **Full procurement workflow**: Draft PO → Submit → [Approval] → Convert to DO → GRN → Receive
- Configurable PO approval: company-level toggle to require or skip the approval step
- PO approver assignments per outlet (Operations Manager, Branch Manager, Chef)
- 3-tab index: Purchase Orders, Delivery Orders, Goods Received Notes
- **Par Level auto-ordering**: set par levels per ingredient per outlet in Settings; enter current stock balance in PO form and order quantity auto-calculates as `par level - balance`
- Supplier ingredient catalog with auto-pricing when supplier is selected
- **Form Templates** — save and load reusable PO templates for quick ordering
- PDF generation for PO, DO, and GRN documents (includes company logo, registration number, billing address)
- CSV export of filtered purchase orders

### Sales
- Daily sales recording with revenue per cost center (ingredient category)
- Pax count and meal period tracking (Breakfast, Lunch, Dinner, Supper)
- **File attachments** — upload receipts, Z-reports, or images (max 5MB each) with lightbox preview
- Z-report import with AI-powered data extraction
- Sales closures and daily targets per outlet
- CSV export and import

### Inventory
- **Stock Takes** — periodic stock counting with ingredient search and cost-by-category breakdown
- **Wastage Records** — track wastage by ingredient with cost impact
- **Staff Meals** — log staff consumption separately from sales with cost tracking
- **Prep Items** — manage semi-finished goods with yield and costing
- **Outlet Transfers** — transfer stock between outlets with full status workflow: Draft → In Transit → Received / Cancelled
- Transfer scoping by outlet (shows transfers where outlet is sender or receiver)

### Reports
- Monthly P&L cost summary: Revenue, Opening Stock, Purchases, Transfers In/Out, Closing Stock, COGS, Cost %, Wastage
- Cost % progress bars per category
- **MTD Comparison** — toggle to compare 3 periods side-by-side:
  - This Month MTD vs Last Month MTD vs Last Year Same Month MTD
  - Custom "MTD till date" picker for point-in-time comparisons
  - Variance indicators (amount and %) across all metrics
- **4 PDF exports**: Cost Summary, Cost Analysis, Performance Report, Wastage Report — all include MTD comparison data when the toggle is active
- Multi-tab views: Performance, Cost Analysis, Wastage & Staff Meals
- CSV export

### AI Analytics
- AI-powered business insights using Claude API
- Performance analysis, cost analysis, and wastage analysis
- Cached analysis results with refresh capability
- Role-restricted: Super Admin, System Admin, Company Admin, Business Manager, Operations Manager

### Settings
- **Company Details** — logo, registration number, billing address for PDF documents
- **Outlets** — manage multiple outlet/branch locations
- **Users** — create/edit users with role assignment and multi-outlet access
- **Suppliers** — supplier directory with active/inactive status
- **Ingredient Categories** — 2-level hierarchy (parent/sub) with color coding
- **Recipe Categories** — hierarchical main category and sub-category management with color coding
- **Sales Categories** — configure sales line categories
- **Cost Types** — define cost classification types
- **Form Templates** — create reusable PO templates with pre-set ingredient lists
- **PO Approvers** — configure approval workflow; toggle company-wide approval requirement
- **Par Levels** — set reorder par levels per ingredient per outlet; used for auto-ordering in PO form
- **Sales Targets** — monthly and daily revenue/pax targets per outlet
- **Calendar Events** — operational calendar for events and reminders
- **API Keys** — manage API access (Super Admin / System Admin only)

## Architecture

### Multi-Tenancy

All data is scoped by company using Eloquent Global Scopes. The `CompanyScope` is automatically applied to tenant models via `static::addGlobalScope(new CompanyScope())` in the model's `booted()` method.

**Scoped models**: Supplier, Ingredient, Recipe, PurchaseOrder, DeliveryOrder, PurchaseRecord, SalesRecord, WastageRecord, OutletTransfer, StockTake, IngredientParLevel, RecipeCategory, and more.

### Multi-Outlet Access

- Users can be assigned to multiple outlets via the `outlet_user` pivot table
- Active outlet is stored in session and switchable via sidebar dropdown
- Business Manager and Super Admin roles can view data across all outlets ("All Outlets" option)
- All listing components scope queries by the user's active outlet

### Roles & Permissions

Built on Spatie Laravel Permission:

| Role                | Access Level                                       |
|---------------------|----------------------------------------------------|
| Super Admin         | Full system access, all outlets                    |
| System Admin        | Full system access, all outlets                    |
| Company Admin       | Full company access, all outlets                   |
| Business Manager    | All outlets, analytics, reports                    |
| Operations Manager  | Multi-outlet operations view                       |
| Branch Manager      | Assigned outlets, PO approval                      |
| Chef                | Assigned outlets, recipes, inventory               |
| Purchasing          | Cross-outlet purchasing view                       |
| Finance             | Financial reports and summaries                    |
| Staff               | Assigned outlets only, limited modules             |

Gate permissions: `ingredients.view`, `recipes.view`, `purchasing.view`, `sales.view`, `inventory.view`, `reports.view`, `settings.view`, `users.manage`.

### Key Workflows

**Purchasing Flow:**
```
Outlet creates PO (draft)
  → Submit (or auto-approve if company setting is off)
  → Approver approves
  → Purchasing converts PO to Delivery Order (DO)
  → GRN auto-generated from DO
  → Outlet receives GRN, verifies quantities
  → PurchaseRecord created, ingredient costs updated
```

**Cost Calculation Chain:**
```
Purchase Price (per base UOM)
  ÷ Yield % (accounts for prep loss)
  = Effective Cost (per base UOM)
  × UOM Conversion Factor
  = Recipe Cost (per recipe UOM)
```

**P&L Formula:**
```
COGS = Opening Stock + Purchases + Transfers In - Transfers Out - Closing Stock
Cost % = COGS / Revenue × 100
```

## Project Structure

```
app/
├── Http/
│   ├── Controllers/              # PDF and export controllers
│   └── Middleware/                # CompanyScope middleware
├── Livewire/
│   ├── Dashboard.php
│   ├── OutletSwitcher.php
│   ├── Analytics/Index.php
│   ├── Ingredients/              # Index, Import
│   ├── Inventory/                # Index, StockTakeForm, WastageForm, StaffMealForm, PrepItemForm, TransferForm
│   ├── Purchasing/               # Index, OrderForm, ConvertToDoForm, GrnReceiveForm, ReceiveForm
│   ├── Recipes/                  # Index, Form, Import
│   ├── Reports/Index.php
│   ├── Sales/                    # Index, SalesForm, Import
│   └── Settings/                 # 14 settings components
├── Models/                       # 25+ Eloquent models
├── Scopes/CompanyScope.php
├── Services/
│   ├── CostSummaryService.php    # Monthly P&L engine
│   ├── CsvExportService.php      # Generic CSV download helper
│   └── UomService.php            # UOM conversion & cost calculation
└── Traits/
    └── ScopesToActiveOutlet.php

resources/views/
├── layouts/app.blade.php         # Dark sidebar + top nav layout
├── livewire/                     # All Livewire component views
└── pdf/                          # PDF templates (PO, DO, GRN, reports)

database/migrations/              # 60 migration files
routes/web.php                    # All application routes
```

## Installation

### Requirements

- PHP 8.2+
- MySQL 8.0+
- Node.js 18+ and npm
- Composer

### Local Development

```bash
# Clone the repository
git clone https://github.com/zestmy/Servora.git
cd Servora

# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Configure your database in .env, then:
php artisan migrate --seed
php artisan storage:link

# Start development servers
php artisan serve
npm run dev
```

### Default Login

After seeding:
- **Email:** `admin@servora.test`
- **Password:** `password`
- **Role:** Super Admin

### Seeded Data

- 17 Units of Measure (kg, g, mg, lb, oz, L, ml, gal, fl oz, pcs, doz, pack, box, ctn, tray, m, cm)
- Default roles and permissions
- Demo company: "Demo Restaurant Co." (slug: demo-restaurant-co)
- Demo outlet: "Main Branch" (code: MAIN)

### Production Deployment (DigitalOcean)

Spin up an Ubuntu droplet (22.04 LTS recommended), SSH in as root, then:

```bash
git clone https://github.com/zestmy/Servora.git /var/www/servora
cd /var/www/servora
bash deploy/install.sh
```

The install script handles everything:
- Nginx, PHP (8.3/8.4 auto-detected), MySQL 8, Node 22, Composer
- Database creation and configuration
- Frontend build (Vite)
- Migrations and seeding
- SSL via Let's Encrypt (optional)
- Queue worker (systemd) and scheduler (cron)
- File permissions and firewall (UFW)

For subsequent updates:

```bash
bash deploy/update.sh
```

This pulls latest code, rebuilds assets, runs migrations, and clears caches with zero-downtime maintenance mode.

## Troubleshooting

**503 Service Unavailable after update:**
The app may be stuck in maintenance mode if the update script was interrupted:

```bash
cd /var/www/servora
php artisan up
```

**Local changes blocking git pull:**
If the server has local file changes that conflict with the update:

```bash
cd /var/www/servora
git checkout -- .
bash deploy/update.sh
```

**Queue worker not found:**
If the queue worker service hasn't been set up yet:

```bash
cat > /etc/systemd/system/servora-queue.service <<'EOF'
[Unit]
Description=Servora Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/servora
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now servora-queue
```

## License

Proprietary. All rights reserved.
