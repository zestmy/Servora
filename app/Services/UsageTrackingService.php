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

    private function getCount(Company $company, string $metric): int
    {
        return match ($metric) {
            'outlets'     => $company->outlets()->count(),
            'users'       => $company->users()->count(),
            'recipes'     => $company->recipes()->count(),
            'ingredients' => $company->ingredients()->count(),
            'lms_users'   => LmsUser::where('company_id', $company->id)->count(),
            default       => 0,
        };
    }
}
