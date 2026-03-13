<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\DeliveryOrder;
use App\Models\GoodsReceivedNote;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\PoApprover;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRecord;
use App\Models\Recipe;
use App\Models\SalesRecord;
use App\Models\StaffMealRecord;
use App\Models\StockTake;
use App\Models\User;
use App\Models\WastageRecord;
use App\Services\CostSummaryService;
use App\Traits\ScopesToActiveOutlet;
use Livewire\Component;

class Dashboard extends Component
{
    use ScopesToActiveOutlet;

    public function approvePo(int $id): void
    {
        $po = PurchaseOrder::findOrFail($id);
        if ($po->status !== 'submitted') return;
        if (! PoApprover::isApproverFor(auth()->id(), $po->outlet_id, $po->department_id)) return;

        $po->update(['status' => 'approved', 'approved_by' => auth()->id()]);
        session()->flash('success', 'PO approved and sent to purchasing team.');
    }

    public function rejectPo(int $id): void
    {
        $po = PurchaseOrder::findOrFail($id);
        if ($po->status !== 'submitted') return;
        if (! PoApprover::isApproverFor(auth()->id(), $po->outlet_id, $po->department_id)) return;

        $po->update(['status' => 'cancelled', 'approved_by' => auth()->id()]);
        session()->flash('success', 'PO has been rejected.');
    }

    public function render()
    {
        $user = auth()->user();
        $roleNames = $user->getRoleNames()->toArray();
        // Prefer business role name for display
        $rolePriority = ['Business Manager', 'Super Admin', 'System Admin', 'Purchasing', 'Finance', 'Operations Manager', 'Chef', 'Branch Manager'];
        $roleName = collect($rolePriority)->first(fn ($r) => in_array($r, $roleNames)) ?? ($roleNames[0] ?? '');

        // Check if user has PO approver appointments (any role)
        $approverOutletIds = PoApprover::approverOutletIds($user->id);
        $isAppointed = count($approverOutletIds) > 0;

        $data = match (true) {
            $user->hasRole('Business Manager')                   => $this->businessManagerDashboard($user),
            $user->hasRole(['Super Admin', 'System Admin'])      => $this->systemDashboard($user),
            $user->hasRole('Purchasing')                         => $this->purchasingDashboard($user),
            $user->hasRole('Finance')                            => $this->financeDashboard($user),
            $user->hasRole('Chef') && ! $isAppointed             => $this->chefDashboard($user),
            default                                              => $isAppointed
                                                                    ? $this->appointedDashboard($user, $approverOutletIds)
                                                                    : $this->managerDashboard($user),
        };

        $data['roleName'] = $roleName;
        $data['dashboardType'] = $data['dashboardType'] ?? 'default';

        return view('livewire.dashboard', $data)
            ->layout('layouts.app', ['title' => 'Dashboard']);
    }

    // ── System Admin / Super Admin ─────────────────────────────────────────

    private function systemDashboard($user): array
    {
        $totalCompanies = Company::count();
        $totalUsers     = User::count();
        $totalOutlets   = Outlet::count();
        $activeUsers    = User::whereNotNull('email_verified_at')->count();

        return [
            'dashboardType' => 'system',
            'stats' => [
                ['label' => 'Companies',    'value' => $totalCompanies, 'icon' => 'building'],
                ['label' => 'Total Users',  'value' => $totalUsers,     'icon' => 'users'],
                ['label' => 'Active Users', 'value' => $activeUsers,    'icon' => 'check'],
                ['label' => 'Outlets',      'value' => $totalOutlets,   'icon' => 'location'],
            ],
        ];
    }

    // ── Business Manager ───────────────────────────────────────────────────

    private function businessManagerDashboard($user): array
    {
        $now = now();
        $outletId = $this->activeOutletId();

        $totalIngredients = Ingredient::where('is_active', true)->count();
        $activeRecipes    = Recipe::where('is_active', true)->where('is_prep', false)->count();

        $poQ = PurchaseOrder::whereIn('status', ['draft', 'submitted']);
        $this->scopeByOutlet($poQ);
        $pendingPOs = $poQ->count();

        $todayQ = SalesRecord::whereDate('sale_date', today());
        $this->scopeByOutlet($todayQ);
        $todayRevenue = $todayQ->sum('total_revenue');

        $monthRevQ = SalesRecord::whereMonth('sale_date', $now->month)->whereYear('sale_date', $now->year);
        $this->scopeByOutlet($monthRevQ);
        $monthRevenue = $monthRevQ->sum('total_revenue');

        $monthPurQ = PurchaseRecord::whereMonth('purchase_date', $now->month)->whereYear('purchase_date', $now->year);
        $this->scopeByOutlet($monthPurQ);
        $monthPurchases = $monthPurQ->sum('total_amount');

        $wastageQ = WastageRecord::whereMonth('wastage_date', $now->month)->whereYear('wastage_date', $now->year);
        $this->scopeByOutlet($wastageQ);
        $monthWastage = $wastageQ->sum('total_cost');

        $staffMealQ = StaffMealRecord::whereMonth('meal_date', $now->month)->whereYear('meal_date', $now->year);
        $this->scopeByOutlet($staffMealQ);
        $monthStaffMeals = $staffMealQ->sum('total_cost');

        $service = new CostSummaryService();
        $costSummary = $service->generate($now->format('Y-m'), $outletId);

        $trendMonths = $this->buildTrend($now, 6);

        $alerts = $this->buildAlerts($now, $pendingPOs);

        return [
            'dashboardType'    => 'business',
            'totalIngredients' => $totalIngredients,
            'activeRecipes'    => $activeRecipes,
            'pendingPOs'       => $pendingPOs,
            'todayRevenue'     => $todayRevenue,
            'monthRevenue'     => $monthRevenue,
            'monthPurchases'   => $monthPurchases,
            'monthWastage'     => $monthWastage,
            'monthStaffMeals'  => $monthStaffMeals,
            'costSummary'      => $costSummary,
            'trendMonths'      => $trendMonths,
            'alerts'           => $alerts,
        ];
    }

    // ── Appointed Approver (any role with PO approval assignments) ───────

    private function appointedDashboard($user, array $approverOutletIds): array
    {
        $now = now();

        // PO approval counts scoped to assigned outlets + departments
        $awaitingQ = PurchaseOrder::where('status', 'submitted');
        PoApprover::scopeApprovablePos($awaitingQ, $user->id);
        $awaitingApproval = $awaitingQ->count();

        $approvedPOs      = PurchaseOrder::where('status', 'approved')
            ->whereIn('outlet_id', $approverOutletIds)->count();
        $sentPOs          = PurchaseOrder::where('status', 'sent')
            ->whereIn('outlet_id', $approverOutletIds)->count();
        $pendingGrns      = GoodsReceivedNote::where('status', 'pending')
            ->whereIn('outlet_id', $approverOutletIds)->count();

        // Operational stats
        $totalIngredients = Ingredient::where('is_active', true)->count();
        $activeRecipes    = Recipe::where('is_active', true)->where('is_prep', false)->count();

        $todayQ = SalesRecord::whereDate('sale_date', today());
        $this->scopeByOutlet($todayQ);
        $todayRevenue = $todayQ->sum('total_revenue');

        $monthRevQ = SalesRecord::whereMonth('sale_date', $now->month)->whereYear('sale_date', $now->year);
        $this->scopeByOutlet($monthRevQ);
        $monthRevenue = $monthRevQ->sum('total_revenue');

        $monthPurQ = PurchaseRecord::whereMonth('purchase_date', $now->month)->whereYear('purchase_date', $now->year);
        $this->scopeByOutlet($monthPurQ);
        $monthPurchases = $monthPurQ->sum('total_amount');

        // Recent submitted POs for quick approval — scoped to assigned outlets + departments
        $recentPosQ = PurchaseOrder::with(['supplier', 'outlet'])
            ->where('status', 'submitted');
        PoApprover::scopeApprovablePos($recentPosQ, $user->id);
        $recentSubmittedPOs = $recentPosQ->orderByDesc('created_at')->limit(5)->get();

        $trendMonths = $this->buildTrend($now, 6);

        $alerts = [];
        if ($awaitingApproval > 0) {
            $alerts[] = ['type' => 'warning', 'message' => "{$awaitingApproval} PO(s) awaiting your approval"];
        }
        if ($pendingGrns > 0) {
            $alerts[] = ['type' => 'info', 'message' => "{$pendingGrns} GRN(s) pending outlet receipt"];
        }

        $wastageQ = WastageRecord::whereMonth('wastage_date', $now->month)->whereYear('wastage_date', $now->year);
        $this->scopeByOutlet($wastageQ);
        $monthWastage = $wastageQ->sum('total_cost');

        // Outlet names the user approves for
        $approverOutletNames = Outlet::whereIn('id', $approverOutletIds)->pluck('name')->toArray();

        return [
            'dashboardType'        => 'operations',
            'awaitingApproval'     => $awaitingApproval,
            'approvedPOs'          => $approvedPOs,
            'sentPOs'              => $sentPOs,
            'pendingGrns'          => $pendingGrns,
            'totalIngredients'     => $totalIngredients,
            'activeRecipes'        => $activeRecipes,
            'todayRevenue'         => $todayRevenue,
            'monthRevenue'         => $monthRevenue,
            'monthPurchases'       => $monthPurchases,
            'monthWastage'         => $monthWastage,
            'recentSubmittedPOs'   => $recentSubmittedPOs,
            'trendMonths'          => $trendMonths,
            'alerts'               => $alerts,
            'approverOutletNames'  => $approverOutletNames,
        ];
    }

    // ── Manager ────────────────────────────────────────────────────────────

    private function managerDashboard($user): array
    {
        $now = now();

        $todayQ = SalesRecord::whereDate('sale_date', today());
        $this->scopeByOutlet($todayQ);
        $todayRevenue = $todayQ->sum('total_revenue');
        $todayPax = (clone $todayQ)->sum('pax');

        $monthRevQ = SalesRecord::whereMonth('sale_date', $now->month)->whereYear('sale_date', $now->year);
        $this->scopeByOutlet($monthRevQ);
        $monthRevenue = $monthRevQ->sum('total_revenue');

        $poQ = PurchaseOrder::whereIn('status', ['draft', 'submitted']);
        $this->scopeByOutlet($poQ);
        $pendingPOs = $poQ->count();

        $grnQ = GoodsReceivedNote::where('status', 'pending');
        $this->scopeByOutlet($grnQ);
        $pendingGrns = $grnQ->count();

        $totalIngredients = Ingredient::where('is_active', true)->count();

        $trendMonths = $this->buildTrend($now, 6);
        $alerts = $this->buildAlerts($now, $pendingPOs);

        return [
            'dashboardType'    => 'manager',
            'stats' => [
                ['label' => 'Today Revenue',  'value' => number_format($todayRevenue, 0), 'color' => 'indigo'],
                ['label' => 'Today Pax',      'value' => $todayPax,                       'color' => 'blue'],
                ['label' => 'Month Revenue',   'value' => number_format($monthRevenue, 0), 'color' => 'green'],
                ['label' => 'Pending POs',     'value' => $pendingPOs,                     'color' => $pendingPOs > 0 ? 'amber' : 'gray'],
                ['label' => 'GRN to Receive',  'value' => $pendingGrns,                    'color' => $pendingGrns > 0 ? 'green' : 'gray'],
                ['label' => 'Ingredients',     'value' => $totalIngredients,                'color' => 'gray'],
            ],
            'trendMonths' => $trendMonths,
            'alerts'      => $alerts,
        ];
    }

    // ── Chef ───────────────────────────────────────────────────────────────

    private function chefDashboard($user): array
    {
        $now = now();

        $activeRecipes = Recipe::where('is_active', true)->where('is_prep', false)->count();
        $prepRecipes   = Recipe::where('is_active', true)->where('is_prep', true)->count();
        $totalIngredients = Ingredient::where('is_active', true)->count();

        $grnQ = GoodsReceivedNote::where('status', 'pending');
        $this->scopeByOutlet($grnQ);
        $pendingGrns = $grnQ->count();

        // Recent stock take
        $stQ = StockTake::where('status', 'completed')->orderByDesc('stock_take_date');
        $this->scopeByOutlet($stQ);
        $lastStockTake = $stQ->first();

        // Over-cost recipes
        $overCostRecipes = Recipe::where('is_active', true)
            ->where('is_prep', false)
            ->where('selling_price', '>', 0)
            ->get()
            ->filter(function ($r) {
                $totalCost = $r->lines->sum(fn ($l) => $l->cost_per_recipe_uom * $l->quantity);
                $yield = max(floatval($r->yield_quantity), 0.0001);
                $costPerUnit = $totalCost / $yield;
                return ($costPerUnit / (float) $r->selling_price) * 100 > 35;
            })->count();

        $alerts = [];
        if ($overCostRecipes > 0) {
            $alerts[] = ['type' => 'warning', 'message' => "{$overCostRecipes} recipe(s) exceed 35% food cost target"];
        }
        if ($pendingGrns > 0) {
            $alerts[] = ['type' => 'info', 'message' => "{$pendingGrns} GRN(s) pending receipt"];
        }

        return [
            'dashboardType' => 'chef',
            'stats' => [
                ['label' => 'Active Recipes',  'value' => $activeRecipes,    'color' => 'indigo'],
                ['label' => 'Prep Recipes',     'value' => $prepRecipes,      'color' => 'purple'],
                ['label' => 'Ingredients',      'value' => $totalIngredients, 'color' => 'gray'],
                ['label' => 'GRN to Receive',   'value' => $pendingGrns,      'color' => $pendingGrns > 0 ? 'green' : 'gray'],
            ],
            'lastStockTake'    => $lastStockTake,
            'overCostRecipes'  => $overCostRecipes,
            'alerts'           => $alerts,
        ];
    }

    // ── Purchasing ─────────────────────────────────────────────────────────

    private function purchasingDashboard($user): array
    {
        $now = now();

        $submittedPOs    = PurchaseOrder::where('status', 'submitted')->count();
        $approvedPOs     = PurchaseOrder::where('status', 'approved')->count();
        $pendingDOs      = DeliveryOrder::where('status', 'pending')->count();
        $pendingGRNs     = GoodsReceivedNote::where('status', 'pending')->count();

        $monthPurQ = PurchaseRecord::whereMonth('purchase_date', $now->month)->whereYear('purchase_date', $now->year);
        $monthSpend = $monthPurQ->sum('total_amount');

        $todayDOs = DeliveryOrder::whereDate('delivery_date', today())->count();

        $alerts = [];
        if ($submittedPOs > 0) {
            $alerts[] = ['type' => 'info', 'message' => "{$submittedPOs} PO(s) awaiting your review"];
        }
        if ($pendingGRNs > 0) {
            $alerts[] = ['type' => 'warning', 'message' => "{$pendingGRNs} GRN(s) pending outlet receipt"];
        }

        return [
            'dashboardType' => 'purchasing',
            'stats' => [
                ['label' => 'Awaiting Review',   'value' => $submittedPOs,               'color' => $submittedPOs > 0 ? 'indigo' : 'gray'],
                ['label' => 'Approved POs',       'value' => $approvedPOs,                'color' => 'purple'],
                ['label' => 'Pending Delivery',   'value' => $pendingDOs,                 'color' => $pendingDOs > 0 ? 'yellow' : 'gray'],
                ['label' => 'Pending Receipt',    'value' => $pendingGRNs,                'color' => $pendingGRNs > 0 ? 'amber' : 'gray'],
                ['label' => 'Today Deliveries',   'value' => $todayDOs,                   'color' => 'blue'],
                ['label' => 'Month Spend',        'value' => number_format($monthSpend, 0), 'color' => 'red'],
            ],
            'alerts' => $alerts,
        ];
    }

    // ── Finance ────────────────────────────────────────────────────────────

    private function financeDashboard($user): array
    {
        $now = now();
        $outletId = $this->activeOutletId();

        $todayQ = SalesRecord::whereDate('sale_date', today());
        $this->scopeByOutlet($todayQ);
        $todayRevenue = $todayQ->sum('total_revenue');

        $monthRevQ = SalesRecord::whereMonth('sale_date', $now->month)->whereYear('sale_date', $now->year);
        $this->scopeByOutlet($monthRevQ);
        $monthRevenue = $monthRevQ->sum('total_revenue');

        $monthPurQ = PurchaseRecord::whereMonth('purchase_date', $now->month)->whereYear('purchase_date', $now->year);
        $this->scopeByOutlet($monthPurQ);
        $monthPurchases = $monthPurQ->sum('total_amount');

        $monthCostQ = SalesRecord::whereMonth('sale_date', $now->month)->whereYear('sale_date', $now->year);
        $this->scopeByOutlet($monthCostQ);
        $monthCost = $monthCostQ->sum('total_cost');

        $grossProfit = $monthRevenue - $monthCost;
        $grossMargin = $monthRevenue > 0 ? round(($grossProfit / $monthRevenue) * 100, 1) : 0;

        $wastageQ = WastageRecord::whereMonth('wastage_date', $now->month)->whereYear('wastage_date', $now->year);
        $this->scopeByOutlet($wastageQ);
        $monthWastage = $wastageQ->sum('total_cost');

        $staffMealQ = StaffMealRecord::whereMonth('meal_date', $now->month)->whereYear('meal_date', $now->year);
        $this->scopeByOutlet($staffMealQ);
        $monthStaffMeals = $staffMealQ->sum('total_cost');

        $service = new CostSummaryService();
        $costSummary = $service->generate($now->format('Y-m'), $outletId);

        $trendMonths = $this->buildTrend($now, 6);

        return [
            'dashboardType'  => 'finance',
            'stats' => [
                ['label' => 'Today Revenue',   'value' => number_format($todayRevenue, 0),   'color' => 'indigo'],
                ['label' => 'Month Revenue',    'value' => number_format($monthRevenue, 0),   'color' => 'green'],
                ['label' => 'Month Purchases',  'value' => number_format($monthPurchases, 0), 'color' => 'red'],
                ['label' => 'Gross Profit',     'value' => number_format($grossProfit, 0),    'color' => $grossProfit >= 0 ? 'green' : 'red'],
                ['label' => 'Gross Margin',     'value' => $grossMargin . '%',                'color' => $grossMargin > 30 ? 'green' : 'amber'],
                ['label' => 'Cost of Sales',    'value' => number_format($monthCost, 0),      'color' => 'gray'],
                ['label' => 'Wastage',          'value' => number_format($monthWastage, 0),   'color' => 'orange'],
                ['label' => 'Staff Meals',      'value' => number_format($monthStaffMeals, 0), 'color' => 'purple'],
            ],
            'costSummary' => $costSummary,
            'trendMonths' => $trendMonths,
        ];
    }

    // ── Shared Helpers ────────────────────────────────────────────────────

    private function buildTrend($now, int $months): array
    {
        $trendMonths = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $revQ = SalesRecord::whereMonth('sale_date', $month->month)->whereYear('sale_date', $month->year);
            $this->scopeByOutlet($revQ);
            $mRev = $revQ->sum('total_revenue');

            $purQ = PurchaseRecord::whereMonth('purchase_date', $month->month)->whereYear('purchase_date', $month->year);
            $this->scopeByOutlet($purQ);
            $mPur = $purQ->sum('total_amount');

            $trendMonths[] = [
                'label'     => $month->format('M'),
                'revenue'   => round((float) $mRev, 2),
                'purchases' => round((float) $mPur, 2),
            ];
        }
        return $trendMonths;
    }

    private function buildAlerts($now, int $pendingPOs): array
    {
        $alerts = [];

        $overCostRecipes = Recipe::where('is_active', true)
            ->where('is_prep', false)
            ->where('selling_price', '>', 0)
            ->get()
            ->filter(function ($r) {
                $totalCost = $r->lines->sum(fn ($l) => $l->cost_per_recipe_uom * $l->quantity);
                $yield = max(floatval($r->yield_quantity), 0.0001);
                $costPerUnit = $totalCost / $yield;
                return ($costPerUnit / (float) $r->selling_price) * 100 > 35;
            })->count();

        if ($overCostRecipes > 0) {
            $alerts[] = ['type' => 'warning', 'message' => "{$overCostRecipes} recipe(s) exceed 35% food cost target"];
        }
        if ($pendingPOs > 0) {
            $alerts[] = ['type' => 'info', 'message' => "{$pendingPOs} purchase order(s) pending"];
        }

        $stCheckQ = StockTake::where('status', 'completed')
            ->whereMonth('stock_take_date', $now->month)
            ->whereYear('stock_take_date', $now->year);
        $this->scopeByOutlet($stCheckQ);
        if (!$stCheckQ->exists() && $now->day >= 25) {
            $alerts[] = ['type' => 'alert', 'message' => 'Monthly stock take not yet completed'];
        }

        return $alerts;
    }
}
