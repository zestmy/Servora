<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait ScopesToActiveOutlet
{
    protected function activeOutletId(): ?int
    {
        return auth()->user()?->activeOutletId();
    }

    /**
     * Apply outlet filter to a query if an active outlet is selected.
     * Pass the outlet column name (default: 'outlet_id').
     */
    protected function scopeByOutlet(Builder $query, string $column = 'outlet_id'): Builder
    {
        $outletId = $this->activeOutletId();

        if ($outletId) {
            $query->where($column, $outletId);
        }

        return $query;
    }
}
