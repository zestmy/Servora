<?php

namespace App\Services;

use App\Models\AssetCount;
use App\Models\AssetCountLine;
use App\Models\AssetMovement;
use App\Models\AssetMovementLine;
use Illuminate\Support\Facades\Auth;

/**
 * How many of an asset an outlet holds, and therefore what it is worth.
 *
 * THE ONE PLACE THIS ARITHMETIC LIVES. The count form needs it to show what it
 * expected, the register needs it to value the outlet, and a receipt needs it
 * to say what the new total is — three screens that must never disagree about
 * the same number.
 *
 * THE RULE, IN ONE SENTENCE: an asset's quantity is what the last completed
 * count that listed it found, plus every receipt and minus every disposal dated
 * AFTER that count. With no count behind it, it is simply the movements.
 *
 * WHY THE BASELINE IS PER ASSET AND NOT PER COUNT. A count may legitimately
 * cover one category or one section of the outlet — "count the bar" — and an
 * asset that was not on that sheet was not counted zero, it was not counted at
 * all. Taking the baseline from the last count that actually carries a line for
 * that asset is what keeps a partial count from wiping everything it did not
 * mention.
 *
 * A MOVEMENT ON THE COUNT'S OWN DAY IS BROKEN BY WHEN IT WAS ENTERED. Count the
 * bar in the morning, take a delivery in the afternoon, book it in: by date
 * alone those two cannot be ordered, and whichever way you rule, one real
 * scenario is silently wrong. "The count wins its day" loses that afternoon
 * delivery until the next count; "movements always add" double-counts a
 * delivery booked in BEFORE the count that walked past it.
 *
 * So the tie is broken by `created_at` against when the count was last saved:
 * a movement entered after the count was completed happened after it, and one
 * entered before was already in front of the person counting. That is true of
 * both scenarios above, because in both the person books the movement when it
 * happens and completes the count when they finish counting.
 *
 * The case it still cannot see is paperwork entered late for a day that was
 * counted — a delivery note for count day, keyed in a week later, is read as
 * arriving after the count. That is genuinely ambiguous rather than merely
 * undecided, it is rare, and it errs towards showing MORE than is there, which
 * the next count corrects. Dates that differ never reach this at all.
 *
 * NOTHING HERE IS STORED. There is no balance column on `assets` and none on
 * the movements, so there is nothing that can drift out of step with the
 * documents — the cost of which is that this recomputes, which is why every
 * method takes a whole outlet at once rather than being called per row.
 */
class AssetOnHandService
{
    /**
     * Quantity on hand per asset, for one outlet.
     *
     * @param  array<int, int>|null  $assetIds  narrow the work to these assets; null = every asset with history
     * @return array<int, float>  asset id => quantity
     */
    public function quantities(int $outletId, ?array $assetIds = null, ?int $companyId = null): array
    {
        return $this->forOutlets([$outletId], $assetIds, $companyId)[$outletId] ?? [];
    }

    /** Quantity on hand for a single asset at a single outlet. */
    public function quantity(int $assetId, int $outletId, ?int $companyId = null): float
    {
        return $this->quantities($outletId, [$assetId], $companyId)[$assetId] ?? 0.0;
    }

    /**
     * Quantity on hand per asset, for several outlets at once.
     *
     * @param  array<int, int>  $outletIds
     * @param  array<int, int>|null  $assetIds
     * @return array<int, array<int, float>>  outlet id => [asset id => quantity]
     */
    public function forOutlets(array $outletIds, ?array $assetIds = null, ?int $companyId = null): array
    {
        $outletIds = array_values(array_unique(array_map('intval', $outletIds)));

        if ($outletIds === []) {
            return [];
        }

        $companyId ??= Auth::user()?->company_id;

        $baselines = $this->baselines($outletIds, $assetIds, $companyId);
        $movements = $this->movementLines($outletIds, $assetIds, $companyId);

        $out = array_fill_keys($outletIds, []);

        foreach ($baselines as $outletId => $perAsset) {
            foreach ($perAsset as $assetId => $baseline) {
                $out[$outletId][$assetId] = $baseline['quantity'];
            }
        }

        foreach ($movements as $row) {
            $outletId = (int) $row->outlet_id;
            $assetId  = (int) $row->asset_id;

            $baseline = $baselines[$outletId][$assetId] ?? null;

            if ($baseline !== null && ! $this->isAfter($row, $baseline)) {
                continue;
            }

            $sign = $row->movement_type === AssetMovement::TYPE_RECEIPT ? 1 : -1;

            $out[$outletId][$assetId] = ($out[$outletId][$assetId] ?? 0.0)
                + ($sign * (float) $row->quantity);
        }

        foreach ($out as $outletId => $perAsset) {
            $out[$outletId] = array_map(fn ($q) => round((float) $q, 4), $perAsset);
        }

        return $out;
    }

    /**
     * When each asset was last counted at each outlet, and what was found.
     *
     * Reduced in PHP rather than with a per-asset correlated subquery: the rows
     * come back oldest first and each write overwrites the one before it, so the
     * last write per (outlet, asset) wins. One query, and it reads the same on
     * MySQL and on the SQLite the tests run against — which a window function
     * would not.
     *
     * @return array<int, array<int, array{quantity: float, date: string, completed_at: ?string}>>
     */
    public function baselines(array $outletIds, ?array $assetIds = null, ?int $companyId = null): array
    {
        $companyId ??= Auth::user()?->company_id;

        $rows = AssetCountLine::query()
            ->join('asset_counts', 'asset_counts.id', '=', 'asset_count_lines.asset_count_id')
            ->whereNull('asset_counts.deleted_at')
            ->where('asset_counts.status', AssetCount::STATUS_COMPLETED)
            ->whereIn('asset_counts.outlet_id', $outletIds)
            ->when($companyId, fn ($q) => $q->where('asset_counts.company_id', $companyId))
            ->when($assetIds, fn ($q) => $q->whereIn('asset_count_lines.asset_id', array_map('intval', $assetIds)))
            ->orderBy('asset_counts.count_date')
            ->orderBy('asset_counts.id')
            ->get([
                'asset_counts.outlet_id',
                'asset_count_lines.asset_id',
                'asset_count_lines.counted_quantity',
                'asset_counts.count_date',
                // When the count was last saved, which is what breaks a tie
                // with a movement dated the same day. A recount through reopen
                // moves this, and should: the later count supersedes.
                'asset_counts.updated_at as completed_at',
            ]);

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->outlet_id][(int) $row->asset_id] = [
                'quantity'     => (float) $row->counted_quantity,
                'date'         => $this->day($row->count_date),
                'completed_at' => $row->completed_at ? (string) $row->completed_at : null,
            ];
        }

        return $out;
    }

    /** Every receipt and disposal line for these outlets; the caller decides which ones count. */
    private function movementLines(array $outletIds, ?array $assetIds, ?int $companyId)
    {
        return AssetMovementLine::query()
            ->join('asset_movements', 'asset_movements.id', '=', 'asset_movement_lines.asset_movement_id')
            ->whereNull('asset_movements.deleted_at')
            ->whereIn('asset_movements.outlet_id', $outletIds)
            ->when($companyId, fn ($q) => $q->where('asset_movements.company_id', $companyId))
            ->when($assetIds, fn ($q) => $q->whereIn('asset_movement_lines.asset_id', array_map('intval', $assetIds)))
            ->get([
                'asset_movements.outlet_id',
                'asset_movements.movement_type',
                'asset_movements.movement_date',
                'asset_movements.created_at',
                'asset_movement_lines.asset_id',
                'asset_movement_lines.quantity',
            ]);
    }

    /**
     * Did this movement happen after the count that is this asset's baseline?
     *
     * Different days answer themselves. The same day is broken by when each was
     * entered, for the reasons set out on the class — and a count with no saved
     * timestamp at all (only reachable through a hand-written fixture) falls
     * back to letting the movement count, which is the direction that shows
     * more rather than quietly losing a document.
     *
     * @param  array{quantity: float, date: string, completed_at: ?string}  $baseline
     */
    private function isAfter(object $movement, array $baseline): bool
    {
        $movementDay = $this->day($movement->movement_date);

        if ($movementDay !== $baseline['date']) {
            return $movementDay > $baseline['date'];
        }

        if ($baseline['completed_at'] === null || $movement->created_at === null) {
            return true;
        }

        // Through strtotime rather than compared as strings: one side arrives as
        // a Carbon (created_at is a magic timestamp on the line model) and the
        // other as whatever the driver printed for an aliased column, and two
        // formats that disagree would compare as text without complaining.
        return strtotime((string) $movement->created_at) > strtotime((string) $baseline['completed_at']);
    }

    /**
     * A date column as YYYY-MM-DD, whatever the driver handed back.
     *
     * MySQL returns a plain date, SQLite a datetime string; both compare
     * correctly as strings once they are the same ten characters.
     */
    private function day($value): string
    {
        return substr((string) $value, 0, 10);
    }
}
