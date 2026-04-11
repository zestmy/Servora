<?php

namespace App\Livewire\Settings;

use App\Models\CalendarEvent;
use App\Models\Department;
use App\Models\FormTemplate;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\RecipeCategory;
use App\Models\SalesCategory;
use App\Models\SalesTarget;
use App\Models\CostType;
use App\Models\CentralKitchen;
use App\Models\CentralPurchasingUnit;
use App\Models\PoApprover;
use App\Models\SupplierPriceAlert;
use App\Models\RecipePriceClass;
use App\Models\TaxRate;
use App\Models\Supplier;
use App\Models\LabourCost;
use App\Models\LmsUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $user = Auth::user();
        $isSystemLevel = $user->isSystemRole();
        $isBusinessLevel = $user->hasCapability('can_manage_users');
        $hasSettingsAccess = $user->hasPermissionTo('settings.view');

        $departmentCount     = Department::count();
        $costTypeCount       = CostType::count();
        $supplierCount       = Supplier::count();
        $categoryCount       = IngredientCategory::count();
        $recipeCategoryCount = RecipeCategory::count();
        $salesCategoryCount  = SalesCategory::count();
        $formTemplateCount   = FormTemplate::count();
        $poApproverCount     = PoApprover::count();
        $calendarEventCount  = CalendarEvent::count();
        $salesTargetCount    = SalesTarget::count();
        $labourCostCount     = LabourCost::count();
        $lmsUserCount        = $user->company_id
            ? LmsUser::where('company_id', $user->company_id)->count()
            : 0;
        $userCount           = $isSystemLevel
            ? User::count()
            : User::where('company_id', $user->company_id)->count();
        $outletCount         = $user->company_id
            ? Outlet::where('company_id', $user->company_id)->count()
            : 0;
        $cpuCount            = CentralPurchasingUnit::count();
        $kitchenCount        = CentralKitchen::count();
        $taxRateCount        = TaxRate::count();
        $priceAlertCount     = SupplierPriceAlert::count();
        $priceClassCount     = RecipePriceClass::count();

        return view('livewire.settings.index', compact(
            'isSystemLevel', 'isBusinessLevel', 'hasSettingsAccess',
            'departmentCount', 'costTypeCount', 'supplierCount', 'categoryCount', 'recipeCategoryCount',
            'salesCategoryCount', 'formTemplateCount', 'poApproverCount', 'calendarEventCount', 'salesTargetCount', 'labourCostCount', 'lmsUserCount', 'userCount', 'outletCount', 'cpuCount', 'kitchenCount', 'taxRateCount', 'priceAlertCount', 'priceClassCount'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Settings']);
    }
}
