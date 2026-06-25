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

    /**
     * Outlets the user can pick from in a per-page outlet filter.
     * Returns an empty collection for single-outlet users (nothing to filter),
     * so callers can hide the dropdown entirely.
     */
    protected function filterableOutlets()
    {
        $ids = $this->availableOutletIds();

        if (count($ids) <= 1) {
            return collect();
        }

        return Outlet::whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Resolve a user-supplied outlet-filter value to a single outlet id the
     * user is actually allowed to see. Returns null when empty or out of reach,
     * so a tampered value can never widen access.
     */
    protected function selectedOutletId($outletFilter): ?int
    {
        if (empty($outletFilter)) {
            return null;
        }

        $id = (int) $outletFilter;

        return in_array($id, $this->availableOutletIds(), true) ? $id : null;
    }

    /**
     * Apply outlet scoping plus an optional single-outlet UI filter. The base
     * scope always bounds the query to accessible outlets; the filter only
     * narrows further to the one selected outlet.
     */
    protected function scopeByOutletFilter(Builder $query, $outletFilter, string $column = 'outlet_id'): Builder
    {
        $this->scopeByOutlet($query, $column);

        $id = $this->selectedOutletId($outletFilter);
        if ($id !== null) {
            $query->where($column, $id);
        }

        return $query;
    }
}
