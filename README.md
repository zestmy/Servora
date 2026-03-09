# Servora

A multi-tenant Food & Beverage operations management system built with Laravel 12, Livewire 3, and Tailwind CSS.

## Features

### Ingredients & Recipes
- Ingredient master list with UOM conversions, supplier pricing, and cost tracking
- Recipe builder with costing, yield calculations, and prep item support
- 2-level shared category hierarchy (cost centers)
- CSV import for bulk ingredient and recipe uploads

### Purchasing
- Full procurement flow: **PO > Approval > DO > GRN > Receive**
- Optional PO approval workflow (configurable per company)
- PO approver assignments per outlet (Operations Manager, Manager, Chef)
- PDF generation for Purchase Orders, Delivery Orders, and Goods Received Notes
- Form templates for quick PO creation

### Sales
- Daily sales entry by sales category with meal period and pax tracking
- Z-report import (image/PDF with AI-powered extraction)
- File attachments on sales records with lightbox preview
- CSV export

### Inventory
- Stock takes with cost-by-category breakdown and variance tracking
- Wastage recording (ingredient and recipe-level)
- Staff meal recording with cost tracking
- Prep item management (semi-finished goods with yield and costing)
- **Inter-outlet transfers** with full status workflow: Draft > In Transit > Received / Cancelled
- Transfer scoping by outlet (shows transfers where outlet is sender or receiver)

### Reports
- Multi-tab reports dashboard: Performance, Cost Analysis, Wastage & Staff Meals
- Monthly P&L cost summary (Revenue, Opening, Purchases, Transfers, Closing, COGS, Cost %)
- PDF export for Performance Report, Cost Analysis Report, and Wastage Report
- 6-month revenue vs purchases trend chart
- Cost % gauges per category
- CSV export on reports, sales, and purchasing

### AI Analytics
- AI-powered business insights using Claude API
- Performance analysis, cost analysis, and wastage analysis
- Cached analysis results with refresh capability
- Role-restricted access (Admin, Business Manager, Operations Manager)

### Dashboard
- Role-based dashboards (Business Manager, Operations, Chef, Purchasing, Finance)
- Calendar events and reminders
- Quick-access stats and recent activity

### Multi-Tenancy & Access Control
- Company-scoped data isolation via Eloquent global scopes
- Role-based access: Super Admin, System Admin, Company Admin, Business Manager, Operations Manager, Manager, Chef, Staff, Purchasing, Finance
- Multi-outlet support with outlet switcher on profile page
- Per-outlet PO approver assignments

### Settings
- Company details with logo, registration number, billing address
- Outlet/branch management
- User management with role and multi-outlet assignment
- Ingredient categories, recipe categories, sales categories, cost types
- PO form templates
- Calendar events management
- API keys management

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2+
- **Frontend:** Livewire 3, Alpine.js, Tailwind CSS, Vite
- **Database:** MySQL 8
- **Auth:** Laravel Breeze (Livewire stack)
- **Roles:** Spatie Laravel Permission
- **PDF:** barryvdh/laravel-dompdf
- **Export:** maatwebsite/excel, custom CSV service
- **AI:** Claude API (Anthropic) for analytics

## Installation

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

## Default Login

After seeding:
- **Email:** admin@servora.test
- **Password:** password

## License

Proprietary. All rights reserved.
