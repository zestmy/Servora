<?php

namespace App\Traits;

use App\Models\Outlet;
use Illuminate\Support\Facades\Auth;

/**
 * Shared outlet selection for record-creation forms (stock take, wastage,
 * staff meal, purchase capture). Multi-outlet users pick which outlet the
 * record belongs to; single-outlet users get their one outlet automatically.
 *
 * Replaces the old "first company outlet" default, which silently filed every
 * multi-outlet record under the wrong location.
 */
trait PicksRecordOutlet
{
    /** The outlet this record belongs to. */
    public ?int $outlet_id = null;

    /**
     * Outlet ids the user may create records for.
     *
     * In Central Kitchen mode this collapses to the kitchen's own outlet: the
     * user is working one kitchen, so offering a list of shop outlets is both
     * noise and a chance to file kitchen wastage against a restaurant.
     */
    protected function creatableOutletIds(): array
    {
        $user = Auth::user();
        if (! $user) return [];

        if ($kitchenOutletId = $user->kitchenOutletId()) {
            return [$kitchenOutletId];
        }

        return $user->accessibleOutletIds();
    }

    /**
     * Outlets the user can choose from, for the form selector.
     *
     * Includes whatever this record is already filed against, even if that
     * outlet has since closed: editing a stock take from last quarter should
     * not silently re-file it somewhere still trading.
     */
    protected function outletOptions()
    {
        return Outlet::whereIn('id', array_unique(array_merge(
                $this->creatableOutletIds(),
                $this->outlet_id ? [(int) $this->outlet_id] : [],
            )))
            ->selectable($this->outlet_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Whether the user can pick among more than one outlet. */
    protected function hasOutletChoice(): bool
    {
        return count($this->creatableOutletIds()) > 1;
    }

    /**
     * Initialise outlet_id. For edits, pass the record's existing outlet. For
     * new records, defaults to the user's active outlet when accessible, else
     * the first accessible outlet.
     */
    protected function initOutlet(?int $existingOutletId = null): void
    {
        if ($existingOutletId !== null) {
            $this->outlet_id = $existingOutletId;
            return;
        }

        $ids    = $this->creatableOutletIds();
        $active = Auth::user()?->activeOutletId();

        $this->outlet_id = ($active && in_array($active, $ids, true))
            ? $active
            : ($ids[0] ?? null);
    }

    /**
     * Validate and return the outlet id to persist. Guarantees the value is one
     * the user may actually create records for, so a tampered value is rejected.
     */
    protected function resolveOutletId(): int
    {
        $id = (int) $this->outlet_id;
        abort_unless(in_array($id, $this->creatableOutletIds(), true), 403);

        return $id;
    }
}
