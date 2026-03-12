<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Dashboard;
use App\Livewire\Ingredients\Index as IngredientsIndex;
use App\Livewire\Ingredients\Import as IngredientsImport;
use App\Livewire\Recipes\Index as RecipesIndex;
use App\Livewire\Recipes\Form as RecipesForm;
use App\Livewire\Recipes\Import as RecipesImport;
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
use App\Livewire\Settings\ParLevels as SettingsParLevels;
use App\Livewire\Analytics\Index as AnalyticsIndex;
use App\Livewire\Purchasing\ConvertToDoForm as PurchasingConvertToDoForm;
use App\Livewire\Purchasing\GrnReceiveForm as PurchasingGrnReceiveForm;
use App\Http\Controllers\PurchaseDocumentPdfController;
use App\Http\Controllers\IngredientExportController;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware(['auth', 'verified', 'company.scope'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/ingredients', IngredientsIndex::class)->name('ingredients.index')->middleware('can:ingredients.view');
    Route::get('/ingredients/export', [IngredientExportController::class, 'export'])->name('ingredients.export')->middleware('can:ingredients.view');
    Route::get('/ingredients/import', IngredientsImport::class)->name('ingredients.import')->middleware('can:ingredients.view');
    Route::get('/recipes', RecipesIndex::class)->name('recipes.index')->middleware('can:recipes.view');
    Route::get('/recipes/import', RecipesImport::class)->name('recipes.import')->middleware('can:recipes.view');
    Route::get('/recipes/create', RecipesForm::class)->name('recipes.create')->middleware('can:recipes.view');
    Route::get('/recipes/{id}/edit', RecipesForm::class)->name('recipes.edit')->middleware('can:recipes.view');
    Route::get('/purchasing', PurchasingIndex::class)->name('purchasing.index')->middleware('can:purchasing.view');
    Route::get('/purchasing/orders/create', PurchasingOrderForm::class)->name('purchasing.orders.create')->middleware('can:purchasing.view');
    Route::get('/purchasing/orders/{id}/edit', PurchasingOrderForm::class)->name('purchasing.orders.edit')->middleware('can:purchasing.view');
    Route::get('/purchasing/orders/{id}/receive', PurchasingReceiveForm::class)->name('purchasing.orders.receive')->middleware('can:purchasing.view');
    Route::get('/purchasing/receive', PurchasingReceiveForm::class)->name('purchasing.receive')->middleware('can:purchasing.view');
    Route::get('/purchasing/orders/{id}/convert-to-do', PurchasingConvertToDoForm::class)->name('purchasing.convert-to-do')->middleware('can:purchasing.view');
    Route::get('/purchasing/grn/{id}/receive', PurchasingGrnReceiveForm::class)->name('purchasing.grn.receive')->middleware('can:purchasing.view');
    Route::get('/purchasing/pdf/{type}/{id}', PurchaseDocumentPdfController::class)->name('purchasing.pdf')->middleware('can:purchasing.view');
    Route::get('/sales', SalesIndex::class)->name('sales.index')->middleware('can:sales.view');
    Route::get('/sales/create', SalesForm::class)->name('sales.create')->middleware('can:sales.view');
    Route::get('/sales/import', SalesImport::class)->name('sales.import')->middleware('can:sales.view');
    Route::get('/sales/{id}/edit', SalesForm::class)->name('sales.edit')->middleware('can:sales.view');
    Route::get('/inventory', InventoryIndex::class)->name('inventory.index')->middleware('can:inventory.view');
    Route::get('/inventory/stock-takes/create', StockTakeForm::class)->name('inventory.stock-takes.create')->middleware('can:inventory.view');
    Route::get('/inventory/stock-takes/{id}', StockTakeForm::class)->name('inventory.stock-takes.show')->middleware('can:inventory.view');
    Route::get('/inventory/wastage/create', WastageForm::class)->name('inventory.wastage.create')->middleware('can:inventory.view');
    Route::get('/inventory/wastage/{id}', WastageForm::class)->name('inventory.wastage.show')->middleware('can:inventory.view');
    Route::get('/inventory/staff-meals/create', StaffMealForm::class)->name('inventory.staff-meals.create')->middleware('can:inventory.view');
    Route::get('/inventory/staff-meals/{id}', StaffMealForm::class)->name('inventory.staff-meals.show')->middleware('can:inventory.view');
    Route::get('/inventory/prep-items/create', PrepItemForm::class)->name('inventory.prep-items.create')->middleware('can:inventory.view');
    Route::get('/inventory/prep-items/{id}', PrepItemForm::class)->name('inventory.prep-items.show')->middleware('can:inventory.view');
    Route::get('/inventory/transfers/create', TransferForm::class)->name('inventory.transfers.create')->middleware('can:inventory.view');
    Route::get('/inventory/transfers/{id}', TransferForm::class)->name('inventory.transfers.show')->middleware('can:inventory.view');
    Route::get('/reports', ReportsIndex::class)->name('reports.index')->middleware('can:reports.view');
    Route::get('/reports/price-history', ReportsPriceHistory::class)->name('reports.price-history')->middleware('can:reports.view');
    Route::get('/settings', SettingsIndex::class)->name('settings.index')->middleware('can:settings.view');
    Route::get('/settings/suppliers', SettingsSuppliers::class)->name('settings.suppliers')->middleware('can:settings.view');
    Route::get('/settings/categories', SettingsCategories::class)->name('settings.categories')->middleware('can:settings.view');
    Route::get('/settings/recipe-categories', SettingsRecipeCategories::class)->name('settings.recipe-categories')->middleware('can:settings.view');
    Route::get('/settings/cost-types', SettingsCostTypes::class)->name('settings.cost-types')->middleware('can:settings.view');
    Route::get('/settings/sales-categories', SettingsSalesCategories::class)->name('settings.sales-categories')->middleware('can:settings.view');
    Route::get('/settings/form-templates', SettingsFormTemplates::class)->name('settings.form-templates')->middleware('can:settings.view');
    Route::get('/settings/form-templates/{id}/edit', SettingsFormTemplateEdit::class)->name('settings.form-templates.edit')->middleware('can:settings.view');
    Route::get('/settings/outlets', SettingsOutlets::class)->name('settings.outlets')->middleware('can:settings.view');
    Route::get('/settings/api-keys', SettingsApiKeys::class)->name('settings.api-keys')->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class . ':Super Admin|System Admin');
    Route::get('/settings/users', SettingsUsers::class)->name('settings.users')->middleware('can:users.manage');
    Route::get('/settings/po-approvers', SettingsPoApprovers::class)->name('settings.po-approvers')->middleware('can:settings.view');
    Route::get('/settings/company-details', SettingsCompanyDetails::class)->name('settings.company-details')->middleware('can:settings.view');
    Route::get('/settings/calendar-events', SettingsCalendarEvents::class)->name('settings.calendar-events')->middleware('can:settings.view');
    Route::get('/settings/sales-targets', SettingsSalesTargets::class)->name('settings.sales-targets')->middleware('can:settings.view');
    Route::get('/settings/par-levels', SettingsParLevels::class)->name('settings.par-levels')->middleware('can:settings.view');
    Route::get('/analytics', AnalyticsIndex::class)->name('analytics.index')->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class . ':Super Admin|System Admin|Company Admin|Business Manager|Operations Manager');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__ . '/auth.php';
