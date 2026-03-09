<?php

namespace App\Livewire\Settings;

use App\Models\CalendarEvent;
use App\Models\FormTemplate;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\RecipeCategory;
use App\Models\SalesCategory;
use App\Models\SalesTarget;
use App\Models\CostType;
use App\Models\PoApprover;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $user = Auth::user();
        $isSystemLevel = $user->hasRole(['Super Admin', 'System Admin']);
        $isBusinessLevel = $user->hasRole('Business Manager');

        $costTypeCount       = CostType::count();
        $supplierCount       = Supplier::count();
        $categoryCount       = IngredientCategory::count();
        $recipeCategoryCount = RecipeCategory::count();
        $salesCategoryCount  = SalesCategory::count();
        $formTemplateCount   = FormTemplate::count();
        $poApproverCount     = PoApprover::count();
        $calendarEventCount  = CalendarEvent::count();
        $salesTargetCount    = SalesTarget::count();
        $userCount           = $isSystemLevel
            ? User::count()
            : User::where('company_id', $user->company_id)->count();
        $outletCount         = $user->company_id
            ? Outlet::where('company_id', $user->company_id)->count()
            : 0;

        return view('livewire.settings.index', compact(
            'isSystemLevel', 'isBusinessLevel',
            'costTypeCount', 'supplierCount', 'categoryCount', 'recipeCategoryCount',
            'salesCategoryCount', 'formTemplateCount', 'poApproverCount', 'calendarEventCount', 'salesTargetCount', 'userCount', 'outletCount'
        ))->layout('layouts.app', ['title' => 'Settings']);
    }
}
