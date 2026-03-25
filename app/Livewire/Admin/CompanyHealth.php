<?php

namespace App\Livewire\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class CompanyHealth extends Component
{
    use WithPagination;

    public string $search = '';
    public string $healthFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Company::where('is_active', true)
            ->withCount(['outlets', 'users', 'recipes', 'ingredients']);

        if ($this->search) {
            $query->where('name', 'like', "%{$this->search}%");
        }

        $companies = $query->orderBy('name')->paginate(20);

        // Enrich with health data
        $companyIds = $companies->pluck('id');

        // Last login per company (most recent user session)
        $lastLogins = DB::table('sessions')
            ->join('users', 'sessions.user_id', '=', 'users.id')
            ->whereIn('users.company_id', $companyIds)
            ->groupBy('users.company_id')
            ->selectRaw('users.company_id, MAX(sessions.last_activity) as last_active')
            ->pluck('last_active', 'company_id');

        // Classify health
        $healthData = [];
        foreach ($companies as $company) {
            $lastActive = isset($lastLogins[$company->id])
                ? \Carbon\Carbon::createFromTimestamp($lastLogins[$company->id])
                : null;

            $daysSinceActive = $lastActive ? (int) $lastActive->diffInDays(now()) : null;

            $status = 'unknown';
            if ($daysSinceActive === null) {
                $status = 'no_data';
            } elseif ($daysSinceActive <= 1) {
                $status = 'active';
            } elseif ($daysSinceActive <= 7) {
                $status = 'healthy';
            } elseif ($daysSinceActive <= 14) {
                $status = 'at_risk';
            } else {
                $status = 'inactive';
            }

            $healthData[$company->id] = [
                'last_active'       => $lastActive,
                'days_since_active' => $daysSinceActive,
                'status'            => $status,
            ];
        }

        // Filter by health status
        if ($this->healthFilter) {
            $companies->setCollection(
                $companies->getCollection()->filter(
                    fn ($c) => ($healthData[$c->id]['status'] ?? '') === $this->healthFilter
                )
            );
        }

        // Stats
        $totalActive = collect($healthData)->where('status', 'active')->count() + collect($healthData)->where('status', 'healthy')->count();
        $totalAtRisk = collect($healthData)->where('status', 'at_risk')->count();
        $totalInactive = collect($healthData)->where('status', 'inactive')->count();

        return view('livewire.admin.company-health', compact('companies', 'healthData', 'totalActive', 'totalAtRisk', 'totalInactive'))
            ->layout('layouts.app', ['title' => 'Company Health']);
    }
}
