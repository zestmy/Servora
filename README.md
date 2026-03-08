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
- Stock takes with cost-by-category breakdown
- Wastage recording (ingredient and recipe-level)
- Inter-outlet transfers
- Prep item tracking
- Staff meal recording

### Reports & Dashboard
- Monthly P&L cost summary (Revenue, Opening, Purchases, Transfers, Closing, COGS, Cost %)
- Role-based dashboards (Business Manager, Operations, Chef, Purchasing, Finance)
- 6-month revenue vs purchases trend chart
- Cost % gauges per category
- CSV export on reports, sales, and purchasing

### Multi-Tenancy & Access Control
- Company-scoped data isolation via Eloquent global scopes
- Role-based access: Super Admin, Company Admin, Business Manager, Operations Manager, Manager, Chef, Staff, Purchasing, Finance
- Multi-outlet support with session-based outlet switcher
- Per-outlet PO approver assignments

### Settings
- Company details with logo, registration number, billing address
- Outlet/branch management
- User management with role and multi-outlet assignment
- Ingredient categories, recipe categories, sales categories, cost types
- PO form templates
- API keys management

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2
- **Frontend:** Livewire 3, Volt, Alpine.js, Tailwind CSS, Vite
- **Database:** MySQL 8
- **Auth:** Laravel Breeze (Livewire stack)
- **Roles:** Spatie Laravel Permission
- **PDF:** barryvdh/laravel-dompdf
- **Export:** maatwebsite/excel, custom CSV service

## Installation

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

## Default Login

After seeding:
- **Email:** admin@servora.test
- **Password:** password

## License

Proprietary. All rights reserved.
