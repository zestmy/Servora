<?php

namespace App\Traits;

use App\Models\Outlet;
use Illuminate\Database\Eloquent\Builder;

trait ScopesToActiveOutlet
{
    /**
     * All outlet IDs this user may see.
     * - System roles / can_view_all_outlets → every outlet in the company.
     * - Everyone else → only their assigned outlets.
     */
    protected function availableOutletIds(): array
    {
        $user = auth()->user();
        if (! $user) return [];

        return $user->canViewAllOutlets()
            ? Outlet::where('company_id', $user->company_id)->pluck('id')->all()
            : $user->outlets()->pluck('outlets.id')->all();
    }

    /**
     * Scope query to the user's accessible outlets.
     * Single-outlet users see exactly their one outlet (no change from before).
     * Multi-outlet / cross-outlet users see all outlets they can access.
     */
    protected function scopeByOutlet(Builder $query, string $column = 'outlet_id'): Builder
    {
        $ids = $this->availableOutletIds();

        if (! empty($ids)) {
            $query->whereIn($column, $ids);
        }

        return $query;
    }

    /** @deprecated Use availableOutletIds() for listing queries. Kept for form prefill compat. */
    protected function activeOutletId(): ?int
    {
        return auth()->user()?->activeOutletId();
    }
}
