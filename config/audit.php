<?php

use App\Models\CreditNote;
use App\Models\DeliveryOrder;
use App\Models\Employee;
use App\Models\GoodsReceivedNote;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientParLevel;
use App\Models\LabourCost;
use App\Models\OutletPrepRequest;
use App\Models\OutletTransfer;
use App\Models\OvertimeClaim;
use App\Models\ProcurementInvoice;
use App\Models\ProductionOrder;
use App\Models\ProductionRecipe;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRecord;
use App\Models\PurchaseRequest;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipePriceClass;
use App\Models\SalesCategory;
use App\Models\SalesClosure;
use App\Models\SalesRecord;
use App\Models\SalesTarget;
use App\Models\StaffMealRecord;
use App\Models\StockTake;
use App\Models\StockTransferOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WastageRecord;

return [

    /*
    |--------------------------------------------------------------------------
    | Audited models
    |--------------------------------------------------------------------------
    | Every model listed here is observed for created / updated / deleted /
    | restored / force-deleted events. Registration happens in
    | AppServiceProvider::boot() and is guarded by class_exists(), so a stray
    | entry can never fatal the app. Line-item child models are intentionally
    | omitted — they churn on every parent edit; header-level changes plus the
    | dedicated adjustment logs (OrderAdjustmentLog, IngredientPriceHistory)
    | already capture the meaningful line detail.
    */
    'models' => [
        // Procurement
        PurchaseRequest::class,
        PurchaseOrder::class,
        PurchaseRecord::class,
        DeliveryOrder::class,
        GoodsReceivedNote::class,
        ProcurementInvoice::class,
        CreditNote::class,

        // Inventory & stock
        Ingredient::class,
        IngredientCategory::class,
        IngredientParLevel::class,
        StockTake::class,
        StockTransferOrder::class,
        OutletTransfer::class,
        OutletPrepRequest::class,
        WastageRecord::class,

        // Recipes & menu
        Recipe::class,
        RecipeCategory::class,
        RecipePriceClass::class,

        // Sales
        SalesRecord::class,
        SalesCategory::class,
        SalesTarget::class,
        SalesClosure::class,

        // Production / central kitchen
        ProductionOrder::class,
        ProductionRecipe::class,

        // HR & labour
        OvertimeClaim::class,
        LabourCost::class,
        Employee::class,
        StaffMealRecord::class,

        // Master data (high-value: capability/role and supplier changes)
        Supplier::class,
        User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Globally excluded attributes
    |--------------------------------------------------------------------------
    | Never captured in old/new values for any model. Timestamps are noise;
    | secrets must never be written to the log.
    */
    'global_exclude' => [
        'created_at', 'updated_at', 'deleted_at',
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-model excluded attributes
    |--------------------------------------------------------------------------
    | Suppress high-churn, machine-maintained fields so the trail shows genuine
    | user intent rather than cascade side-effects. Keyed by model FQCN.
    */
    'model_exclude' => [
        // Cost fields are recalculated by PrepCostService/observers, not by users.
        Ingredient::class => ['current_cost'],
        Recipe::class     => ['cost_per_yield_unit'],
    ],
];
