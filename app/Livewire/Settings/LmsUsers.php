<?php

namespace App\Livewire\Settings;

use App\Models\LmsUser;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class LmsUsers extends Component
{
    use WithPagination;

    public string $statusFilter = 'pending';
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function approve(int $id): void
    {
        $user = LmsUser::where('company_id', Auth::user()->company_id)->findOrFail($id);
        $user->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        session()->flash('success', "{$user->name} has been approved.");
    }

    public function reject(int $id): void
    {
        $user = LmsUser::where('company_id', Auth::user()->company_id)->findOrFail($id);
        $user->update(['status' => 'rejected']);
        session()->flash('success', "{$user->name} has been rejected.");
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;
        $company   = Auth::user()->company;

        $users = LmsUser::where('company_id', $companyId)
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('name', 'like', "%{$this->search}%")
                   ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->with(['outlet', 'approver'])
            ->latest()
            ->paginate(20);

        // Stats
        $totalLmsUsers   = LmsUser::where('company_id', $companyId)->count();
        $pendingCount    = LmsUser::where('company_id', $companyId)->where('status', 'pending')->count();
        $approvedCount   = LmsUser::where('company_id', $companyId)->where('status', 'approved')->count();
        $rejectedCount   = LmsUser::where('company_id', $companyId)->where('status', 'rejected')->count();

        $totalSops       = Recipe::where('is_active', true)->where('is_prep', false)->has('steps')->count();
        $totalRecipes    = Recipe::where('is_active', true)->where('is_prep', false)->count();
        $recipesWithVideo = Recipe::where('is_active', true)->where('is_prep', false)->whereNotNull('video_url')->count();

        // SOP categories
        $sopCategories = Recipe::where('is_active', true)
            ->where('is_prep', false)
            ->has('steps')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        $domain = config('app.domain');
        if ($domain && $company?->slug) {
            $lmsUrl = "https://{$company->slug}.{$domain}/lms/login";
            $lmsRegisterUrl = "https://{$company->slug}.{$domain}/lms/register";
        } elseif ($company?->slug) {
            $lmsUrl = url("/lms/{$company->slug}/login");
            $lmsRegisterUrl = url("/lms/{$company->slug}/register");
        } else {
            $lmsUrl = null;
            $lmsRegisterUrl = null;
        }

        return view('livewire.settings.lms-users', compact(
            'users', 'totalLmsUsers', 'pendingCount', 'approvedCount', 'rejectedCount',
            'totalSops', 'totalRecipes', 'recipesWithVideo', 'sopCategories',
            'lmsUrl', 'lmsRegisterUrl', 'company'
        ))->layout('layouts.app', ['title' => 'Training Portal']);
    }
}
