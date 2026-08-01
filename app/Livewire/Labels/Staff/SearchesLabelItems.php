<?php

namespace App\Livewire\Labels\Staff;

use App\Services\Labels\LabelItemSearch;

/**
 * Item lookup for the staff screens.
 *
 * Thin now: the queries moved into LabelItemSearch so the staff app and the
 * manager screens cannot drift apart on what is findable — they already had,
 * on both the group names and the result cap. What stays here is the one
 * genuinely staff-specific part: the company comes from the PIN session
 * rather than from an authenticated web user.
 */
trait SearchesLabelItems
{
    protected function findLabelItem(?string $type, ?int $id)
    {
        return app(LabelItemSearch::class)->find($this->staff()->company_id, $type, $id);
    }

    /**
     * @return array<string, array{items: array, total: int, truncated: bool}>
     */
    protected function labelSearchResults(string $term): array
    {
        return app(LabelItemSearch::class)->groups($this->staff()->company_id, $term);
    }
}
