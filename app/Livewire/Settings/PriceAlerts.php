<?php

namespace App\Livewire\Settings;

use App\Models\PriceChangeNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class PriceAlerts extends Component
{
    use WithPagination;

    public string $threshold = '';
    public string $directionFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    public function mount(): void
    {
        $this->threshold = (string) (Auth::user()->company?->price_alert_threshold ?? 5.00);
        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function updatedDirectionFilter(): void { $this->resetPage(); }
    public function updatedDateFrom(): void { $this->resetPage(); }
    public function updatedDateTo(): void { $this->resetPage(); }

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

    public function render()
    {
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

        $notifications = $query->orderByDesc('detected_at')->paginate(20);

        $unreadCount = PriceChangeNotification::unread()->count();
        $increaseCount = PriceChangeNotification::where('is_dismissed', false)->where('direction', 'increase')->count();
        $decreaseCount = PriceChangeNotification::where('is_dismissed', false)->where('direction', 'decrease')->count();

        return view('livewire.settings.price-alerts', compact(
            'notifications', 'unreadCount', 'increaseCount', 'decreaseCount'
        ))->layout('layouts.app', ['title' => 'Price Alerts']);
    }
}
