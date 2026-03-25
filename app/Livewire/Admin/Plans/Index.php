<?php

namespace App\Livewire\Admin\Plans;

use App\Models\Plan;
use Livewire\Component;

class Index extends Component
{
    public function toggleActive(int $id): void
    {
        $plan = Plan::findOrFail($id);
        $plan->update(['is_active' => !$plan->is_active]);
        session()->flash('success', "Plan \"{$plan->name}\" " . ($plan->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function delete(int $id): void
    {
        $plan = Plan::findOrFail($id);

        if ($plan->subscriptions()->count() > 0) {
            session()->flash('error', "Cannot delete \"{$plan->name}\" — it has active subscriptions.");
            return;
        }

        $plan->delete();
        session()->flash('success', "Plan \"{$plan->name}\" deleted.");
    }

    public function render()
    {
        $plans = Plan::withCount('subscriptions')->ordered()->get();

        return view('livewire.admin.plans.index', compact('plans'))
            ->layout('layouts.app', ['title' => 'Subscription Plans']);
    }
}
