<?php

namespace App\Livewire\Settings;

use App\Models\PriceChangeNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class PriceAlerts extends Component
{
    use WithPagination;

    // 'alerts' = threshold notifications inbox; 'history' = every price
    // change over time, derived straight from ingredient_price_history.
    #[\Livewire\Attributes\Url]
    public string $view = 'alerts';

    public string $threshold = '';
    public string $directionFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $search = '';

    public function mount(): void
    {
        $this->threshold = (string) (Auth::user()->company?->price_alert_threshold ?? 5.00);
        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function updatedDirectionFilter(): void { $this->resetPage(); }
    public function updatedDateFrom(): void { $this->resetPage(); }
    public function updatedDateTo(): void { $this->resetPage(); }
    public function updatedSearch(): void { $this->resetPage(); }

    public function setView(string $view): void
    {
        if (in_array($view, ['alerts', 'history'], true)) {
            $this->view = $view;
            $this->resetPage();
        }
    }

    public function saveThreshold(): void
    {
        $this->validate(['threshold' => 'required|numeric|min:0.1|max:100']);
        Auth::user()->company->update(['price_alert_threshold' => $this->threshold]);
        session()->flash('success', 'Alert threshold updated to ' . $this->threshold . '%.');
    }

    public function markRead(int $id): void
    {
        PriceChangeNotification::findOrFail($id)->update(['is_read' => true]);
    }

    public function dismiss(int $id): void
    {
        PriceChangeNotification::findOrFail($id)->update(['is_dismissed' => true]);
    }

    public function markAllRead(): void
    {
        PriceChangeNotification::where('is_read', false)->update(['is_read' => true]);
        session()->flash('success', 'All notifications marked as read.');
    }

    /**
     * Every Market List price change in the date range, derived from
     * ingredient_price_history: each row is compared against the previous
     * row for the same (ingredient, supplier); unchanged-cost receives are
     * filtered out, so what remains is the price movement timeline.
     */
    protected function historyQuery()
    {
        $companyId = \Illuminate\Support\Facades\Auth::user()->company_id;

        $q = \Illuminate\Support\Facades\DB::table('ingredient_price_history as iph')
            ->join('ingredients as i', 'i.id', '=', 'iph.ingredient_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'iph.supplier_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'iph.uom_id')
            ->where('i.company_id', $companyId)
            ->selectRaw("iph.id, iph.ingredient_id, iph.cost, iph.effective_date, iph.source,
                i.name as ingredient_name, s.name as supplier_name, u.abbreviation as uom_abbr,
                (SELECT iph2.cost FROM ingredient_price_history iph2
                 WHERE iph2.ingredient_id = iph.ingredient_id
                   AND COALESCE(iph2.supplier_id, 0) = COALESCE(iph.supplier_id, 0)
                   AND (iph2.effective_date < iph.effective_date
                        OR (iph2.effective_date = iph.effective_date AND iph2.id < iph.id))
                 ORDER BY iph2.effective_date DESC, iph2.id DESC LIMIT 1) as prev_cost");

        if ($this->dateFrom) {
            $q->where('iph.effective_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $q->where('iph.effective_date', '<=', $this->dateTo . ' 23:59:59');
        }
        if ($this->search !== '') {
            $s = '%' . $this->search . '%';
            $q->where(fn ($w) => $w->where('i.name', 'like', $s)->orWhere('s.name', 'like', $s));
        }

        // Only rows where the price actually moved (direction-filterable).
        // Filtered in an outer query — MySQL strict mode rejects HAVING on
        // non-grouped columns.
        $outer = \Illuminate\Support\Facades\DB::query()->fromSub($q, 't')
            ->whereNotNull('t.prev_cost')
            ->whereColumn('t.prev_cost', '!=', 't.cost');
        if ($this->directionFilter === 'increase') {
            $outer->whereColumn('t.cost', '>', 't.prev_cost');
        } elseif ($this->directionFilter === 'decrease') {
            $outer->whereColumn('t.cost', '<', 't.prev_cost');
        }

        return $outer->orderByDesc('t.effective_date')->orderByDesc('t.id');
    }

    public function render()
    {
        $history = null;
        $notifications = null;
        $historyStats = null;

        if ($this->view === 'history') {
            $history = $this->historyQuery()->paginate(20);

            // Range-wide stats over the same filtered set (capped for safety)
            $rows = $this->historyQuery()->limit(5000)->get()
                ->map(function ($r) {
                    $r->change_amt = (float) $r->cost - (float) $r->prev_cost;
                    $r->change_pct = (float) $r->prev_cost > 0
                        ? round(($r->change_amt / (float) $r->prev_cost) * 100, 1)
                        : null;
                    return $r;
                });

            $increases = $rows->where('change_amt', '>', 0);
            $decreases = $rows->where('change_amt', '<', 0);
            $pcts      = $rows->pluck('change_pct')->filter(fn ($p) => $p !== null);

            $historyStats = [
                'total'      => $rows->count(),
                'increases'  => $increases->count(),
                'decreases'  => $decreases->count(),
                'netAmount'  => $rows->sum('change_amt'),
                'avgPct'     => $pcts->isNotEmpty() ? round($pcts->avg(), 1) : null,
                'topChanges' => $rows->sortByDesc(fn ($r) => abs($r->change_pct ?? 0))->take(5)->values(),
                'bySupplier' => $rows->groupBy(fn ($r) => $r->supplier_name ?? 'Manual edit')
                    ->map(fn ($g, $name) => [
                        'name'  => $name,
                        'count' => $g->count(),
                        'net'   => $g->sum('change_amt'),
                    ])
                    ->sortByDesc('count')->take(5)->values(),
            ];
        } else {
            $query = PriceChangeNotification::with(['ingredient', 'supplier'])
                ->where('is_dismissed', false);

            if ($this->directionFilter) {
                $query->where('direction', $this->directionFilter);
            }
            if ($this->dateFrom) {
                $query->where('detected_at', '>=', $this->dateFrom);
            }
            if ($this->dateTo) {
                $query->where('detected_at', '<=', $this->dateTo . ' 23:59:59');
            }
            if ($this->search !== '') {
                $s = '%' . $this->search . '%';
                $query->where(fn ($w) => $w
                    ->whereHas('ingredient', fn ($iq) => $iq->where('name', 'like', $s))
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', $s)));
            }

            $notifications = $query->orderByDesc('detected_at')->paginate(20);
        }

        $unreadCount = PriceChangeNotification::unread()->count();
        $increaseCount = PriceChangeNotification::where('is_dismissed', false)->where('direction', 'increase')->count();
        $decreaseCount = PriceChangeNotification::where('is_dismissed', false)->where('direction', 'decrease')->count();

        return view('livewire.settings.price-alerts', compact(
            'notifications', 'history', 'historyStats', 'unreadCount', 'increaseCount', 'decreaseCount'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Price Alerts']);
    }
}
