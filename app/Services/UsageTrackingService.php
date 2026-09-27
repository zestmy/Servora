<?php

namespace App\Services;

use App\Models\Company;
use App\Models\UsageRecord;
use App\Models\LmsUser;

class UsageTrackingService
{
    private const METRICS = ['outlets', 'users', 'recipes', 'ingredients', 'lms_users'];

    public function snapshot(Company $company): array
    {
        $now = now();
        $counts = [];

        foreach (self::METRICS as $metric) {
            $count = $this->getCount($company, $metric);
            $counts[$metric] = $count;

            UsageRecord::create([
                'company_id'  => $company->id,
                'metric'      => $metric,
                'count'       => $count,
                'recorded_at' => $now,
            ]);
        }

        return $counts;
    }

    public function snapshotAll(): int
    {
        $companies = Company::where('is_active', true)->get();
        $total = 0;

        foreach ($companies as $company) {
            $this->snapshot($company);
            $total++;
        }

        return $total;
    }

    public function getCurrentCounts(Company $company): array
    {
        $counts = [];
        foreach (self::METRICS as $metric) {
            $counts[$metric] = $this->getCount($company, $metric);
        }

        return $counts;
    }

    /**
     * How many of a limited thing the company has. The single definition —
     * the Billing usage bars and plan-limit enforcement both read it.
     *
     * Outlets: active ones; archiving an outlet is how a company gets back
     * under the Free limit. Users: anyone whose active company this is OR who
     * is a member through company_user, since one login can serve several
     * companies and `users.company_id` alone undercounts them.
     */
    public function count(Company $company, string $metric): int
    {
        return $this->getCount($company, $metric);
    }

    private function getCount(Company $company, string $metric): int
    {
        return match ($metric) {
            'outlets'     => $company->outlets()->where('is_active', true)->count(),
            'users'       => \App\Models\User::where('company_id', $company->id)
                ->orWhereHas('companies', fn ($q) => $q->where('companies.id', $company->id))
                ->count(),
            'recipes'     => $company->recipes()->count(),
            'ingredients' => $company->ingredients()->count(),
            'lms_users'   => LmsUser::where('company_id', $company->id)->count(),
            default       => 0,
        };
    }
}
