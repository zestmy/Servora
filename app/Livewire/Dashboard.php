<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\DeliveryOrder;
use App\Models\GoodsReceivedNote;
use App\Models\Ingredient;
use App\Models\LabelPrint;
use App\Models\Outlet;
use App\Models\PoApprover;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRecord;
use App\Models\Recipe;
use App\Models\SalesRecord;
use App\Models\StaffMealRecord;
use App\Models\StockTake;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WastageRecord;
use App\Services\CostSummaryService;
use App\Services\RecipeCostService;
use App\Support\DashboardPeriod;
use App\Traits\ScopesToActiveOutlet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The dashboard, in seven role-specific arrangements.
 *
 * The reporting layer here had one structural gap that ran through all seven:
 * every figure was an absolute with nothing to compare it against, and the
 * window was hardwired to the current calendar month by `$now = now()` at the
 * top of each builder. Forty-four tiles, no baselines, no time control.
 *
 * So the shape of this file changed in three ways:
 *
 *   1. A DashboardPeriod resolves the window and the window before it, once,
 *      and every builder reads both from it. `$this->window` replaces `$now`.
 *
 *   2. Figures are returned as ['value' => x, 'prior' => y] pairs through
 *      metric(), so a tile can render a delta without each builder working
 *      out its own comparison and getting a different answer.
 *
 *   3. The over-cost recipe scan — which hydrated every recipe in the company
 *      on every page load, twice over, to produce one integer — moved to
 *      RecipeCostService behind a cache.
 *
 * Trend building also went from twelve queries to three: it was querying each
 * month separately in a loop, which is a pattern that only looks cheap.
 */
class Dashboard extends Component
{
    use ScopesToActiveOutlet;

    /** Optional single-outlet filter for multi-outlet users. Empty = all accessible outlets. */
    #[Url]
    public string $outletFilter = '';

    /**
     * The reporting window. Named ranges only — the meanings come from
     * DashboardPeriod, which narrows HasQuickDateRanges' vocabulary to the
     * four that make sense for a standing view.
     */
    #[Url]
    public string $period = 'this_month';

    private DashboardPeriod $window;

    /**
     * Outlet id to feed the cost-summary service: the selected outlet when one
     * is picked, otherwise company-wide (null) for users who can see all
     * outlets, else their active outlet so restricted users never over-show.
     */
    private function costSummaryOutletId(): ?int
    {
        $selected = $this->selectedOutletId($this->outletFilter);
        if ($selected !== null) {
            return $selected;
        }

        return auth()->user()->canViewAllOutlets() ? null : $this->activeOutletId();
    }

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
        $this->window = DashboardPeriod::make($this->period);

        $user = auth()->user();
        $roleName = $user->designation ?: ($user->getRoleNames()->first() ?? '');

        // Check if user has PO approver appointments (any role)
        $approverOutletIds = PoApprover::approverOutletIds($user->id);
        $isAppointed = count($approverOutletIds) > 0;

        // isSystemRole must be checked FIRST: canDo() returns true for system
        // roles, so the users.manage arm would otherwise shadow the platform
        // dashboard and send system admins to the business one.
        $data = match (true) {
            $user->isSystemRole()                                                                      => $this->systemDashboard($user),
            $user->canDo('users.manage')                                                               => $this->businessManagerDashboard($user),
            $user->can('purchasing.view') && ! $user->can('sales.view')        => $this->purchasingDashboard($user),
            $user->can('reports.view') && ! $user->can('purchasing.view')      => $this->financeDashboard($user),
            $user->can('recipes.view') && ! $user->can('sales.view') && ! $isAppointed => $this->chefDashboard($user),
            default                                                                                    => $isAppointed
                                                                                                          ? $this->appointedDashboard($user, $approverOutletIds)
                                                                                                          : $this->managerDashboard($user),
        };

        $data['roleName']      = $roleName;
        $data['dashboardType'] = $data['dashboardType'] ?? 'default';
        $data['filterOutlets'] = $this->filterableOutlets();
        $data['outletFilter']  = $this->outletFilter;
        $data['greeting']      = $this->greeting($user);
        $data['periodOptions'] = DashboardPeriod::options();
        $data['periodCaption'] = $this->window->caption();
        $data['generatedAt']   = now()->format('H:i');

        return view('livewire.dashboard', $data)
            ->layout('layouts.app', ['title' => 'Dashboard']);
    }

    // ── Shared query helpers ───────────────────────────────────────────────

    /**
     * One outlet-scoped SUM over a date range.
     *
     * Every builder wrote this by hand — build the query, apply the outlet
     * filter, sum the column — five or six times each, with the month and year
     * clauses spelled out inline. Forty-odd near-identical blocks was forty-odd
     * chances for one of them to scope differently from the rest.
     */
    private function sumBetween(string $model, string $dateColumn, string $sumColumn, $from, $to): float
    {
        $query = $model::whereBetween($dateColumn, [$from->toDateString(), $to->toDateString()]);
        $this->scopeByOutletFilter($query, $this->outletFilter);

        return (float) $query->sum($sumColumn);
    }

    /**
     * The same figure for the reporting window and the one before it.
     *
     * @return array{value: float, prior: float}
     */
    private function metric(string $model, string $dateColumn, string $sumColumn): array
    {
        return [
            'value' => $this->sumBetween($model, $dateColumn, $sumColumn, $this->window->start, $this->window->end),
            'prior' => $this->sumBetween($model, $dateColumn, $sumColumn, $this->window->priorStart, $this->window->priorEnd),
        ];
    }

    /** An outlet-scoped COUNT with an optional extra constraint. */
    private function scopedCount(string $model, ?callable $modify = null): int
    {
        $query = $model::query();
        if ($modify) {
            $modify($query);
        }
        $this->scopeByOutletFilter($query, $this->outletFilter);

        return $query->count();
    }

    /**
     * How many whole days the oldest row of a queue has been sitting there.
     *
     * A queue depth on its own does not say whether it is a queue or a
     * backlog: four POs awaiting review is unremarkable if they arrived this
     * morning and a problem if the oldest is nine days old, and the dashboard
     * rendered those two situations identically.
     */
    private function oldestWaitDays(string $model, ?callable $modify = null): ?int
    {
        $query = $model::query();
        if ($modify) {
            $modify($query);
        }
        $this->scopeByOutletFilter($query, $this->outletFilter);

        $oldest = $query->min('created_at');

        return $oldest ? (int) CarbonImmutable::parse($oldest)->diffInDays(now()) : null;
    }

    /**
     * A monthly series in ONE grouped query.
     *
     * buildTrend used to loop six months and fire two queries per iteration —
     * twelve round trips for a chart with twelve bars, plus another twelve if
     * a second dashboard section wanted the same numbers. Grouping in SQL
     * turns each series into a single query and makes adding a third series
     * cost one more rather than six.
     *
     * @return array<string, float> keyed 'Y-m'
     */
    private function monthlySeries(string $model, string $dateColumn, string $sumColumn, $from, $to): array
    {
        $query = $model::query()
            ->selectRaw("{$dateColumn} as bucket, SUM({$sumColumn}) as total")
            ->whereBetween($dateColumn, [$from->toDateString(), $to->toDateString()])
            ->groupBy($dateColumn);

        $this->scopeByOutletFilter($query, $this->outletFilter);

        /*
         * Grouped by DAY in SQL and rolled up to months here, rather than by
         * DATE_FORMAT(col, '%Y-%m') in the query.
         *
         * DATE_FORMAT and YEAR()/MONTH() are MySQL-only, and they are the
         * house pattern — Reports, OrderSummary, PoSummary and EaForm all use
         * them. They are also why none of those screens can be tested: the
         * suite runs on SQLite, where the query dies with "no such function".
         * This is the most-visited page in the product and it is getting its
         * first test coverage, so it does not inherit that.
         *
         * The cost of the change is a few hundred rows instead of six — six
         * months of daily totals for one outlet — which is nothing next to the
         * twelve separate round trips this replaced.
         */
        $totals = [];

        foreach ($query->get() as $row) {
            $key = CarbonImmutable::parse($row->bucket)->format('Y-m');
            $totals[$key] = ($totals[$key] ?? 0.0) + (float) $row->total;
        }

        return $totals;
    }

    /**
     * Revenue, cost of sales and purchases by month, ending with the
     * reporting window's own month.
     *
     * Purchases stays in the returned rows because the purchasing dashboard
     * plots it as procurement cashflow, which is what it actually measures.
     * What changed is that it is no longer the default second series on a
     * chart captioned as though the gap between the bars were profit.
     */
    private function buildTrend(int $months = 6): array
    {
        $last  = $this->window->start->startOfMonth();
        $first = $last->subMonths($months - 1);
        $to    = $last->endOfMonth();

        $revenue   = $this->monthlySeries(SalesRecord::class,    'sale_date',     'total_revenue', $first, $to);
        $cogs      = $this->monthlySeries(SalesRecord::class,    'sale_date',     'total_cost',    $first, $to);
        $purchases = $this->monthlySeries(PurchaseRecord::class, 'purchase_date', 'total_amount',  $first, $to);

        $rows = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $first->addMonths($i);
            $key   = $month->format('Y-m');

            $rows[] = [
                'label'     => $month->format('M'),
                'revenue'   => round($revenue[$key]   ?? 0, 2),
                'cogs'      => round($cogs[$key]      ?? 0, 2),
                'purchases' => round($purchases[$key] ?? 0, 2),
            ];
        }

        return $rows;
    }

    /** Pull one series out of the trend rows, for a tile sparkline. */
    private function spark(array $trend, string $key): array
    {
        return array_column($trend, $key);
    }

    /**
     * What a normal day looks like, for the day of the week today happens to
     * be.
     *
     * Comparing today against yesterday, or against the month's daily average,
     * makes every Monday look like a disaster and every Saturday like a
     * triumph. Four same-weekday occurrences is short enough to track a
     * changing business and long enough not to swing on one wet Tuesday.
     *
     * @return array{revenue: float, pax: float}
     */
    private function sameWeekdayAverage(int $weeks = 4): array
    {
        $dates = collect(range(1, $weeks))
            ->map(fn ($i) => CarbonImmutable::today()->subWeeks($i)->toDateString())
            ->all();

        $query = SalesRecord::query()
            ->selectRaw('COALESCE(SUM(total_revenue), 0) as rev, COALESCE(SUM(pax), 0) as pax')
            ->whereIn('sale_date', $dates);

        $this->scopeByOutletFilter($query, $this->outletFilter);

        $row = $query->first();

        return [
            'revenue' => (float) ($row->rev ?? 0) / $weeks,
            'pax'     => (float) ($row->pax ?? 0) / $weeks,
        ];
    }

    /** Today's takings and covers, in one query. */
    private function todayFigures(): array
    {
        $query = SalesRecord::query()
            ->selectRaw('COALESCE(SUM(total_revenue), 0) as rev, COALESCE(SUM(pax), 0) as pax')
            ->whereDate('sale_date', today());

        $this->scopeByOutletFilter($query, $this->outletFilter);

        $row = $query->first();

        return [
            'revenue' => (float) ($row->rev ?? 0),
            'pax'     => (int) ($row->pax ?? 0),
        ];
    }

    /** The cost summary for a window, monthly-costed where the window allows. */
    private function costSummaryFor(DashboardPeriod $window, ?int $outletId): array
    {
        [$start, $end] = $window->costRange();

        return (new CostSummaryService())->generate($window->costPeriod(), $outletId, $start, $end);
    }

    private function greeting($user): string
    {
        $hour = (int) now()->format('G');

        $part = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default    => 'Good evening',
        };

        /*
         * The WHOLE name, not the first word of it.
         *
         * Malaysian names are not given-name-first. Cutting at the first space
         * turns "MOHD AFFANDY BIN ZULKARNAIN" into "MOHD" and "NURIN QASRINA
         * BINTI MOHD FAIZAL" into "NURIN" — for many staff here the first word
         * is an honorific or a shared element, so the friendly shortening
         * greets people by a name that is not theirs.
         */
        $name = trim((string) $user->name);

        return $name !== '' ? "{$part}, {$name}" : $part;
    }

    /** The delta label every tile on the page shares, so they cannot disagree. */
    private function compareLabel(): string
    {
        return $this->window->priorLabel;
    }

    // ── System Admin / Super Admin ─────────────────────────────────────────

    private function systemDashboard($user): array
    {
        $now = now();

        $subCounts = Subscription::selectRaw('status, COUNT(*) c')
            ->groupBy('status')->pluck('c', 'status');

        // Platform-wide activity: users.last_active_at (session-driver
        // independent), with the DB sessions table as legacy fallback.
        // The or-pair is grouped in its own closure so that any constraint
        // ever added to this query — a scope, a where — cannot silently
        // change what the OR binds to.
        $activeSince = function ($since) {
            return User::where(function ($q) use ($since) {
                $q->where('last_active_at', '>=', $since)
                    ->orWhereIn('id', DB::table('sessions')
                        ->whereNotNull('user_id')
                        ->where('last_activity', '>=', $since->timestamp)
                        ->select('user_id'));
            })->count();
        };
        $active24h = $activeSince($now->copy()->subDay());
        $active7d  = $activeSince($now->copy()->subDays(7));

        $newThisMonth = Company::where('created_at', '>=', $now->copy()->subDays(30))->count();
        $newPrior     = Company::whereBetween('created_at', [
            $now->copy()->subDays(60), $now->copy()->subDays(30),
        ])->count();

        $trialsEndingSoon = Subscription::where('status', Subscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$now, $now->copy()->addDays(7)])
            ->count();

        $pastDue = (int) ($subCounts['past_due'] ?? 0);

        /*
         * These were hand-rolled tiles — `bg-white rounded-xl shadow-sm p-4
         * border border-gray-100` — the only ones in the product not going
         * through the shared partial, and off both the shape lock and the
         * elevation scale. They route through stat-cards now, which also means
         * they get deltas like everybody else.
         *
         * Eight counts with no growth rate between them told a platform admin
         * how big the platform is, which is not a daily question. The four
         * that move are promoted; the rest are a secondary group.
         */
        $stats = [
            ['label' => 'Companies', 'value' => number_format(Company::count()),
             'current' => $newThisMonth, 'prior' => $newPrior, 'compare' => 'new, vs prior 30 days',
             'sub' => Company::where('is_active', true)->count() . ' active'],

            ['label' => 'Active subscriptions', 'value' => number_format((int) ($subCounts['active'] ?? 0)),
             'sub' => $pastDue > 0 ? $pastDue . ' past due' : 'none past due',
             'color' => $pastDue > 0 ? 'amber' : null],

            ['label' => 'Trials', 'value' => number_format((int) ($subCounts['trialing'] ?? 0)),
             'sub' => $trialsEndingSoon > 0 ? $trialsEndingSoon . ' ending within 7 days' : 'none ending this week',
             'color' => $trialsEndingSoon > 0 ? 'amber' : null],

            ['label' => 'Active today', 'value' => number_format($active24h),
             'current' => $active24h, 'prior' => $active7d / 7, 'compare' => 'vs daily average this week',
             'sub' => $active7d . ' this week'],
        ];

        $secondaryStats = [
            ['label' => 'Users',      'value' => number_format(User::count()),
             'sub' => User::has('companies', '>=', 2)->count() . ' multi-company'],
            ['label' => 'Outlets',    'value' => number_format(Outlet::count())],
            ['label' => 'Unverified', 'value' => number_format(User::whereNull('email_verified_at')->count()),
             'sub' => 'user accounts'],
        ];

        $quickLinks = [
            ['route' => 'admin.users',               'label' => 'All Users',      'desc' => 'Cross-company user directory'],
            ['route' => 'admin.companies',           'label' => 'Companies',      'desc' => 'Suspend, reactivate or delete'],
            ['route' => 'admin.role-templates',      'label' => 'Role Templates', 'desc' => 'Access template per role'],
            ['route' => 'admin.company-health',      'label' => 'Company Health', 'desc' => 'Engagement & at-risk accounts'],
            ['route' => 'admin.subscriptions.index', 'label' => 'Subscriptions',  'desc' => 'Manage company subscriptions'],
            ['route' => 'admin.plans.index',         'label' => 'Plans',          'desc' => 'Pricing plans & features'],
            ['route' => 'admin.trials.index',        'label' => 'Trials',         'desc' => 'Trial pipeline & conversions'],
            ['route' => 'admin.coupons',             'label' => 'Coupons',        'desc' => 'Discount & lifetime codes'],
            ['route' => 'admin.referrals.index',     'label' => 'Referrals',      'desc' => 'Referral programs & payouts'],
            ['route' => 'admin.announcements',       'label' => 'Announcements',  'desc' => 'Broadcast to all companies'],
            ['route' => 'admin.pages',               'label' => 'Pages',          'desc' => 'Public site content'],
            ['route' => 'settings.api-keys',         'label' => 'API Keys',       'desc' => 'System integrations'],
        ];

        $alerts = [];
        if ($pastDue > 0) {
            $alerts[] = [
                'type'    => 'alert',
                'message' => "{$pastDue} " . Str::plural('subscription', $pastDue) . ' past due',
                'action'  => 'Open subscriptions',
                'href'    => route('admin.subscriptions.index'),
            ];
        }
        if ($trialsEndingSoon > 0) {
            $alerts[] = [
                'type'    => 'warning',
                'message' => "{$trialsEndingSoon} " . Str::plural('trial', $trialsEndingSoon) . ' ending within 7 days',
                'action'  => 'Open trials',
                'href'    => route('admin.trials.index'),
            ];
        }

        return [
            'dashboardType'   => 'system',
            'stats'           => $stats,
            'secondaryStats'  => $secondaryStats,
            'atRiskCompanies' => $this->atRiskCompanies(),
            'quickLinks'      => $quickLinks,
            'alerts'          => $alerts,
        ];
    }

    /**
     * Companies that have gone quiet.
     *
     * Replaces the "Recently Created Companies" table. A platform admin's
     * daily question is who is about to churn, not who signed up — signups are
     * a weekly review, and the count is already on a tile above. The
     * thresholds match admin.company-health so the dashboard and that screen
     * cannot classify the same company differently.
     */
    private function atRiskCompanies(int $limit = 6)
    {
        $lastActive = DB::table('users')
            ->whereNotNull('company_id')
            ->whereNotNull('last_active_at')
            ->groupBy('company_id')
            ->selectRaw('company_id, MAX(last_active_at) as la')
            ->pluck('la', 'company_id');

        return Company::where('is_active', true)
            ->with(['subscription.plan'])
            ->withCount('users')
            ->get()
            ->map(function ($company) use ($lastActive) {
                $la   = $lastActive[$company->id] ?? null;
                $days = $la ? (int) CarbonImmutable::parse($la)->diffInDays(now()) : null;

                $company->days_quiet = $days;
                $company->health     = match (true) {
                    $days === null => 'no_data',
                    $days <= 7     => 'healthy',
                    $days <= 14    => 'at_risk',
                    default        => 'inactive',
                };

                return $company;
            })
            ->filter(fn ($c) => in_array($c->health, ['at_risk', 'inactive', 'no_data'], true))
            // Never-active accounts sort last: they are usually a stalled
            // signup rather than a customer slipping away, and mixing the two
            // buries the ones worth a phone call.
            ->sortByDesc(fn ($c) => $c->days_quiet ?? -1)
            ->take($limit)
            ->values();
    }

    // ── Business Manager ───────────────────────────────────────────────────

    private function businessManagerDashboard($user): array
    {
        $outletId = $this->costSummaryOutletId();

        $revenue   = $this->metric(SalesRecord::class,      'sale_date',     'total_revenue');
        $cogs      = $this->metric(SalesRecord::class,      'sale_date',     'total_cost');
        $purchases = $this->metric(PurchaseRecord::class,   'purchase_date', 'total_amount');
        $wastage   = $this->metric(WastageRecord::class,    'wastage_date',  'total_cost');
        $meals     = $this->metric(StaffMealRecord::class,  'meal_date',     'total_cost');

        $grossProfit      = $revenue['value'] - $cogs['value'];
        $priorGrossProfit = $revenue['prior'] - $cogs['prior'];
        $margin           = $revenue['value'] > 0 ? round(($grossProfit / $revenue['value']) * 100, 1) : 0.0;
        $priorMargin      = $revenue['prior'] > 0 ? round(($priorGrossProfit / $revenue['prior']) * 100, 1) : 0.0;

        // Wastage in ringgit says nothing without the volume it came out of.
        $wastagePct      = $revenue['value'] > 0 ? round(($wastage['value'] / $revenue['value']) * 100, 2) : 0.0;
        $priorWastagePct = $revenue['prior'] > 0 ? round(($wastage['prior'] / $revenue['prior']) * 100, 2) : 0.0;

        $pendingPOs = $this->scopedCount(PurchaseOrder::class, fn ($q) => $q->whereIn('status', ['draft', 'submitted']));

        $trend       = $this->buildTrend();
        $costSummary = $this->costSummaryFor($this->window, $outletId);

        return [
            'dashboardType' => 'business',

            'pnl' => [
                'revenue'     => $revenue,
                'cogs'        => $cogs,
                'grossProfit' => ['value' => $grossProfit, 'prior' => $priorGrossProfit],
                'margin'      => ['value' => $margin,      'prior' => $priorMargin],
            ],

            'today'        => $this->window->includesToday() ? $this->todayFigures() : null,
            'runRate'      => $this->window->runRate($revenue['value']),
            'wastage'      => $wastage,
            'wastagePct'   => $wastagePct,
            'priorWastagePct' => $priorWastagePct,
            'staffMeals'   => $meals,
            'purchases'    => $purchases,
            'pendingPOs'   => $pendingPOs,

            'totalIngredients' => Ingredient::where('is_active', true)->count(),
            'activeRecipes'    => Recipe::where('is_active', true)->where('is_prep', false)->count(),

            'costSummary'      => $costSummary,
            'priorCostSummary' => $this->window->usesMonthlyCosting()
                ? $this->costSummaryFor(DashboardPeriod::make('last_month'), $outletId)
                : null,

            'trendMonths'   => $trend,
            'revenueSpark'  => $this->spark($trend, 'revenue'),
            'compareLabel'  => $this->compareLabel(),
            'daysElapsed'   => $this->window->daysElapsed,
            'monthlyCosting'=> $this->window->usesMonthlyCosting(),
            'alerts'        => $this->buildAlerts($pendingPOs, $wastagePct, $margin),
        ];
    }

    // ── Appointed Approver (any role with PO approval assignments) ───────

    private function appointedDashboard($user, array $approverOutletIds): array
    {
        $sel = $this->selectedOutletId($this->outletFilter);

        // PO approval counts scoped to assigned outlets + departments
        // (further narrowed to the selected outlet when the filter is applied)
        $awaitingQ = PurchaseOrder::where('status', 'submitted');
        PoApprover::scopeApprovablePos($awaitingQ, $user->id);
        $awaitingQ->when($sel, fn ($q) => $q->where('outlet_id', $sel));
        $awaitingApproval = $awaitingQ->count();

        $oldestQ = PurchaseOrder::where('status', 'submitted');
        PoApprover::scopeApprovablePos($oldestQ, $user->id);
        $oldestQ->when($sel, fn ($q) => $q->where('outlet_id', $sel));
        $oldestSubmitted = $oldestQ->min('created_at');
        $oldestWait = $oldestSubmitted ? (int) CarbonImmutable::parse($oldestSubmitted)->diffInDays(now()) : null;

        $approvedPOs = PurchaseOrder::where('status', 'approved')
            ->whereIn('outlet_id', $approverOutletIds)
            ->when($sel, fn ($q) => $q->where('outlet_id', $sel))->count();
        $sentPOs = PurchaseOrder::where('status', 'sent')
            ->whereIn('outlet_id', $approverOutletIds)
            ->when($sel, fn ($q) => $q->where('outlet_id', $sel))->count();
        $pendingGrns = GoodsReceivedNote::where('status', 'pending')
            ->whereIn('outlet_id', $approverOutletIds)
            ->when($sel, fn ($q) => $q->where('outlet_id', $sel))->count();

        // Recent submitted POs for quick approval — scoped to assigned outlets + departments
        $recentPosQ = PurchaseOrder::with(['supplier', 'outlet'])
            ->where('status', 'submitted');
        PoApprover::scopeApprovablePos($recentPosQ, $user->id);
        $recentPosQ->when($sel, fn ($q) => $q->where('outlet_id', $sel));
        $recentSubmittedPOs = $recentPosQ->orderBy('created_at')->limit(5)->get();

        $revenue   = $this->metric(SalesRecord::class,    'sale_date',     'total_revenue');
        $purchases = $this->metric(PurchaseRecord::class, 'purchase_date', 'total_amount');
        $wastage   = $this->metric(WastageRecord::class,  'wastage_date',  'total_cost');

        $trend = $this->buildTrend();

        $staleDays = (int) config('costing.po_stale_days');

        $alerts = [];
        if ($awaitingApproval > 0) {
            $alerts[] = [
                'type'    => $oldestWait !== null && $oldestWait >= $staleDays ? 'alert' : 'warning',
                'message' => $oldestWait !== null && $oldestWait >= $staleDays
                    ? "{$awaitingApproval} " . Str::plural('PO', $awaitingApproval) . " awaiting your approval — the oldest has waited {$oldestWait} " . Str::plural('day', $oldestWait)
                    : "{$awaitingApproval} " . Str::plural('PO', $awaitingApproval) . ' awaiting your approval',
                'action'  => 'Review',
                'href'    => route('purchasing.index', ['tab' => 'po', 'statusFilter' => 'submitted']),
            ];
        }
        if ($pendingGrns > 0) {
            $alerts[] = [
                'type'    => 'info',
                'message' => "{$pendingGrns} " . Str::plural('GRN', $pendingGrns) . ' pending outlet receipt',
                'action'  => 'Open GRNs',
                'href'    => route('purchasing.index', ['tab' => 'grn']),
            ];
        }

        return [
            'dashboardType'       => 'operations',
            'awaitingApproval'    => $awaitingApproval,
            'oldestWait'          => $oldestWait,
            'approvedPOs'         => $approvedPOs,
            'sentPOs'             => $sentPOs,
            'pendingGrns'         => $pendingGrns,
            'revenue'             => $revenue,
            'purchases'           => $purchases,
            'wastage'             => $wastage,
            'recentSubmittedPOs'  => $recentSubmittedPOs,
            'trendMonths'         => $trend,
            'compareLabel'        => $this->compareLabel(),
            'alerts'              => $alerts,
            'approverOutletNames' => Outlet::whereIn('id', $approverOutletIds)->pluck('name')->toArray(),
        ];
    }

    // ── Manager ────────────────────────────────────────────────────────────

    private function managerDashboard($user): array
    {
        $revenue = $this->metric(SalesRecord::class, 'sale_date', 'total_revenue');

        $today   = $this->window->includesToday() ? $this->todayFigures() : null;
        $typical = $today ? $this->sameWeekdayAverage() : null;

        $pendingPOs  = $this->scopedCount(PurchaseOrder::class, fn ($q) => $q->whereIn('status', ['draft', 'submitted']));
        $pendingGrns = $this->scopedCount(GoodsReceivedNote::class, fn ($q) => $q->where('status', 'pending'));
        $oldestPoWait = $this->oldestWaitDays(PurchaseOrder::class, fn ($q) => $q->where('status', 'submitted'));

        $wastage    = $this->metric(WastageRecord::class, 'wastage_date', 'total_cost');
        $wastagePct = $revenue['value'] > 0 ? round(($wastage['value'] / $revenue['value']) * 100, 2) : 0.0;

        $trend = $this->buildTrend();

        return [
            'dashboardType' => 'manager',

            'today'   => $today,
            'typical' => $typical,

            'revenue'      => $revenue,
            'runRate'      => $this->window->runRate($revenue['value']),
            'pendingPOs'   => $pendingPOs,
            'oldestPoWait' => $oldestPoWait,
            'pendingGrns'  => $pendingGrns,

            'totalIngredients' => Ingredient::where('is_active', true)->count(),
            'activeRecipes'    => Recipe::where('is_active', true)->where('is_prep', false)->count(),

            'trendMonths'  => $trend,
            'revenueSpark' => $this->spark($trend, 'revenue'),
            'compareLabel' => $this->compareLabel(),
            'periodLabel'  => $this->window->label,
            'alerts'       => $this->buildAlerts($pendingPOs, $wastagePct, null),
        ];
    }

    // ── Chef ───────────────────────────────────────────────────────────────

    private function chefDashboard($user): array
    {
        $pendingGrns = $this->scopedCount(GoodsReceivedNote::class, fn ($q) => $q->where('status', 'pending'));

        // Recent stock take
        $stQ = StockTake::where('status', 'completed')->orderByDesc('stock_take_date');
        $this->scopeByOutletFilter($stQ, $this->outletFilter);
        $lastStockTake = $stQ->first();

        $daysSinceStockTake = $lastStockTake
            ? (int) CarbonImmutable::parse($lastStockTake->stock_take_date)->diffInDays(now())
            : null;

        $overCost = app(RecipeCostService::class)->overCost(auth()->user()->company_id);

        $revenue    = $this->metric(SalesRecord::class,   'sale_date',    'total_revenue');
        $wastage    = $this->metric(WastageRecord::class, 'wastage_date', 'total_cost');
        $wastagePct = $revenue['value'] > 0 ? round(($wastage['value'] / $revenue['value']) * 100, 2) : 0.0;

        [$expiring, $expiringCount] = $this->expiringLabels();

        $trend = $this->buildTrend();

        $alerts = [];
        if ($overCost['count'] > 0) {
            $alerts[] = [
                'type'    => 'warning',
                'message' => $overCost['count'] . ' ' . Str::plural('recipe', $overCost['count'])
                             . ' above the ' . rtrim(rtrim(number_format((float) config('costing.target.over'), 1), '0'), '.') . '% food cost target',
                'action'  => 'Review recipes',
                'href'    => route('recipes.index'),
            ];
        }
        if ($expiringCount > 0) {
            $alerts[] = [
                'type'    => 'alert',
                'message' => $expiringCount . ' labelled ' . Str::plural('item', $expiringCount) . ' expiring within 24 hours',
                'action'  => 'Open list',
                'href'    => route('labels.expiring'),
            ];
        }
        if ($pendingGrns > 0) {
            $alerts[] = [
                'type'    => 'info',
                'message' => "{$pendingGrns} " . Str::plural('GRN', $pendingGrns) . ' pending receipt',
                'action'  => 'Open GRNs',
                'href'    => route('purchasing.index', ['tab' => 'grn']),
            ];
        }

        return [
            'dashboardType' => 'chef',

            'expiring'      => $expiring,
            'expiringCount' => $expiringCount,
            'pendingGrns'   => $pendingGrns,

            'wastage'    => $wastage,
            'wastagePct' => $wastagePct,

            'overCostCount'   => $overCost['count'],
            'overCostRecipes' => $overCost['top'],

            'lastStockTake'      => $lastStockTake,
            'daysSinceStockTake' => $daysSinceStockTake,

            'activeRecipes'    => Recipe::where('is_active', true)->where('is_prep', false)->count(),
            'prepRecipes'      => Recipe::where('is_active', true)->where('is_prep', true)->count(),
            'totalIngredients' => Ingredient::where('is_active', true)->count(),

            'trendMonths'  => $trend,
            'compareLabel' => $this->compareLabel(),
            'alerts'       => $alerts,
        ];
    }

    /**
     * What is about to go off.
     *
     * <x-meter> is described in the project's own notes as the one component
     * that could only belong to this product, and the kitchen dashboard — the
     * single screen where "what expires today" is the first question of the
     * shift — did not use it, or show expiring items at all. The data was
     * already there: every label print writes its computed use-by, which is
     * what makes the expiring list possible in the first place.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: int}
     */
    private function expiringLabels(int $limit = 6): array
    {
        $outletId = $this->selectedOutletId($this->outletFilter) ?? $this->activeOutletId();

        if (! $outletId) {
            return [collect(), 0];
        }

        $horizon = now()->addDay();

        $base = fn () => LabelPrint::unresolved()
            ->forOutlet($outletId)
            ->whereNotNull('end_at')
            ->where('end_at', '<=', $horizon);

        return [
            $base()->orderBy('end_at')->limit($limit)->get(),
            $base()->count(),
        ];
    }

    // ── Purchasing ─────────────────────────────────────────────────────────

    private function purchasingDashboard($user): array
    {
        $submittedPOs = $this->scopedCount(PurchaseOrder::class, fn ($q) => $q->where('status', 'submitted'));
        $approvedPOs  = $this->scopedCount(PurchaseOrder::class, fn ($q) => $q->where('status', 'approved'));
        $pendingDOs   = $this->scopedCount(DeliveryOrder::class, fn ($q) => $q->where('status', 'pending'));
        $pendingGRNs  = $this->scopedCount(GoodsReceivedNote::class, fn ($q) => $q->where('status', 'pending'));
        $todayDOs     = $this->scopedCount(DeliveryOrder::class, fn ($q) => $q->whereDate('delivery_date', today()));

        $spend      = $this->metric(PurchaseRecord::class, 'purchase_date', 'total_amount');
        $oldestWait = $this->oldestWaitDays(PurchaseOrder::class, fn ($q) => $q->where('status', 'submitted'));

        $staleDays = (int) config('costing.po_stale_days');

        // The queue itself, oldest first — the ordering the ageing question
        // implies. The counts above say how many; this says which.
        $ageingQ = PurchaseOrder::with(['supplier', 'outlet'])->where('status', 'submitted');
        $this->scopeByOutletFilter($ageingQ, $this->outletFilter);
        $ageingQueue = $ageingQ->orderBy('created_at')->limit(6)->get();

        $trend = $this->buildTrend();

        $alerts = [];
        if ($oldestWait !== null && $oldestWait >= $staleDays) {
            $stale = $this->scopedCount(PurchaseOrder::class, fn ($q) => $q
                ->where('status', 'submitted')
                ->where('created_at', '<=', now()->subDays($staleDays)));

            $alerts[] = [
                'type'    => 'alert',
                'message' => "{$stale} submitted " . Str::plural('PO', $stale) . " have waited {$staleDays} days or more",
                'action'  => 'Review queue',
                'href'    => route('purchasing.index', ['tab' => 'po', 'statusFilter' => 'submitted']),
            ];
        } elseif ($submittedPOs > 0) {
            $alerts[] = [
                'type'    => 'info',
                'message' => "{$submittedPOs} " . Str::plural('PO', $submittedPOs) . ' awaiting your review',
                'action'  => 'Review queue',
                'href'    => route('purchasing.index', ['tab' => 'po', 'statusFilter' => 'submitted']),
            ];
        }
        if ($pendingGRNs > 0) {
            $alerts[] = [
                'type'    => 'warning',
                'message' => "{$pendingGRNs} " . Str::plural('GRN', $pendingGRNs) . ' pending outlet receipt',
                'action'  => 'Open GRNs',
                'href'    => route('purchasing.index', ['tab' => 'grn']),
            ];
        }

        return [
            'dashboardType' => 'purchasing',

            /*
             * Named, not positional. The pipeline panel and the quick-action
             * badge used to read $stats[0] through $stats[3] straight out of
             * the tile array — so inserting or reordering a tile silently
             * relabelled the pipeline with no error, just four wrong numbers
             * under four right words.
             */
            'submittedPOs' => $submittedPOs,
            'approvedPOs'  => $approvedPOs,
            'pendingDOs'   => $pendingDOs,
            'pendingGRNs'  => $pendingGRNs,
            'todayDOs'     => $todayDOs,
            'spend'        => $spend,
            'oldestWait'   => $oldestWait,
            'staleDays'    => $staleDays,
            'ageingQueue'  => $ageingQueue,
            'topSuppliers' => $this->topSuppliers(),

            'trendMonths'  => $trend,
            'spendSpark'   => $this->spark($trend, 'purchases'),
            'compareLabel' => $this->compareLabel(),
            'alerts'       => $alerts,
        ];
    }

    /**
     * Where the money went, by supplier, for the reporting window.
     *
     * A month spend figure with no composition behind it cannot be acted on.
     * One grouped query with the names joined in — the alternative, loading
     * records and grouping in PHP, is the pattern that made the recipe scan
     * expensive.
     */
    private function topSuppliers(int $limit = 5): array
    {
        $query = PurchaseRecord::query()
            ->selectRaw('supplier_id, SUM(total_amount) as total')
            ->whereBetween('purchase_date', [
                $this->window->start->toDateString(),
                $this->window->end->toDateString(),
            ])
            ->whereNotNull('supplier_id')
            ->groupBy('supplier_id')
            ->orderByDesc('total')
            ->limit($limit);

        $this->scopeByOutletFilter($query, $this->outletFilter);

        $rows = $query->with('supplier')->get();
        $all  = (float) $rows->sum('total');

        return $rows->map(fn ($row) => [
            'name'  => $row->supplier?->name ?? 'Unknown supplier',
            'total' => (float) $row->total,
            'share' => $all > 0 ? round(((float) $row->total / $all) * 100) : 0,
        ])->all();
    }

    // ── Finance ────────────────────────────────────────────────────────────

    private function financeDashboard($user): array
    {
        $outletId = $this->costSummaryOutletId();

        $revenue   = $this->metric(SalesRecord::class,     'sale_date',     'total_revenue');
        $cogs      = $this->metric(SalesRecord::class,     'sale_date',     'total_cost');
        $purchases = $this->metric(PurchaseRecord::class,  'purchase_date', 'total_amount');
        $wastage   = $this->metric(WastageRecord::class,   'wastage_date',  'total_cost');
        $meals     = $this->metric(StaffMealRecord::class, 'meal_date',     'total_cost');

        $grossProfit      = $revenue['value'] - $cogs['value'];
        $priorGrossProfit = $revenue['prior'] - $cogs['prior'];
        $margin           = $revenue['value'] > 0 ? round(($grossProfit / $revenue['value']) * 100, 1) : 0.0;
        $priorMargin      = $revenue['prior'] > 0 ? round(($priorGrossProfit / $revenue['prior']) * 100, 1) : 0.0;

        $wastagePct = $revenue['value'] > 0 ? round(($wastage['value'] / $revenue['value']) * 100, 2) : 0.0;

        $trend = $this->buildTrend();

        // Margin per month, for the sparkline under the hero figure. Derived
        // from the series already fetched rather than six more queries.
        $marginSpark = array_map(
            fn ($m) => $m['revenue'] > 0 ? round((($m['revenue'] - $m['cogs']) / $m['revenue']) * 100, 1) : 0,
            $trend
        );

        return [
            'dashboardType' => 'finance',

            'margin'      => ['value' => $margin, 'prior' => $priorMargin],
            'marginFloor' => (float) config('costing.margin_floor_pct'),
            'marginSpark' => $marginSpark,

            'revenue'     => $revenue,
            'cogs'        => $cogs,
            'grossProfit' => ['value' => $grossProfit, 'prior' => $priorGrossProfit],
            'purchases'   => $purchases,
            'wastage'     => $wastage,
            'wastagePct'  => $wastagePct,
            'staffMeals'  => $meals,

            'costSummary'      => $this->costSummaryFor($this->window, $outletId),
            'priorCostSummary' => $this->window->usesMonthlyCosting()
                ? $this->costSummaryFor(DashboardPeriod::make('last_month'), $outletId)
                : null,

            'trendMonths'    => $trend,
            'compareLabel'   => $this->compareLabel(),
            'daysElapsed'    => $this->window->daysElapsed,
            'monthlyCosting' => $this->window->usesMonthlyCosting(),

            /*
             * financeDashboard was the only builder that returned no alerts
             * key, and finance.blade.php the only role view that omitted the
             * include — so the role whose entire job is margin was the one
             * role never told it had moved.
             */
            'alerts' => $this->financeAlerts($margin, $priorMargin, $wastagePct),
        ];
    }

    private function financeAlerts(float $margin, float $priorMargin, float $wastagePct): array
    {
        $alerts = [];
        $floor  = (float) config('costing.margin_floor_pct');
        $warn   = (float) config('costing.wastage_warn_pct');

        if ($margin > 0 && $margin < $floor) {
            $alerts[] = [
                'type'    => 'alert',
                'message' => "Gross margin is {$margin}%, below the {$floor}% floor",
                'action'  => 'Open cost report',
                'href'    => route('reports.index'),
            ];
        } elseif ($priorMargin > 0 && ($priorMargin - $margin) >= 3) {
            $drop = round($priorMargin - $margin, 1);
            $alerts[] = [
                'type'    => 'warning',
                'message' => "Gross margin is down {$drop} points on the previous period",
                'action'  => 'Open cost report',
                'href'    => route('reports.index'),
            ];
        }

        if ($wastagePct > $warn) {
            $alerts[] = [
                'type'    => 'warning',
                'message' => "Wastage is {$wastagePct}% of revenue, above the {$warn}% threshold",
                'action'  => 'Open wastage',
                'href'    => route('inventory.index', ['tab' => 'wastage']),
            ];
        }

        return $alerts;
    }

    // ── Shared Helpers ────────────────────────────────────────────────────

    /**
     * The standing conditions worth interrupting someone about.
     *
     * Every rule here now carries a destination. The previous version produced
     * three messages, none of them linked — a reader was told four recipes
     * were over target and then had to go and work out which four, which the
     * dashboard already knew.
     *
     * The over-cost scan is no longer run inline. It hydrated every active
     * recipe with its lines, ingredients, UOMs and conversions on every page
     * load, and again on every outlet-filter change, to produce one integer.
     * See RecipeCostService.
     */
    private function buildAlerts(int $pendingPOs, float $wastagePct, ?float $margin): array
    {
        $alerts = [];

        $overCost = app(RecipeCostService::class)->overCost(auth()->user()->company_id);
        $target   = rtrim(rtrim(number_format((float) config('costing.target.over'), 1), '0'), '.');

        if ($overCost['count'] > 0) {
            $alerts[] = [
                'type'    => 'warning',
                'message' => $overCost['count'] . ' ' . Str::plural('recipe', $overCost['count'])
                             . " above the {$target}% food cost target",
                'action'  => 'Review recipes',
                'href'    => route('recipes.index'),
            ];
        }

        if ($wastagePct > (float) config('costing.wastage_warn_pct')) {
            $alerts[] = [
                'type'    => 'warning',
                'message' => "Wastage is {$wastagePct}% of revenue for this period",
                'action'  => 'Open wastage',
                'href'    => route('inventory.index', ['tab' => 'wastage']),
            ];
        }

        if ($margin !== null && $margin > 0 && $margin < (float) config('costing.margin_floor_pct')) {
            $alerts[] = [
                'type'    => 'alert',
                'message' => "Gross margin is {$margin}%, below the " . config('costing.margin_floor_pct') . '% floor',
                'action'  => 'Open cost report',
                'href'    => route('reports.index'),
            ];
        }

        if ($pendingPOs > 0) {
            $alerts[] = [
                'type'    => 'info',
                'message' => "{$pendingPOs} purchase " . Str::plural('order', $pendingPOs) . ' pending',
                'action'  => 'Open purchasing',
                'href'    => route('purchasing.index', ['tab' => 'po']),
            ];
        }

        /*
         * The stock-take reminder used to be silent until the 25th, so three
         * weeks of a month could pass with nothing said. It now tracks how far
         * through the month we actually are, and only for a month-shaped
         * window — "no stock take in the last 7 days" is not a finding.
         */
        if ($this->window->usesMonthlyCosting()) {
            $stQ = StockTake::where('status', 'completed')
                ->whereBetween('stock_take_date', [
                    $this->window->start->toDateString(),
                    $this->window->end->toDateString(),
                ]);
            $this->scopeByOutletFilter($stQ, $this->outletFilter);

            $elapsedShare = $this->window->daysElapsed / max($this->window->daysInPeriod, 1);

            if (! $stQ->exists() && $elapsedShare >= 0.5) {
                $alerts[] = [
                    'type'    => $elapsedShare >= 0.8 ? 'alert' : 'warning',
                    'message' => 'No stock take completed for this period yet ('
                                 . $this->window->daysElapsed . ' of ' . $this->window->daysInPeriod . ' days elapsed)',
                    'action'  => 'Start stock take',
                    'href'    => route('inventory.stock-takes.create'),
                ];
            }
        }

        return $alerts;
    }
}
