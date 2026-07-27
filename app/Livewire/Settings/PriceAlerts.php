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
     * The price movement timeline. The query definition lives in
     * PriceHistoryExportController::query() — shared with the PDF/Excel
     * exports so screen and export always agree.
     */
    protected function historyQuery()
    {
        return \App\Http\Controllers\PriceHistoryExportController::query(
            \Illuminate\Support\Facades\Auth::user()->company_id,
            $this->dateFrom,
            $this->dateTo,
            $this->search,
            $this->directionFilter,
        );
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
