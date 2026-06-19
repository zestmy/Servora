<?php

namespace App\Observers;

use App\Models\Ingredient;
use App\Services\PrepCostService;

class IngredientObserver
{
    /**
     * After any ingredient is saved (manual edit, quick-edit, CSV import,
     * GRN/PO goods receipt, document review, prep-item sync, …) refresh the
     * stored cost of every prep item that uses it — directly or transitively.
     *
     * The IngredientObserver is intentionally the single choke point: every
     * cost-change path goes through an Eloquent save, so wiring it here covers
     * them all without touching each call site.
     */
    public function saved(Ingredient $ingredient): void
    {
        // Only react when a cost-affecting field actually changed.
        if (! $ingredient->wasChanged(['purchase_price', 'pack_size', 'yield_percent', 'current_cost'])) {
            return;
        }

        // PrepCostService persists with saveQuietly(), so this observer is not
        // re-entered by the cascade — recursion is driven by the service's queue.
        app(PrepCostService::class)->recalculateForIngredients([$ingredient->id]);
    }
}
