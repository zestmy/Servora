<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Livewire\Dashboard;
use App\Livewire\Ingredients\Index as IngredientsIndex;
use App\Livewire\Ingredients\Import as IngredientsImport;
use App\Livewire\Recipes\Index as RecipesIndex;
use App\Livewire\Recipes\Form as RecipesForm;
use App\Livewire\Recipes\Show as RecipesShow;
use App\Livewire\Recipes\Import as RecipesImport;
use App\Http\Controllers\RecipeCostPdfController;
use App\Livewire\Purchasing\Index as PurchasingIndex;
use App\Livewire\Purchasing\OrderForm as PurchasingOrderForm;
use App\Livewire\Purchasing\ReceiveForm as PurchasingReceiveForm;
use App\Livewire\Sales\Index as SalesIndex;
use App\Livewire\Sales\Import as SalesImport;
use App\Livewire\Sales\SalesForm;
use App\Livewire\Inventory\Index as InventoryIndex;
use App\Livewire\Inventory\StockTakeForm;
use App\Livewire\Inventory\WastageForm;
use App\Livewire\Inventory\StaffMealForm;
use App\Livewire\Inventory\PrepItemForm;
use App\Livewire\Inventory\TransferForm;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Reports\PriceHistory as ReportsPriceHistory;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Livewire\Settings\Suppliers as SettingsSuppliers;
use App\Livewire\Settings\Categories as SettingsCategories;
use App\Livewire\Settings\RecipeCategories as SettingsRecipeCategories;
use App\Livewire\Settings\SalesCategories as SettingsSalesCategories;
use App\Livewire\Settings\FormTemplates as SettingsFormTemplates;
use App\Livewire\Settings\FormTemplateEdit as SettingsFormTemplateEdit;
use App\Livewire\Settings\ApiKeys as SettingsApiKeys;
use App\Livewire\Settings\Outlets as SettingsOutlets;
use App\Livewire\Settings\Users as SettingsUsers;
use App\Livewire\Settings\CompanyDetails as SettingsCompanyDetails;
use App\Livewire\Settings\CostTypes as SettingsCostTypes;
use App\Livewire\Settings\PoApprovers as SettingsPoApprovers;
use App\Livewire\Settings\CalendarEvents as SettingsCalendarEvents;
use App\Livewire\Settings\SalesTargets as SettingsSalesTargets;
use App\Livewire\Settings\Departments as SettingsDepartments;
use App\Livewire\Settings\ParLevels as SettingsParLevels;
use App\Livewire\Settings\LabourCosts as SettingsLabourCosts;
use App\Livewire\Analytics\Index as AnalyticsIndex;
use App\Livewire\Purchasing\ConvertToDoForm as PurchasingConvertToDoForm;
use App\Livewire\Purchasing\GrnReceiveForm as PurchasingGrnReceiveForm;
use App\Livewire\Purchasing\PurchaseRequestForm as PurchasingRequestForm;
use App\Livewire\Purchasing\ConsolidateForm as PurchasingConsolidateForm;
use App\Livewire\Purchasing\StockTransferForm as PurchasingStockTransferForm;
use App\Livewire\Purchasing\InvoiceIndex as PurchasingInvoiceIndex;
use App\Livewire\Purchasing\InvoiceShow as PurchasingInvoiceShow;
use App\Livewire\Purchasing\InvoiceReceive as PurchasingInvoiceReceive;
use App\Livewire\Settings\CpuManagement as SettingsCpuManagement;
use App\Livewire\Settings\TaxRates as SettingsTaxRates;
use App\Http\Controllers\PurchaseDocumentPdfController;
use App\Http\Controllers\IngredientExportController;
use App\Http\Controllers\StockTakeCountSheetController;
use App\Http\Controllers\Lms\SopPdfController;
use App\Livewire\Settings\LmsUsers as SettingsLmsUsers;
use App\Livewire\Admin\Plans\Index as AdminPlansIndex;
use App\Livewire\Admin\Plans\Form as AdminPlansForm;
use App\Livewire\Admin\Subscriptions\Index as AdminSubscriptionsIndex;
use App\Livewire\Auth\SaasRegister;
use App\Livewire\Onboarding\Wizard as OnboardingWizard;
use App\Livewire\Marketing\Home as MarketingHome;
use App\Livewire\Marketing\Pricing as MarketingPricing;
use App\Livewire\Marketing\Features as MarketingFeatures;
use App\Livewire\Marketing\ReferralProgram as MarketingReferralProgram;
use App\Livewire\Marketing\PageView as MarketingPageView;
use App\Livewire\Admin\Pages as AdminPages;
use App\Livewire\Billing\Index as BillingIndex;
use App\Livewire\Billing\Checkout as BillingCheckout;
use App\Livewire\Billing\ReferralDashboard;
use App\Http\Controllers\Webhook\ChipInWebhookController;
use App\Http\Controllers\ReferralTrackingController;
use App\Livewire\Kitchen\Index as KitchenIndex;
use App\Livewire\Kitchen\ProductionOrderForm as KitchenOrderForm;
use App\Livewire\Kitchen\ProductionExecute as KitchenExecute;
use App\Livewire\Kitchen\PrepRequestForm as KitchenPrepRequestForm;
use App\Livewire\Settings\KitchenManagement as SettingsKitchenManagement;
use App\Livewire\Admin\Referrals\Programs as AdminReferralPrograms;
use App\Livewire\Admin\Referrals\Dashboard as AdminReferralDashboard;
use App\Livewire\Admin\TrialDashboard as AdminTrialDashboard;
use App\Livewire\Admin\CompanyHealth as AdminCompanyHealth;
use App\Livewire\Admin\Announcements as AdminAnnouncements;

// Home — marketing for guests, dashboard for logged-in users
Route::get('/', MarketingHome::class)->name('marketing.home');

// Marketing pages (public, no auth)
Route::get('/pricing', MarketingPricing::class)->name('pricing');
Route::get('/features', MarketingFeatures::class)->name('features');
Route::get('/for-suppliers', \App\Livewire\Marketing\ForSuppliers::class)->name('for-suppliers');
Route::get('/marketplace', \App\Livewire\Marketing\Marketplace::class)->name('marketplace');
Route::get('/referral', MarketingReferralProgram::class)->name('referral.program');
Route::get('/register/start', SaasRegister::class)->name('saas.register');
Route::get('/page/{slug}', MarketingPageView::class)->name('page.show');

// Public video share (loginless, QR from printed SOP PDF)
Route::get('/v/{token}', [\App\Http\Controllers\VideoShareController::class, 'show'])->name('video.share');
Route::get('/v/{token}/data', [\App\Http\Controllers\VideoShareController::class, 'data'])->name('video.share.data');

// CHIP-IN webhook (no auth, no CSRF)
Route::post('/webhooks/chipin', [ChipInWebhookController::class, 'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.chipin');

// Referral tracking (short link)
Route::get('/r/{code}', ReferralTrackingController::class)->name('referral.track');
Route::get('/ref/{code}', ReferralTrackingController::class); // legacy fallback

// Supplier portal
Route::prefix('supplier')->group(function () {
    Route::get('/register', [\App\Http\Controllers\Supplier\AuthController::class, 'showRegister'])->name('supplier.register');
    Route::post('/register', [\App\Http\Controllers\Supplier\AuthController::class, 'register'])->name('supplier.register.submit');
    Route::get('/login', [\App\Http\Controllers\Supplier\AuthController::class, 'showLogin'])->name('supplier.login');
    Route::post('/login', [\App\Http\Controllers\Supplier\AuthController::class, 'login'])->name('supplier.login.submit');
    Route::get('/forgot-password', [\App\Http\Controllers\Supplier\AuthController::class, 'showForgotPassword'])->name('supplier.forgot-password');
    Route::post('/forgot-password', [\App\Http\Controllers\Supplier\AuthController::class, 'sendResetLink'])->name('supplier.forgot-password.submit');
    Route::get('/reset-password', [\App\Http\Controllers\Supplier\AuthController::class, 'showResetPassword'])->name('supplier.reset-password');
    Route::post('/reset-password', [\App\Http\Controllers\Supplier\AuthController::class, 'resetPassword'])->name('supplier.reset-password.submit');
});
Route::middleware(\App\Http\Middleware\SupplierAuthenticate::class)->prefix('supplier')->group(function () {
    Route::get('/dashboard', \App\Livewire\Supplier\Dashboard::class)->name('supplier.dashboard');
    Route::get('/products', \App\Livewire\Supplier\Products::class)->name('supplier.products');
    Route::get('/orders', \App\Livewire\Supplier\Orders::class)->name('supplier.orders');
    Route::get('/orders/{id}', \App\Livewire\Supplier\OrderShow::class)->name('supplier.orders.show');
    Route::get('/invoices', \App\Livewire\Supplier\Invoices::class)->name('supplier.invoices');
    Route::get('/quotations', \App\Livewire\Supplier\Quotations::class)->name('supplier.quotations');
    Route::get('/quotations/{quotation_request_supplier_id}/respond', \App\Livewire\Supplier\QuotationResponse::class)->name('supplier.quotations.respond');
    Route::get('/credit-notes', \App\Livewire\Supplier\CreditNotes::class)->name('supplier.credit-notes');
    Route::get('/profile', \App\Livewire\Supplier\Profile::class)->name('supplier.profile');
    Route::post('/logout', [\App\Http\Controllers\Supplier\AuthController::class, 'logout'])->name('supplier.logout');
});

// Affiliate portal (public referral partners)
Route::prefix('affiliate')->group(function () {
    Route::get('/register', [\App\Http\Controllers\Affiliate\AuthController::class, 'showRegister'])->name('affiliate.register');
    Route::post('/register', [\App\Http\Controllers\Affiliate\AuthController::class, 'register'])->name('affiliate.register.submit');
    Route::get('/login', [\App\Http\Controllers\Affiliate\AuthController::class, 'showLogin'])->name('affiliate.login');
    Route::post('/login', [\App\Http\Controllers\Affiliate\AuthController::class, 'login'])->name('affiliate.login.submit');
    Route::get('/forgot-password', [\App\Http\Controllers\Affiliate\AuthController::class, 'showForgotPassword'])->name('affiliate.forgot-password');
    Route::post('/forgot-password', [\App\Http\Controllers\Affiliate\AuthController::class, 'sendResetLink'])->name('affiliate.forgot-password.submit');
    Route::get('/reset-password', [\App\Http\Controllers\Affiliate\AuthController::class, 'showResetPassword'])->name('affiliate.reset-password');
    Route::post('/reset-password', [\App\Http\Controllers\Affiliate\AuthController::class, 'resetPassword'])->name('affiliate.reset-password.submit');
});
Route::middleware('auth:affiliate')->prefix('affiliate')->group(function () {
    Route::get('/dashboard', \App\Http\Controllers\Affiliate\DashboardController::class)->name('affiliate.dashboard');
    Route::post('/bank', [\App\Http\Controllers\Affiliate\BankController::class, 'update'])->name('affiliate.update-bank');
    Route::post('/logout', [\App\Http\Controllers\Affiliate\AuthController::class, 'logout'])->name('affiliate.logout');
});

Route::middleware(['auth', 'verified', 'company.scope', 'enforce.subscription'])->group(function () {
    // Onboarding (must be before onboarding middleware)
    Route::get('/onboarding', OnboardingWizard::class)->name('onboarding');

    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/ingredients', IngredientsIndex::class)->name('ingredients.index')->middleware('can:ingredients.view');
    Route::get('/ingredients/export', [IngredientExportController::class, 'export'])->name('ingredients.export')->middleware('can:ingredients.view');
    Route::get('/ingredients/import', IngredientsImport::class)->name('ingredients.import')->middleware('can:ingredients.view');
    Route::get('/recipes', RecipesIndex::class)->name('recipes.index')->middleware('can:recipes.view');
    Route::get('/recipes/import', RecipesImport::class)->name('recipes.import')->middleware('can:recipes.view');
    Route::get('/recipes/create', RecipesForm::class)->name('recipes.create')->middleware('can:recipes.view');
    Route::get('/recipes/cost-pdf/all', [RecipeCostPdfController::class, 'all'])->name('recipes.cost-pdf-all')->middleware('can:recipes.view');
    Route::get('/recipes/cost-pdf/summary', [RecipeCostPdfController::class, 'summary'])->name('recipes.cost-pdf-summary')->middleware('can:recipes.view');
    Route::get('/recipes/prep/cost-pdf/all', [RecipeCostPdfController::class, 'prepAll'])->name('recipes.prep-cost-pdf-all')->middleware('can:recipes.view');
    Route::get('/recipes/prep/cost-pdf/summary', [RecipeCostPdfController::class, 'prepSummary'])->name('recipes.prep-cost-pdf-summary')->middleware('can:recipes.view');
    Route::get('/recipes/{id}', RecipesShow::class)->name('recipes.show')->middleware('can:recipes.view');
    Route::get('/recipes/{id}/edit', RecipesForm::class)->name('recipes.edit')->middleware('can:recipes.view');
    Route::get('/recipes/{id}/cost-pdf', [RecipeCostPdfController::class, 'single'])->name('recipes.cost-pdf')->middleware('can:recipes.view');
    Route::get('/purchasing', PurchasingIndex::class)->name('purchasing.index')->middleware('can:purchasing.view');
    Route::get('/purchasing/orders/create', PurchasingOrderForm::class)->name('purchasing.orders.create')->middleware('can:purchasing.view');
    Route::get('/purchasing/orders/{id}/edit', PurchasingOrderForm::class)->name('purchasing.orders.edit')->middleware('can:purchasing.view');
    Route::get('/purchasing/orders/{id}/receive', PurchasingReceiveForm::class)->name('purchasing.orders.receive')->middleware('can:purchasing.view');
    Route::get('/purchasing/receive', PurchasingReceiveForm::class)->name('purchasing.receive')->middleware('can:purchasing.view');
    Route::get('/purchasing/orders/{id}/convert-to-do', PurchasingConvertToDoForm::class)->name('purchasing.convert-to-do')->middleware('can:purchasing.view');
    Route::get('/purchasing/grn/{id}/receive', PurchasingGrnReceiveForm::class)->name('purchasing.grn.receive')->middleware('can:purchasing.view');
    Route::get('/purchasing/pdf/{type}/{id}', PurchaseDocumentPdfController::class)->name('purchasing.pdf')->middleware('can:purchasing.view');
    Route::get('/purchasing/requests/create', PurchasingRequestForm::class)->name('purchasing.requests.create')->middleware('can:purchasing.view');
    Route::get('/purchasing/requests/{id}/edit', PurchasingRequestForm::class)->name('purchasing.requests.edit')->middleware('can:purchasing.view');
    Route::get('/purchasing/consolidate', PurchasingConsolidateForm::class)->name('purchasing.consolidate')->middleware('can:purchasing.view');
    Route::get('/purchasing/transfers/create', PurchasingStockTransferForm::class)->name('purchasing.transfers.create')->middleware('can:purchasing.view');
    Route::get('/purchasing/invoices', PurchasingInvoiceIndex::class)->name('purchasing.invoices.index')->middleware('can:purchasing.view');
    Route::get('/purchasing/invoices/receive', PurchasingInvoiceReceive::class)->name('purchasing.invoices.receive')->middleware('can:purchasing.view');
    Route::get('/purchasing/invoices/{id}', PurchasingInvoiceShow::class)->name('purchasing.invoices.show')->middleware('can:purchasing.view');
    Route::get('/purchasing/price-comparison', \App\Livewire\Purchasing\PriceComparison::class)->name('purchasing.price-comparison')->middleware('can:purchasing.view');
    Route::get('/purchasing/suppliers', \App\Livewire\Purchasing\SupplierDirectory::class)->name('purchasing.suppliers.directory')->middleware('can:purchasing.view');
    Route::get('/purchasing/credit-notes', \App\Livewire\Purchasing\CreditNoteIndex::class)->name('purchasing.credit-notes.index')->middleware('can:purchasing.view');
    Route::get('/purchasing/credit-notes/create', \App\Livewire\Purchasing\CreditNoteForm::class)->name('purchasing.credit-notes.create')->middleware('can:purchasing.view');
    Route::get('/purchasing/credit-notes/{id}', \App\Livewire\Purchasing\CreditNoteForm::class)->name('purchasing.credit-notes.edit')->middleware('can:purchasing.view');
    Route::get('/purchasing/rfq', \App\Livewire\Purchasing\RfqIndex::class)->name('purchasing.rfq.index')->middleware('can:purchasing.view');
    Route::get('/purchasing/rfq/create', \App\Livewire\Purchasing\RfqForm::class)->name('purchasing.rfq.create')->middleware('can:purchasing.view');
    Route::get('/purchasing/rfq/{id}', \App\Livewire\Purchasing\RfqShow::class)->name('purchasing.rfq.show')->middleware('can:purchasing.view');
    Route::get('/purchasing/rfq/{id}/edit', \App\Livewire\Purchasing\RfqForm::class)->name('purchasing.rfq.edit')->middleware('can:purchasing.view');

    // Workspace switcher
    Route::get('/workspace/{mode}', function (string $mode) {
        if (! in_array($mode, ['outlet', 'kitchen'])) abort(404);
        session(['workspace_mode' => $mode]);
        if ($mode === 'kitchen') {
            $kitchen = \Illuminate\Support\Facades\DB::table('kitchen_users')
                ->where('user_id', Auth::id())->first();
            if ($kitchen) session(['active_kitchen_id' => $kitchen->kitchen_id]);
            return redirect()->route('kitchen.index');
        }
        return redirect()->route('dashboard');
    })->name('workspace.switch');

    // Kitchen (requires kitchen user access)
    Route::middleware('kitchen.user')->group(function () {
        Route::get('/kitchen', KitchenIndex::class)->name('kitchen.index');
        Route::get('/kitchen/orders/create', KitchenOrderForm::class)->name('kitchen.orders.create');
        Route::get('/kitchen/orders/{id}/edit', KitchenOrderForm::class)->name('kitchen.orders.edit');
        Route::get('/kitchen/orders/{id}/execute', KitchenExecute::class)->name('kitchen.orders.execute');
        Route::get('/kitchen/prep-requests/create', KitchenPrepRequestForm::class)->name('kitchen.prep-requests.create');
        Route::get('/kitchen/prep-requests/{id}/edit', KitchenPrepRequestForm::class)->name('kitchen.prep-requests.edit');
        Route::get('/kitchen/recipes', \App\Livewire\Kitchen\ProductionRecipes::class)->name('kitchen.recipes.index');
        Route::get('/kitchen/recipes/create', \App\Livewire\Kitchen\ProductionRecipeForm::class)->name('kitchen.recipes.create');
        Route::get('/kitchen/recipes/{id}/edit', \App\Livewire\Kitchen\ProductionRecipeForm::class)->name('kitchen.recipes.edit');
    });

    Route::get('/sales', SalesIndex::class)->name('sales.index')->middleware('can:sales.view');
    Route::get('/sales/create', SalesForm::class)->name('sales.create')->middleware('can:sales.view');
    Route::get('/sales/import', SalesImport::class)->name('sales.import')->middleware('can:sales.view');
    Route::get('/sales/{id}/edit', SalesForm::class)->name('sales.edit')->middleware('can:sales.view');
    Route::get('/inventory', InventoryIndex::class)->name('inventory.index')->middleware('can:inventory.view');
    Route::get('/inventory/stock-takes/create', StockTakeForm::class)->name('inventory.stock-takes.create')->middleware('can:inventory.view');
    Route::get('/inventory/stock-takes/{id}', StockTakeForm::class)->name('inventory.stock-takes.show')->middleware('can:inventory.view');
    Route::get('/inventory/stock-takes/{id}/count-sheet', StockTakeCountSheetController::class)->name('inventory.stock-takes.count-sheet')->middleware('can:inventory.view');
    Route::get('/inventory/wastage/create', WastageForm::class)->name('inventory.wastage.create')->middleware('can:inventory.view');
    Route::get('/inventory/wastage/{id}', WastageForm::class)->name('inventory.wastage.show')->middleware('can:inventory.view');
    Route::get('/inventory/staff-meals/create', StaffMealForm::class)->name('inventory.staff-meals.create')->middleware('can:inventory.view');
    Route::get('/inventory/staff-meals/{id}', StaffMealForm::class)->name('inventory.staff-meals.show')->middleware('can:inventory.view');
    Route::get('/inventory/prep-items/create', PrepItemForm::class)->name('inventory.prep-items.create')->middleware('can:inventory.view');
    Route::get('/inventory/prep-items/{id}', PrepItemForm::class)->name('inventory.prep-items.show')->middleware('can:inventory.view');
    Route::get('/inventory/transfers/create', TransferForm::class)->name('inventory.transfers.create')->middleware('can:inventory.view');
    Route::get('/inventory/transfers/{id}', TransferForm::class)->name('inventory.transfers.show')->middleware('can:inventory.view');
    Route::get('/reports', \App\Livewire\Reports\Hub::class)->name('reports.hub')->middleware('can:reports.view');
    Route::get('/reports/cost-summary', ReportsIndex::class)->name('reports.index')->middleware('can:reports.view');
    Route::get('/reports/price-history', ReportsPriceHistory::class)->name('reports.price-history')->middleware('can:reports.view');
    // Purchase reports
    Route::get('/reports/purchase-analysis', \App\Livewire\Reports\Purchase\PurchaseAnalysis::class)->name('reports.purchase-analysis')->middleware('can:reports.view');
    Route::get('/reports/po-summary', \App\Livewire\Reports\Purchase\PoSummary::class)->name('reports.po-summary')->middleware('can:reports.view');
    // Order reports
    Route::get('/reports/order-history', \App\Livewire\Reports\Order\OrderHistory::class)->name('reports.order-history')->middleware('can:reports.view');
    Route::get('/reports/order-summary', \App\Livewire\Reports\Order\OrderSummary::class)->name('reports.order-summary')->middleware('can:reports.view');
    Route::get('/reports/order-items-by-branch', \App\Livewire\Reports\Order\OrderItemsByBranch::class)->name('reports.order-items-by-branch')->middleware('can:reports.view');
    Route::get('/reports/delivery-order', \App\Livewire\Reports\Order\DeliveryOrderReport::class)->name('reports.delivery-order')->middleware('can:reports.view');
    Route::get('/reports/grn-report', \App\Livewire\Reports\Order\GrnReport::class)->name('reports.grn-report')->middleware('can:reports.view');
    Route::get('/reports/invoice-summary', \App\Livewire\Reports\Order\InvoiceSummary::class)->name('reports.invoice-summary')->middleware('can:reports.view');
    // Inventory reports
    Route::get('/reports/stock-balance-package', \App\Livewire\Reports\Inventory\StockBalancePackage::class)->name('reports.stock-balance-package')->middleware('can:reports.view');
    Route::get('/reports/stock-balance-product', \App\Livewire\Reports\Inventory\StockBalanceProduct::class)->name('reports.stock-balance-product')->middleware('can:reports.view');
    Route::get('/reports/stock-card', \App\Livewire\Reports\Inventory\StockCard::class)->name('reports.stock-card')->middleware('can:reports.view');
    // Inventory Action reports
    Route::get('/reports/stock-count', \App\Livewire\Reports\InventoryAction\StockCount::class)->name('reports.stock-count')->middleware('can:reports.view');
    Route::get('/reports/stock-count-analysis', \App\Livewire\Reports\InventoryAction\StockCountAnalysis::class)->name('reports.stock-count-analysis')->middleware('can:reports.view');
    Route::get('/reports/stock-wastage', \App\Livewire\Reports\InventoryAction\StockWastage::class)->name('reports.stock-wastage')->middleware('can:reports.view');
    Route::get('/reports/stock-transfer-history', \App\Livewire\Reports\InventoryAction\StockTransferHistory::class)->name('reports.stock-transfer-history')->middleware('can:reports.view');
    Route::get('/reports/stock-adjustment', \App\Livewire\Reports\InventoryAction\StockAdjustment::class)->name('reports.stock-adjustment')->middleware('can:reports.view');
    // Menu reports
    Route::get('/reports/sales-menu-ingredients', \App\Livewire\Reports\Menu\SalesMenuIngredients::class)->name('reports.sales-menu-ingredients')->middleware('can:reports.view');
    Route::get('/reports/menu-ingredients', \App\Livewire\Reports\Menu\MenuIngredients::class)->name('reports.menu-ingredients')->middleware('can:reports.view');
    // Other reports
    Route::get('/reports/inventory-variance', \App\Livewire\Reports\Others\InventoryVariance::class)->name('reports.inventory-variance')->middleware('can:reports.view');
    // Kitchen reports
    Route::get('/reports/production-history', \App\Livewire\Reports\Kitchen\ProductionHistory::class)->name('reports.production-history')->middleware('can:reports.view');
    Route::get('/reports/yield-analysis', \App\Livewire\Reports\Kitchen\YieldAnalysis::class)->name('reports.yield-analysis')->middleware('can:reports.view');
    Route::get('/settings', SettingsIndex::class)->name('settings.index')->middleware('can:settings.view');
    Route::get('/settings/suppliers', SettingsSuppliers::class)->name('settings.suppliers')->middleware('can:purchasing.view');
    Route::get('/settings/categories', SettingsCategories::class)->name('settings.categories')->middleware('can:settings.view');
    Route::get('/settings/recipe-categories', SettingsRecipeCategories::class)->name('settings.recipe-categories')->middleware('can:settings.view');
    Route::get('/settings/price-classes', \App\Livewire\Settings\PriceClasses::class)->name('settings.price-classes')->middleware('can:settings.view');
    Route::get('/settings/cost-types', SettingsCostTypes::class)->name('settings.cost-types')->middleware('can:settings.view');
    Route::get('/settings/sales-categories', SettingsSalesCategories::class)->name('settings.sales-categories')->middleware('can:settings.view');
    Route::get('/settings/form-templates', SettingsFormTemplates::class)->name('settings.form-templates')->middleware('can:purchasing.view');
    Route::get('/settings/form-templates/{id}/edit', SettingsFormTemplateEdit::class)->name('settings.form-templates.edit')->middleware('can:purchasing.view');
    Route::get('/settings/outlets', SettingsOutlets::class)->name('settings.outlets')->middleware('can:settings.view');
    Route::get('/settings/api-keys', SettingsApiKeys::class)->name('settings.api-keys')->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class . ':Super Admin|System Admin');
    Route::get('/settings/users', SettingsUsers::class)->name('settings.users')->middleware('can:users.manage');
    Route::get('/settings/po-approvers', SettingsPoApprovers::class)->name('settings.po-approvers')->middleware('can:settings.view');
    Route::get('/settings/company-details', SettingsCompanyDetails::class)->name('settings.company-details')->middleware('can:users.manage');
    Route::get('/settings/calendar-events', SettingsCalendarEvents::class)->name('settings.calendar-events')->middleware('can:settings.view');
    Route::get('/settings/sales-targets', SettingsSalesTargets::class)->name('settings.sales-targets')->middleware('can:sales.view');
    Route::get('/settings/departments', SettingsDepartments::class)->name('settings.departments')->middleware('can:settings.view');
    Route::get('/settings/par-levels', SettingsParLevels::class)->name('settings.par-levels')->middleware('can:settings.view');
    Route::get('/settings/outlet-groups', \App\Livewire\Settings\OutletGroups::class)->name('settings.outlet-groups')->middleware('can:settings.view');
    Route::get('/settings/labour-costs', SettingsLabourCosts::class)->name('settings.labour-costs')->middleware('can:hr.view');
    Route::get('/settings/lms-users', SettingsLmsUsers::class)->name('settings.lms-users')->middleware('can:hr.view');
    Route::get('/settings/cpu-management', SettingsCpuManagement::class)->name('settings.cpu-management')->middleware('can:settings.view');
    Route::get('/settings/kitchen-management', SettingsKitchenManagement::class)->name('settings.kitchen-management')->middleware('can:settings.view');
    Route::get('/settings/tax-rates', SettingsTaxRates::class)->name('settings.tax-rates')->middleware('can:settings.view');
    Route::get('/settings/supplier-mapping', \App\Livewire\Settings\SupplierProductMapping::class)->name('settings.supplier-mapping')->middleware('can:purchasing.view');
    Route::get('/settings/price-alerts', \App\Livewire\Settings\PriceAlerts::class)->name('settings.price-alerts')->middleware('can:purchasing.view');
    Route::get('/training/sop/{id}/pdf', [SopPdfController::class, 'single'])->name('training.sop.pdf')->middleware('can:hr.view');
    Route::get('/training/sop/pdf-all', [SopPdfController::class, 'all'])->name('training.sop.pdf-all')->middleware('can:hr.view');

    // HR routes
    Route::get('/hr/overtime-claims', \App\Livewire\Hr\OvertimeClaims::class)->name('hr.overtime-claims')->middleware('can:hr.view');
    Route::get('/hr/overtime-claims/pdf/{employee}', \App\Http\Controllers\OtClaimPdfController::class)->name('hr.ot-claims.pdf')->middleware('can:hr.view');
    Route::get('/settings/ot-approvers', \App\Livewire\Settings\OtApprovers::class)->name('settings.ot-approvers')->middleware('can:settings.view');

    Route::get('/analytics', AnalyticsIndex::class)->name('analytics.index')->middleware(['can:reports.view', 'check.feature:analytics']);

    // Billing routes (Business Manager, Company Admin, Super Admin)
    Route::get('/billing', BillingIndex::class)->name('billing.index');
    Route::get('/billing/checkout/{planSlug}', BillingCheckout::class)->name('billing.checkout');

    // Refer & Earn (all users)
    Route::get('/refer', ReferralDashboard::class)->name('referral.dashboard');

    // Admin routes (System Admin only)
    Route::prefix('admin')->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class . ':System Admin')->group(function () {
        Route::get('/plans', AdminPlansIndex::class)->name('admin.plans.index');
        Route::get('/plans/create', AdminPlansForm::class)->name('admin.plans.create');
        Route::get('/plans/{id}/edit', AdminPlansForm::class)->name('admin.plans.edit');
        Route::get('/subscriptions', AdminSubscriptionsIndex::class)->name('admin.subscriptions.index');
        Route::get('/referrals', AdminReferralDashboard::class)->name('admin.referrals.index');
        Route::get('/referrals/programs', AdminReferralPrograms::class)->name('admin.referrals.programs');
        Route::get('/trials', AdminTrialDashboard::class)->name('admin.trials.index');
        Route::get('/company-health', AdminCompanyHealth::class)->name('admin.company-health');
        Route::get('/announcements', AdminAnnouncements::class)->name('admin.announcements');
        Route::get('/pages', AdminPages::class)->name('admin.pages');
        Route::get('/coupons', \App\Livewire\Admin\Coupons::class)->name('admin.coupons');
    });
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__ . '/auth.php';
