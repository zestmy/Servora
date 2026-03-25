<?php

namespace App\Livewire\Admin;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Livewire\Component;

class TrialDashboard extends Component
{
    public function extendTrial(int $id, int $days = 7): void
    {
        $sub = Subscription::findOrFail($id);
        if (!$sub->isTrial()) {
            session()->flash('error', 'Only trial subscriptions can be extended.');
            return;
        }

        $newEnd = $sub->trial_ends_at->addDays($days);
        $sub->update(['trial_ends_at' => $newEnd, 'current_period_end' => $newEnd]);
        $sub->company->update(['trial_ends_at' => $newEnd]);
        session()->flash('success', "Trial extended by {$days} days for {$sub->company->name}.");
    }

    public function convertToPaid(int $id): void
    {
        $sub = Subscription::findOrFail($id);
        app(SubscriptionService::class)->activate($sub);
        session()->flash('success', "Converted {$sub->company->name} to active subscription.");
    }

    public function deactivate(int $id): void
    {
        $sub = Subscription::findOrFail($id);
        app(SubscriptionService::class)->expire($sub);
        session()->flash('success', "Deactivated subscription for {$sub->company->name}.");
    }

    public function render()
    {
        $trials = Subscription::where('status', Subscription::STATUS_TRIALING)
            ->with(['company', 'plan'])
            ->orderBy('trial_ends_at')
            ->get();

        $totalTrials = $trials->count();
        $expiringSoon = $trials->filter(fn ($s) => $s->daysRemaining() <= 3)->count();

        // Conversion rate
        $totalEverTrialed = Subscription::count();
        $totalConverted = Subscription::where('status', Subscription::STATUS_ACTIVE)->count();
        $conversionRate = $totalEverTrialed > 0 ? round($totalConverted / $totalEverTrialed * 100, 1) : 0;

        // Recently expired
        $recentlyExpired = Subscription::where('status', Subscription::STATUS_EXPIRED)
            ->where('updated_at', '>=', now()->subDays(7))
            ->with(['company', 'plan'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return view('livewire.admin.trial-dashboard', compact('trials', 'totalTrials', 'expiringSoon', 'conversionRate', 'recentlyExpired'))
            ->layout('layouts.app', ['title' => 'Trial Dashboard']);
    }
}
