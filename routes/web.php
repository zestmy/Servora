<?php

/*
 * Staff apps, loaded FIRST on purpose.
 *
 * They serve /labels and /clock on company subdomains. The manager-facing
 * routes below carry no domain constraint, so they match any host — if
 * they were registered first they would swallow every subdomain request
 * and staff would be bounced to the main-app login. Registration order is
 * the only thing separating the two.
 */
require __DIR__ . '/labels-staff.php';
require __DIR__ . '/clock-staff.php';
require __DIR__ . '/print-agent.php';
require __DIR__ . '/pos-agent.php';

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Livewire\Dashboard;
use App\Livewire\Ingredients\Index as IngredientsIndex;
use App\Livewire\Ingredients\Import as IngredientsImport;
use App\Livewire\Recipes\Index as RecipesIndex;
use App\Livewire\Recipes\Form as RecipesForm;
use App\Livewire\Recipes\Show as RecipesShow;
use App\Livewire\Recipes\SmartImport as RecipesImport;
use App\Http\Controllers\RecipeCostPdfController;
use App\Http\Controllers\RecipeCostExcelController;
use App\Livewire\Purchasing\Index as PurchasingIndex;
use App\Livewire\Purchasing\OrderForm as PurchasingOrderForm;
use App\Livewire\Purchasing\ReceiveForm as PurchasingReceiveForm;
use App\Livewire\Sales\Index as SalesIndex;
use App\Livewire\Sales\Import as SalesImport;
use App\Livewire\Sales\SalesForm;
use App\Livewire\Inventory\Index as InventoryIndex;
use App\Livewire\Inventory\StockTakeForm;
use App\Livewire\Inventory\PurchaseCaptureForm;
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
use App\Http\Controllers\Lms\SopExportController;
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
// Companion app installers. Public on purpose: the person at the outlet PC
// extracting a zip rarely has a login, and nothing works until a manager
// pairs it from inside Servora. Singular /download because the PLURAL path
// is the physical public/downloads/ directory the zips are served from —
// a route there loses to the filesystem on every web server we run.
Route::get('/download', \App\Livewire\Marketing\Downloads::class)->name('marketing.downloads');

// Free tools. Public on purpose: useful before anything is asked for.
Route::get('/tools', App\Livewire\Marketing\ToolsIndex::class)->name('tools.index');
Route::get('/tools/recipe-cost-calculator', App\Livewire\Marketing\RecipeCostCalculator::class)
    ->name('tools.recipe-cost');
Route::get('/tools/food-cost-calculator', App\Livewire\Marketing\FoodCostCalculator::class)
    ->name('tools.food-cost');
Route::get('/tools/menu-engineering-matrix', App\Livewire\Marketing\MenuEngineeringMatrix::class)
    ->name('tools.menu-matrix');
Route::get('/tools/salary-calculator', App\Livewire\Marketing\SalaryCalculator::class)
    ->name('tools.salary');
Route::get('/tools/ea-form-generator', App\Livewire\Marketing\EaFormGenerator::class)
    ->name('tools.ea-form');

// Help Centre — the product manual. Public on purpose: most of what it
// answers is asked before anyone has an account, and a manual behind a login
// cannot answer it. Content is managed at /admin/docs; nothing here is
// tenant data.
Route::get('/help', \App\Livewire\Help\Index::class)->name('help.index');
Route::get('/help/{categorySlug}', \App\Livewire\Help\Category::class)->name('help.category');
Route::get('/help/{categorySlug}/{articleSlug}', \App\Livewire\Help\Article::class)->name('help.article');

// Public certificate verification (loginless, QR from the printed certificate)
Route::get('/verify/certificate/{serial}', [\App\Http\Controllers\Training\CertificateVerifyController::class, 'show'])
    ->name('certificate.verify');

// Public video share (loginless, QR from printed SOP PDF)
Route::get('/v/{token}', [\App\Http\Controllers\VideoShareController::class, 'show'])->name('video.share');
Route::get('/v/{token}/data', [\App\Http\Controllers\VideoShareController::class, 'data'])->name('video.share.data');

// CHIP-IN webhook (no auth, no CSRF)
Route::post('/webhooks/chipin', [ChipInWebhookController::class, 'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.chipin');

// Secure deploy webhook — HMAC-signed, timestamp-protected, command allowlist
// See: App\Http\Controllers\DeployWebhookController
Route::post('/internal/deploy-hook', \App\Http\Controllers\DeployWebhookController::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->middleware('throttle:10,1')
    ->name('deploy.webhook');

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

/*
 * A training certificate as a PDF.
 *
 * OUTSIDE the authenticated group on purpose, and not by oversight. Two
 * different populations download this: a manager on the `web` guard, and the
 * person it belongs to on the `lms` guard from the training portal. Those are
 * separate guards — an `auth` middleware satisfied by one is not satisfied by
 * the other — so the check cannot be middleware at all. The controller asks the
 * right question of whichever guard turned up, and refuses anyone else.
 */
Route::get('/training/certificates/{id}/pdf', [\App\Http\Controllers\Training\CertificatePdfController::class, 'show'])
    ->name('training.certificates.pdf');

Route::middleware(['auth', 'verified', 'company.scope', 'enforce.subscription'])->group(function () {
    // Onboarding (must be before onboarding middleware)
    Route::get('/onboarding', OnboardingWizard::class)->name('onboarding');

    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/ingredients', IngredientsIndex::class)->name('ingredients.index')->middleware('can:ingredients.view');
    Route::get('/ingredients/export', [IngredientExportController::class, 'export'])->name('ingredients.export')->middleware('can:ingredients.view');
    Route::get('/ingredients/pdf', \App\Http\Controllers\IngredientPdfController::class)->name('ingredients.pdf')->middleware('can:ingredients.view');
    Route::get('/ingredients/import', IngredientsImport::class)->name('ingredients.import')->middleware('can:ingredients.import');
    // Price Watcher — two-step flow:
    // 1) Scan Documents: upload / photograph a supplier document; AI extracts
    //    the supplier, date, and line items and stages it for review.
    // 2) Review Documents: match the extracted items against existing
    //    ingredients and import. Opens per-document review pages.
    Route::get('/ingredients/scan-document', \App\Livewire\Ingredients\ScanDocument::class)
        ->name('ingredients.scan-document')->middleware('can:ingredients.import');
    Route::get('/ingredients/review-documents', \App\Livewire\Ingredients\ReviewDocuments::class)
        ->name('ingredients.review-documents')->middleware('can:ingredients.import');
    Route::get('/ingredients/review-documents/{document}', \App\Livewire\Ingredients\ReviewDocument::class)
        ->name('ingredients.review-documents.show')->middleware('can:ingredients.import');

    // Legacy redirects so old bookmarks / links keep working.
    Route::redirect('/ingredients/price-watcher', '/ingredients/scan-document');
    Route::redirect('/ingredients/supplier-match', '/ingredients/scan-document');
    Route::get('/recipes', RecipesIndex::class)->name('recipes.index')->middleware('can:recipes.view');
    Route::get('/recipes/import', RecipesImport::class)->name('recipes.import')->middleware('can:recipes.import');
    Route::get('/recipes/create', RecipesForm::class)->name('recipes.create')->middleware('can:recipes.manage');
    Route::get('/recipes/cost-pdf/all', [RecipeCostPdfController::class, 'all'])->name('recipes.cost-pdf-all')->middleware('can:recipes.view');
    Route::get('/recipes/cost-pdf/summary', [RecipeCostPdfController::class, 'summary'])->name('recipes.cost-pdf-summary')->middleware('can:recipes.view');
    Route::get('/recipes/prep/cost-pdf/all', [RecipeCostPdfController::class, 'prepAll'])->name('recipes.prep-cost-pdf-all')->middleware('can:recipes.view');
    Route::get('/recipes/prep/cost-pdf/summary', [RecipeCostPdfController::class, 'prepSummary'])->name('recipes.prep-cost-pdf-summary')->middleware('can:recipes.view');
    Route::get('/recipes/cost-excel', [RecipeCostExcelController::class, 'all'])->name('recipes.cost-excel')->middleware('can:recipes.view');
    Route::get('/recipes/prep/cost-excel', [RecipeCostExcelController::class, 'prepAll'])->name('recipes.prep-cost-excel')->middleware('can:recipes.view');
    Route::get('/recipes/{id}', RecipesShow::class)->name('recipes.show')->middleware('can:recipes.view');
    Route::get('/recipes/{id}/edit', RecipesForm::class)->name('recipes.edit')->middleware('can:recipes.manage');
    Route::get('/recipes/{id}/cost-pdf', [RecipeCostPdfController::class, 'single'])->name('recipes.cost-pdf')->middleware('can:recipes.view');
    Route::get('/purchasing', PurchasingIndex::class)->name('purchasing.index')->middleware('can:purchasing.view');
    Route::get('/purchasing/orders/create', PurchasingOrderForm::class)->name('purchasing.orders.create')->middleware('can:purchasing.orders.create');
    Route::get('/purchasing/orders/{id}/edit', PurchasingOrderForm::class)->name('purchasing.orders.edit')->middleware('can:purchasing.orders.edit');
    Route::get('/purchasing/orders/{id}/receive', PurchasingReceiveForm::class)->name('purchasing.orders.receive')->middleware('can:purchasing.view');
    Route::get('/purchasing/receive', PurchasingReceiveForm::class)->name('purchasing.receive')->middleware('can:purchasing.view');
    Route::get('/purchasing/orders/{id}/convert-to-do', PurchasingConvertToDoForm::class)->name('purchasing.convert-to-do')->middleware('can:purchasing.orders.edit');
    Route::get('/purchasing/grn/{id}/receive', PurchasingGrnReceiveForm::class)->name('purchasing.grn.receive')->middleware('can:purchasing.view');
    Route::get('/purchasing/pdf/{type}/{id}', PurchaseDocumentPdfController::class)->name('purchasing.pdf')->middleware('can:purchasing.view');
    Route::get('/purchasing/requests/create', PurchasingRequestForm::class)->name('purchasing.requests.create')->middleware('can:purchasing.requests.create');
    Route::get('/purchasing/requests/{id}/edit', PurchasingRequestForm::class)->name('purchasing.requests.edit')->middleware('can:purchasing.requests.edit');
    Route::get('/purchasing/consolidate', PurchasingConsolidateForm::class)->name('purchasing.consolidate')->middleware('can:purchasing.consolidate');
    Route::get('/purchasing/transfers/create', PurchasingStockTransferForm::class)->name('purchasing.transfers.create')->middleware('can:purchasing.transfers.create');
    Route::get('/purchasing/invoices', PurchasingInvoiceIndex::class)->name('purchasing.invoices.index')->middleware('can:purchasing.view');
    Route::get('/purchasing/invoices/receive', PurchasingInvoiceReceive::class)->name('purchasing.invoices.receive')->middleware('can:purchasing.view');
    Route::get('/purchasing/invoices/{id}', PurchasingInvoiceShow::class)->name('purchasing.invoices.show')->middleware('can:purchasing.view');
    Route::get('/purchasing/suppliers', \App\Livewire\Purchasing\SupplierDirectory::class)->name('purchasing.suppliers.directory')->middleware('can:purchasing.suppliers.manage');
    Route::get('/purchasing/credit-notes', \App\Livewire\Purchasing\CreditNoteIndex::class)->name('purchasing.credit-notes.index')->middleware('can:purchasing.view');
    Route::get('/purchasing/credit-notes/create', \App\Livewire\Purchasing\CreditNoteForm::class)->name('purchasing.credit-notes.create')->middleware('can:purchasing.view');
    Route::get('/purchasing/credit-notes/{id}', \App\Livewire\Purchasing\CreditNoteForm::class)->name('purchasing.credit-notes.edit')->middleware('can:purchasing.view');

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
        Route::get('/kitchen/recipes', \App\Livewire\Kitchen\ProductionRecipes::class)->name('kitchen.recipes.index');
        Route::get('/kitchen/recipes/create', \App\Livewire\Kitchen\ProductionRecipeForm::class)->name('kitchen.recipes.create');
        Route::get('/kitchen/recipes/{id}/edit', \App\Livewire\Kitchen\ProductionRecipeForm::class)->name('kitchen.recipes.edit');
    });

    Route::get('/sales', SalesIndex::class)->name('sales.index')->middleware('can:sales.view');
    Route::get('/sales/create', SalesForm::class)->name('sales.create')->middleware('can:sales.record');
    Route::get('/sales/import', SalesImport::class)->name('sales.import')->middleware('can:sales.import');
    Route::get('/sales/pos-sync', \App\Livewire\Sales\PosSync::class)->name('sales.pos-sync')->middleware('can:sales.import');
    Route::get('/sales/{id}/edit', SalesForm::class)->name('sales.edit')->middleware('can:sales.record');
    Route::get('/inventory', InventoryIndex::class)->name('inventory.index')->middleware('can:inventory.view');
    Route::get('/inventory/stock-takes/create', StockTakeForm::class)->name('inventory.stock-takes.create')->middleware('can:inventory.stock_takes.record');
    Route::get('/inventory/stock-takes/{id}', StockTakeForm::class)->name('inventory.stock-takes.show')->middleware('can:inventory.stock_takes.record');
    Route::get('/inventory/stock-takes/{id}/count-sheet', StockTakeCountSheetController::class)->name('inventory.stock-takes.count-sheet')->middleware('can:inventory.view');
    Route::get('/inventory/wastage/create', WastageForm::class)->name('inventory.wastage.create')->middleware('can:inventory.wastage.record');
    Route::get('/inventory/wastage/{id}', WastageForm::class)->name('inventory.wastage.show')->middleware('can:inventory.wastage.record');
    Route::get('/inventory/staff-meals/create', StaffMealForm::class)->name('inventory.staff-meals.create')->middleware('can:inventory.staff_meals.record');
    Route::get('/inventory/staff-meals/{id}', StaffMealForm::class)->name('inventory.staff-meals.show')->middleware('can:inventory.staff_meals.record');
    Route::get('/inventory/prep-items/create', PrepItemForm::class)->name('inventory.prep-items.create')->middleware('can:inventory.prep_items.record');
    Route::get('/inventory/prep-items/{id}', PrepItemForm::class)->name('inventory.prep-items.show')->middleware('can:inventory.prep_items.record');
    Route::get('/inventory/transfers/create', TransferForm::class)->name('inventory.transfers.create')->middleware('can:inventory.transfers.record');
    Route::get('/inventory/transfers/{id}', TransferForm::class)->name('inventory.transfers.show')->middleware('can:inventory.transfers.record');
    Route::get('/inventory/purchases/create', PurchaseCaptureForm::class)->name('inventory.purchases.create')->middleware('can:inventory.purchases.record');
    Route::get('/inventory/purchases/{id}', PurchaseCaptureForm::class)->name('inventory.purchases.show')->middleware('can:inventory.purchases.record');
    Route::get('/reports', \App\Livewire\Reports\Hub::class)->name('reports.hub')->middleware('can:reports.view');
    Route::get('/reports/cost-summary', ReportsIndex::class)->name('reports.index')->middleware('can:reports.view');
    Route::get('/reports/price-history', ReportsPriceHistory::class)->name('reports.price-history')->middleware('can:reports.view');
    // The distribution rather than the payroll, so it rides on the service
    // charge ability rather than on salary visibility.
    Route::get('/reports/service-charge-payout', \App\Livewire\Reports\Hr\ServiceChargePayout::class)->name('reports.service-charge-payout')->middleware(['can:reports.view', 'can:hr.attendance.service_charge']);
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
    // No settings.view gate: the index is a list of links, every tile carries
    // the permission its own destination requires, and each of those routes
    // keeps its own middleware. Gating the whole page instead hid a module's
    // settings from the person who administers that module.
    Route::get('/settings', SettingsIndex::class)->name('settings.index');
    // Ungated like the index: the downloads are public assets, and the person
    // fetching a zip onto an outlet PC often administers nothing.
    Route::get('/settings/downloads', \App\Livewire\Settings\Downloads::class)->name('settings.downloads');
    Route::get('/settings/suppliers', SettingsSuppliers::class)->name('settings.suppliers')->middleware('can:purchasing.suppliers.manage');
    Route::get('/settings/categories', SettingsCategories::class)->name('settings.categories')->middleware('can:ingredients.manage');
    Route::get('/settings/recipe-categories', SettingsRecipeCategories::class)->name('settings.recipe-categories')->middleware('can:recipes.manage');
    Route::get('/settings/price-classes', \App\Livewire\Settings\PriceClasses::class)->name('settings.price-classes')->middleware('can:recipes.price');
    Route::get('/settings/sales-categories', SettingsSalesCategories::class)->name('settings.sales-categories')->middleware('can:sales.record');
    Route::get('/settings/form-templates', SettingsFormTemplates::class)->name('settings.form-templates')->middleware('can:purchasing.suppliers.manage');
    Route::get('/settings/form-templates/{id}/edit', SettingsFormTemplateEdit::class)->name('settings.form-templates.edit')->middleware('can:purchasing.suppliers.manage');
    Route::get('/settings/outlets', SettingsOutlets::class)->name('settings.outlets')->middleware('can:settings.outlets');
    Route::get('/settings/api-keys', SettingsApiKeys::class)->name('settings.api-keys')->middleware(\App\Http\Middleware\SystemAdminOnly::class);
    Route::get('/settings/users', SettingsUsers::class)->name('settings.users')->middleware('can:users.manage');
    // The Roles and Effective-access halves of the same screen — see x-access-tabs.
    Route::get('/settings/roles-access', \App\Livewire\Settings\RolesAccess::class)->name('settings.roles-access')->middleware('can:users.manage');
    Route::get('/company/create', \App\Livewire\CompanyCreate::class)->name('company.create'); // gated in component (can_manage_users)
    Route::get('/settings/po-approvers', SettingsPoApprovers::class)->name('settings.po-approvers')->middleware('can:settings.po_approvers');
    Route::get('/settings/company-details', SettingsCompanyDetails::class)->name('settings.company-details')->middleware('can:users.manage');
    Route::get('/settings/calendar-events', SettingsCalendarEvents::class)->name('settings.calendar-events')->middleware('can:reports.view');
    Route::get('/settings/sales-targets', SettingsSalesTargets::class)->name('settings.sales-targets')->middleware('can:sales.record');
    Route::get('/settings/departments', SettingsDepartments::class)->name('settings.departments')->middleware('can:settings.departments');
    Route::get('/settings/sections', \App\Livewire\Settings\Sections::class)->name('settings.sections')->middleware('can:settings.sections');
    Route::get('/settings/certifications', \App\Livewire\Settings\CertificationTypes::class)->name('settings.certifications')->middleware('can:settings.certifications');
    Route::get('/settings/pay-components', \App\Livewire\Settings\PayComponents::class)->name('settings.pay-components')->middleware('can:hr.compensation');
    Route::get('/settings/statutory', \App\Livewire\Settings\StatutoryRates::class)->name('settings.statutory')->middleware('can:hr.compensation');
    Route::get('/settings/banks', \App\Livewire\Settings\Banks::class)->name('settings.banks')->middleware('can:hr.compensation');
    Route::get('/settings/employee-particulars', \App\Livewire\Settings\EmployeeParticulars::class)->name('settings.employee-particulars')->middleware('can:settings.hr');
    Route::get('/settings/leave-types', \App\Livewire\Settings\LeaveTypes::class)->name('settings.leave-types')->middleware('can:settings.hr');
    Route::get('/settings/leave-approvers', \App\Livewire\Settings\LeaveApprovers::class)->name('settings.leave-approvers')->middleware('can:settings.hr');
    Route::get('/settings/public-holidays', \App\Livewire\Settings\PublicHolidays::class)->name('settings.public-holidays')->middleware('can:settings.hr');
    Route::get('/settings/par-levels', SettingsParLevels::class)->name('settings.par-levels')->middleware('can:inventory.view');
    Route::get('/settings/outlet-groups', \App\Livewire\Settings\OutletGroups::class)->name('settings.outlet-groups')->middleware('can:settings.outlet_groups');
    Route::get('/settings/labour-costs', SettingsLabourCosts::class)->name('settings.labour-costs')->middleware('can:hr.view');
    // Moved from `hr.view` to its own ability when the screen moved from HR to
    // Learning & Development. Everyone holding `hr.view` was granted
    // `training.portal` in the same migration, so nobody lost access.
    Route::get('/settings/lms-users', SettingsLmsUsers::class)->name('settings.lms-users')->middleware('can:training.portal');
    Route::get('/settings/cpu-management', SettingsCpuManagement::class)->name('settings.cpu-management')->middleware('can:settings.cpu');
    Route::get('/settings/kitchen-management', SettingsKitchenManagement::class)->name('settings.kitchen-management')->middleware('can:settings.kitchens');
    Route::get('/settings/tax-rates', SettingsTaxRates::class)->name('settings.tax-rates')->middleware('can:settings.tax_rates');
    Route::get('/settings/supplier-mapping', \App\Livewire\Settings\SupplierProductMapping::class)->name('settings.supplier-mapping')->middleware('can:purchasing.suppliers.manage');
    Route::get('/settings/price-alerts', \App\Livewire\Settings\PriceAlerts::class)->name('settings.price-alerts')->middleware('can:purchasing.suppliers.manage');
    Route::get('/settings/price-alerts/export-pdf', [\App\Http\Controllers\PriceHistoryExportController::class, 'pdf'])->name('settings.price-alerts.export-pdf')->middleware('can:purchasing.suppliers.manage');
    Route::get('/settings/price-alerts/export-excel', [\App\Http\Controllers\PriceHistoryExportController::class, 'excel'])->name('settings.price-alerts.export-excel')->middleware('can:purchasing.suppliers.manage');
    // Label printing — HACCP food safety labels. See docs/label-printing-plan.md
    Route::get('/labels', \App\Livewire\Labels\PrintScreen::class)->name('labels.print')->middleware('can:labels.print');
    Route::get('/labels/sets', \App\Livewire\Labels\Sets::class)->name('labels.sets')->middleware('can:labels.print');
    Route::get('/labels/sets/{set}/print', \App\Livewire\Labels\SetPrint::class)->name('labels.sets.print')->middleware('can:labels.print');
    // A preparer's face for the "Prepared by" picker — its own route because
    // the print screens are behind labels.print, not hr.view. See the controller.
    Route::get('/labels/preparer/{employee}/photo', [\App\Http\Controllers\Labels\PreparerPhotoController::class, 'show'])->name('labels.preparer.photo')->middleware('can:labels.print');
    // Printable cut-out QR cards. Its own path rather than /labels/sets/qr
    // so it can never be mistaken for a set id by the route above.
    Route::get('/labels/set-qr-sheet', \App\Http\Controllers\Labels\SetQrSheetController::class)->name('labels.sets.qr-sheet')->middleware('can:labels.manage');
    Route::get('/labels/expiring', \App\Livewire\Labels\Expiring::class)->name('labels.expiring')->middleware('can:labels.print');
    Route::get('/labels/log', \App\Livewire\Labels\PrintLog::class)->name('labels.log')->middleware('can:labels.view_log');
    // Moved to HR (one PIN now opens both staff apps). Kept as a redirect
    // rather than deleted: this path is in the label printing plan, in
    // managers' bookmarks, and in whatever they wrote down when they first
    // set the kitchen up.
    Route::redirect('/labels/staff-access', '/hr/staff-pins', 301)->name('labels.staff-access');
    Route::get('/labels/templates', \App\Livewire\Labels\Templates::class)->name('labels.templates')->middleware('can:labels.manage');
    Route::get('/labels/templates/{template}/design', \App\Livewire\Labels\TemplateDesigner::class)->name('labels.templates.design')->middleware('can:labels.manage');
    Route::get('/labels/shelf-life', \App\Livewire\Labels\ShelfLifeGrid::class)->name('labels.shelf-life')->middleware('can:labels.manage');
    Route::get('/labels/printers', \App\Livewire\Labels\Printers::class)->name('labels.printers')->middleware('can:labels.manage');
    Route::get('/labels/agents', \App\Livewire\Labels\Agents::class)->name('labels.agents')->middleware('can:labels.manage');
    Route::get('/labels/settings', \App\Livewire\Labels\Settings::class)->name('labels.settings')->middleware('can:labels.manage');

    /*
     * ── Learning & Development ────────────────────────────────────────────
     *
     * Courses, quizzes, live sessions, paths, assignments, the leaderboard,
     * report cards and certificates.
     */
    Route::get('/training/courses', \App\Livewire\Training\Courses::class)->name('training.courses')->middleware('can:training.view');
    Route::get('/training/courses/create', \App\Livewire\Training\CourseForm::class)->name('training.courses.create')->middleware('can:training.manage');
    Route::get('/training/courses/{id}/edit', \App\Livewire\Training\CourseForm::class)->name('training.courses.edit')->middleware('can:training.manage');
    Route::get('/training/quizzes', \App\Livewire\Training\Quizzes::class)->name('training.quizzes')->middleware('can:training.view');
    Route::get('/training/quizzes/{id}', \App\Livewire\Training\QuizBuilder::class)->name('training.quizzes.edit')->middleware('can:training.manage');
    Route::get('/training/live', \App\Livewire\Training\Sessions::class)->name('training.live')->middleware('can:training.host');
    Route::get('/training/live/{id}', \App\Livewire\Training\LiveHost::class)->name('training.live.host')->middleware('can:training.host');
    // A scheduled challenge has no room to host, so it gets a board instead of
    // a console — the whole point of scheduling one is that nobody has to run it.
    Route::get('/training/challenge/{id}', \App\Livewire\Training\ChallengeBoard::class)->name('training.challenge')->middleware('can:training.host');
    Route::get('/training/paths', \App\Livewire\Training\Paths::class)->name('training.paths')->middleware('can:training.view');
    Route::get('/training/assignments', \App\Livewire\Training\Assignments::class)->name('training.assignments')->middleware('can:training.assign');
    Route::get('/training/leaderboard', \App\Livewire\Training\Leaderboard::class)->name('training.leaderboard')->middleware('can:training.view');
    Route::get('/training/report-cards', \App\Livewire\Training\ReportCards::class)->name('training.report-cards')->middleware('can:training.reports');
    Route::get('/training/certificates', \App\Livewire\Training\Certificates::class)->name('training.certificates')->middleware('can:training.view');
    /*
     * SOP exports, moved from `hr.view` to `training.view` with the rest of the
     * module. Nobody loses access — the same migration granted `training.view`
     * to every holder of `hr.view` — and it closes a real hole the move opened:
     * these links live on the Training Portal screen, whose own gate is now
     * `training.portal`, so a person granted only the portal was being offered
     * three download buttons that would 403.
     */
    Route::get('/training/sop/{id}/pdf', [SopPdfController::class, 'single'])->name('training.sop.pdf')->middleware('can:training.view');
    Route::get('/training/sop/pdf-all', [SopPdfController::class, 'all'])->name('training.sop.pdf-all')->middleware('can:training.view');
    // The whole catalogue is too heavy to render in a request — it goes through
    // the queue and is collected here. See App\Jobs\GenerateSopExport.
    Route::get('/training/sop/export/{export}', [SopExportController::class, 'download'])->name('training.sop.export.download')->middleware('can:training.view');

    // HR routes
    Route::get('/hr/employees', \App\Livewire\Hr\Employees::class)->name('hr.employees')->middleware('can:hr.view');
    // The PIN that opens the staff apps (clock-in and labels alike).
    Route::get('/hr/staff-pins', \App\Livewire\Hr\StaffPins::class)->name('hr.staff-pins')->middleware('can:staff.pins');
    Route::get('/hr/employees/export-pdf', [\App\Http\Controllers\EmployeeExportController::class, 'pdf'])->name('hr.employees.export-pdf')->middleware('can:hr.view');
    Route::get('/hr/employees/export-excel', [\App\Http\Controllers\EmployeeExportController::class, 'excel'])->name('hr.employees.export-excel')->middleware('can:hr.view');
    // One employee as a form. Outlet scope and the pay/standing gates are
    // enforced in the controller, the same way the edit screen does it.
    Route::get('/hr/employees/{employee}/pdf', [\App\Http\Controllers\EmployeeExportController::class, 'detailsPdf'])->name('hr.employees.details-pdf')->middleware('can:hr.view');
    // Staff photos and scanned paperwork live on the private disk; the
    // controller re-checks outlet access on every request.
    Route::get('/hr/employees/{employee}/photo', [\App\Http\Controllers\Hr\EmployeeDocumentController::class, 'photo'])->name('hr.employees.photo')->middleware('can:hr.view');
    Route::get('/hr/employee-documents/{document}', [\App\Http\Controllers\Hr\EmployeeDocumentController::class, 'show'])->name('hr.employee-documents.show')->middleware('can:hr.view');
    Route::get('/hr/employee-documents/{document}/download', [\App\Http\Controllers\Hr\EmployeeDocumentController::class, 'download'])->name('hr.employee-documents.download')->middleware('can:hr.view');
    // Add / edit is a full page, not a modal — the form is too tall for a dialog.
    Route::get('/hr/employees/create', \App\Livewire\Hr\EmployeeForm::class)->name('hr.employees.create')->middleware('can:hr.employees.manage');
    Route::get('/hr/employees/{id}/edit', \App\Livewire\Hr\EmployeeForm::class)->name('hr.employees.edit')->middleware('can:hr.employees.manage');
    // Compensation sits behind hr.compensation — the same gate that hides
    // salary on the Employees list and the exports.
    Route::get('/hr/compensation', \App\Livewire\Hr\Compensation::class)->name('hr.compensation')->middleware('can:hr.compensation');
    // Declared before /hr/compensation/{id} so "export-pdf" is not swallowed as an employee id.
    Route::get('/hr/compensation/export-pdf', \App\Http\Controllers\CompensationPdfController::class)->name('hr.compensation.export-pdf')->middleware('can:hr.compensation');
    Route::get('/hr/compensation/{id}', \App\Livewire\Hr\EmployeeCompensation::class)->name('hr.compensation.employee')->middleware('can:hr.compensation');
    // Payroll: runs, and payslips printed from a run's locked lines.
    Route::get('/hr/payroll', \App\Livewire\Hr\Payroll::class)->name('hr.payroll')->middleware('can:hr.payroll');
    // EA forms declared BEFORE /hr/payroll/{run}, or "ea" is swallowed as a run id.
    Route::get('/hr/payroll/ea-forms', \App\Livewire\Hr\EaForms::class)->name('hr.payroll.ea-forms')->middleware('can:hr.payroll');
    Route::get('/hr/payroll/ea', \App\Http\Controllers\EaFormController::class)->name('hr.payroll.ea')->middleware('can:hr.payroll');
    Route::get('/hr/payroll/form-e/{format?}', \App\Http\Controllers\FormEController::class)->name('hr.payroll.form-e')->middleware('can:hr.payroll');
    Route::get('/hr/payroll/{run}', \App\Livewire\Hr\PayrollRunShow::class)->name('hr.payroll.show')->middleware('can:hr.payroll');
    Route::get('/hr/payroll/{run}/payslips', [\App\Http\Controllers\PayslipController::class, 'all'])->name('hr.payroll.payslips')->middleware('can:hr.payroll');
    Route::get('/hr/payroll/{run}/payslip/{line}', [\App\Http\Controllers\PayslipController::class, 'single'])->name('hr.payroll.payslip')->middleware('can:hr.payroll');
    Route::get('/hr/payroll/{run}/export/{type}', \App\Http\Controllers\PayrollExportController::class)->name('hr.payroll.export')->middleware('can:hr.payroll');
    // The run's employee table as a sheet to check before approving — allowed
    // on a draft, unlike the statutory and bank exports above.
    Route::get('/hr/payroll/{run}/list-pdf', \App\Http\Controllers\PayrollRunListPdfController::class)->name('hr.payroll.list-pdf')->middleware('can:hr.payroll');
    Route::get('/hr/attendance', \App\Livewire\Hr\AttendanceRecords::class)->name('hr.attendance')->middleware('can:hr.attendance');
    Route::get('/hr/attendance/export-pdf', [\App\Http\Controllers\AttendanceExportController::class, 'pdf'])->name('hr.attendance.export-pdf')->middleware('can:hr.attendance');
    // Payout slips are pay data, so hr.compensation on top of the grid's own gate.
    Route::get('/hr/attendance/service-charge-payout', [\App\Http\Controllers\AttendanceExportController::class, 'payout'])->name('hr.attendance.payout-pdf')->middleware(['can:hr.attendance', 'can:hr.attendance.service_charge']);
    // Web clock-in — the staff-facing app lives in routes/clock-staff.php;
    // these are the manager-facing review, policy and enrolment screens.
    Route::get('/hr/clock-ins', \App\Livewire\Hr\ClockEvents::class)->name('hr.clock-ins')->middleware('can:hr.clock');
    Route::get('/hr/clock-ins/{event}/selfie', [\App\Http\Controllers\Hr\ClockImageController::class, 'selfie'])->name('hr.clock-ins.selfie')->middleware('can:hr.clock');
    Route::get('/hr/clock-settings', \App\Livewire\Hr\ClockSettings::class)->name('hr.clock-settings')->middleware('can:settings.hr');
    // Kiosk tablets. Same gate as the geofence and face enrolment rather than
    // a permission of its own: pairing a device that can vouch for an outlet
    // is the same kind of trust, and a fourth way to say "manages the clock"
    // is a fourth thing to keep in step.
    Route::get('/hr/clock-devices', \App\Livewire\Hr\ClockDevices::class)->name('hr.clock-devices')->middleware('can:hr.clock.manage');
    Route::get('/hr/face-enrolment', \App\Livewire\Hr\FaceEnrolment::class)->name('hr.face-enrolment')->middleware('can:hr.clock.manage');
    Route::get('/hr/face-enrolment/{descriptor}/photo', [\App\Http\Controllers\Hr\ClockImageController::class, 'enrolment'])->name('hr.face-enrolment.photo')->middleware('can:hr.clock.manage');
    // Plain HTTP, not Livewire actions: enrolment gates the whole feature and
    // must not depend on Livewire's JavaScript having started.
    Route::post('/hr/face-enrolment/capture', [\App\Http\Controllers\Hr\FaceEnrolmentController::class, 'store'])->name('hr.face-enrolment.capture')->middleware('can:hr.clock.manage');
    Route::delete('/hr/face-enrolment/{descriptor}', [\App\Http\Controllers\Hr\FaceEnrolmentController::class, 'destroy'])->name('hr.face-enrolment.delete')->middleware('can:hr.clock.manage');
    Route::get('/hr/overtime-claims', \App\Livewire\Hr\OvertimeClaims::class)->name('hr.overtime-claims')->middleware('can:hr.claims');
    Route::get('/hr/overtime-claims/pdf/{employee}', \App\Http\Controllers\OtClaimPdfController::class)->name('hr.ot-claims.pdf')->middleware('can:hr.claims');
    Route::get('/hr/overtime-claims/summary-pdf', \App\Http\Controllers\OtClaimSummaryPdfController::class)->name('hr.ot-claims.summary-pdf')->middleware('can:hr.claims');
    // The statement for whatever the screen is currently filtered to, beside
    // the approved-only export rather than replacing it — see the controller.
    Route::get('/hr/overtime-claims/filtered-pdf', \App\Http\Controllers\OtClaimFilteredPdfController::class)->name('hr.ot-claims.filtered-pdf')->middleware('can:hr.claims');
    Route::get('/hr/leave', \App\Livewire\Hr\Leave::class)->name('hr.leave')->middleware('can:hr.leave');
    Route::get('/hr/time-off', \App\Livewire\Hr\TimeOff::class)->name('hr.time-off')->middleware('can:hr.leave');
    // The MC behind a request. Private disk, so it is served rather than linked.
    Route::get('/hr/leave/{leaveRequest}/attachment', [\App\Http\Controllers\Hr\LeaveAttachmentController::class, 'show'])
        ->name('hr.leave.attachment')->middleware('can:hr.leave');
    Route::get('/hr/documents', \App\Livewire\Hr\Documents::class)->name('hr.documents')->middleware('can:hr.documents.view');
    Route::get('/hr/duty-roster', \App\Livewire\Hr\DutyRoster::class)->name('hr.duty-roster'); // Viewable by all authenticated users
    Route::get('/hr/shifts', \App\Livewire\Hr\Shifts::class)->name('hr.shifts')->middleware('can:roster.settings');
    Route::get('/hr/roster-stations', \App\Livewire\Hr\RosterStations::class)->name('hr.roster-stations')->middleware('can:roster.settings');
    Route::get('/hr/roster-approvers', \App\Livewire\Hr\RosterApprovers::class)->name('hr.roster-approvers')->middleware('can:roster.settings');
    Route::get('/hr/roster-email-recipients', \App\Livewire\Hr\RosterEmailRecipients::class)->name('hr.roster-email-recipients')->middleware('can:roster.settings');
    Route::get('/hr/roster-settings', \App\Livewire\Hr\RosterSettings::class)->name('hr.roster-settings')->middleware('can:roster.settings');
    Route::get('/settings/ot-approvers', \App\Livewire\Settings\OtApprovers::class)->name('settings.ot-approvers')->middleware('can:settings.ot_approvers');
    Route::get('/settings/document-folders', \App\Livewire\Settings\DocumentFolders::class)->name('settings.document-folders')->middleware('can:hr.documents.manage');
    Route::get('/settings/reports', \App\Livewire\Settings\ReportSubscriptions::class)->name('settings.reports')->middleware('can:reports.view');
    Route::get('/settings/reports/log/{logId}/pdf', [\App\Http\Controllers\ReportPdfController::class, 'download'])->name('settings.reports.log-pdf')->middleware('can:reports.view');

    Route::get('/analytics', AnalyticsIndex::class)->name('analytics.index')->middleware(['can:reports.view', 'check.feature:analytics']);

    // Audit Logs (admins + managers)
    Route::get('/audit-logs', \App\Livewire\Audit\Index::class)->name('audit-logs.index')->middleware('can:audit.view');
    Route::get('/audit-logs/export/csv', [\App\Http\Controllers\AuditLogExportController::class, 'csv'])->name('audit-logs.export.csv')->middleware('can:audit.view');
    Route::get('/audit-logs/export/pdf', [\App\Http\Controllers\AuditLogExportController::class, 'pdf'])->name('audit-logs.export.pdf')->middleware('can:audit.view');

    // Billing routes (Business Manager, Company Admin, Super Admin)
    Route::get('/billing', BillingIndex::class)->name('billing.index');
    Route::get('/billing/checkout/{planSlug}', BillingCheckout::class)->name('billing.checkout');

    // One PDF route for both audiences. The controller decides what each may
    // pull — a system admin any invoice, a customer only their own company's
    // and only once it has left draft — because that rule differs per user,
    // which is not something route middleware can express.
    Route::get('/invoices/{id}/pdf', \App\Http\Controllers\SubscriptionInvoicePdfController::class)
        ->name('invoices.pdf');

    // Refer & Earn (all users)
    Route::get('/refer', ReferralDashboard::class)->name('referral.dashboard');

    // Admin routes (System Admin only)
    Route::prefix('admin')->middleware(\App\Http\Middleware\SystemAdminOnly::class)->group(function () {
        Route::get('/users', \App\Livewire\Admin\Users::class)->name('admin.users');
        Route::get('/companies', \App\Livewire\Admin\Companies::class)->name('admin.companies');
        Route::get('/role-templates', \App\Livewire\Admin\RoleTemplates::class)->name('admin.role-templates');
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

        // Platform billing. `admin.invoices.*` is the SUBSCRIPTION ledger —
        // Servora billing its tenants. `purchasing.invoices.*` is a tenant
        // recording what its supplier billed it. Different money, different
        // direction, deliberately separate namespaces.
        Route::get('/invoices', \App\Livewire\Admin\Invoices\Index::class)->name('admin.invoices.index');
        Route::get('/invoices/create', \App\Livewire\Admin\Invoices\Form::class)->name('admin.invoices.create');
        Route::get('/invoices/{id}/edit', \App\Livewire\Admin\Invoices\Form::class)->name('admin.invoices.edit');
        Route::get('/billing-settings', \App\Livewire\Admin\BillingSettings::class)->name('admin.billing-settings');

        // Help centre authoring.
        Route::get('/docs', \App\Livewire\Admin\Docs\Index::class)->name('admin.docs.index');
        Route::get('/docs/articles/create', \App\Livewire\Admin\Docs\ArticleForm::class)->name('admin.docs.create');
        Route::get('/docs/articles/{id}/edit', \App\Livewire\Admin\Docs\ArticleForm::class)->name('admin.docs.edit');
    });
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// End impersonation. Deliberately OUTSIDE 'verified' / 'enforce.subscription'
// (and NOT SystemAdminOnly): while impersonating, the logged-in user is the
// non-admin target — an unverified email or an expired subscription must
// never trap the admin in the impersonated session. Authority comes from the
// impersonator_id stored in the session, verified as a system role.
Route::post('/impersonation/stop', [\App\Http\Controllers\ImpersonationController::class, 'stop'])
    ->middleware(['auth'])
    ->name('impersonation.stop');

require __DIR__ . '/auth.php';
