<?php

namespace App\Livewire\Admin\Subscriptions;

use App\Models\Subscription;
use App\Models\Plan;
use App\Services\SubscriptionService;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $planFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function extendTrial(int $id, int $days = 7): void
    {
        $sub = Subscription::findOrFail($id);

        if (!$sub->isTrial()) {
            session()->flash('error', 'Only trial subscriptions can be extended.');
            return;
        }

        $newEnd = $sub->trial_ends_at->addDays($days);
        $sub->update([
            'trial_ends_at'      => $newEnd,
            'current_period_end' => $newEnd,
        ]);
        $sub->company->update(['trial_ends_at' => $newEnd]);

        session()->flash('success', "Trial extended by {$days} days for {$sub->company->name}.");
    }

    public function cancelSubscription(int $id): void
    {
        $sub = Subscription::findOrFail($id);
        app(SubscriptionService::class)->cancel($sub);
        session()->flash('success', "Subscription cancelled for {$sub->company->name}.");
    }

    public function render()
    {
        $query = Subscription::with(['company', 'plan'])
            ->latest();

        if ($this->search) {
            $query->whereHas('company', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->planFilter) {
            $query->where('plan_id', $this->planFilter);
        }

        $subscriptions = $query->paginate(20);
        $plans = Plan::ordered()->get();

        return view('livewire.admin.subscriptions.index', compact('subscriptions', 'plans'))
            ->layout('layouts.app', ['title' => 'Subscriptions']);
    }
}
