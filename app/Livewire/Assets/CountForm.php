<?php

namespace App\Livewire\Assets;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetCount;
use App\Models\Department;
use App\Services\AssetOnHandService;
use App\Traits\PicksRecordOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The asset inventory count — walk the outlet, tick off what is there.
 *
 * Built like StockTakeForm because it is read and used the same way: pick the
 * outlet and the date, pull the items in (one at a time, a whole category, or
 * everything), type what you found, file it.
 *
 * WHAT IT ADDS OVER A STOCK TAKE. Completing this one MOVES THE BASELINE — see
 * AssetOnHandService. So an asset left blank is not the same as an asset
 * counted zero, and the form keeps them apart: only the rows you actually put a
 * number against are stored on completion, and a row you meant to be zero is a
 * typed 0, which is a variance you can see and explain.
 *
 * UNIT COST IS SHOWN, NEVER ENTERED — the same rule as every other stock form
 * in the product, for the same reason (App\Traits\LocksLineUnitCost sets it out
 * in full). `lines` is a public property the browser can write to, so the cost
 * that gets stored comes from the #[Locked] map below, which refuses client
 * updates, and never from the submitted row.
 */
class CountForm extends Component
{
    use PicksRecordOutlet;

    public ?int $recordId = null;

    public ?int   $department_id    = null;
    public string $count_date       = '';
    public string $reference_number = '';
    public string $notes            = '';
    public string $status           = 'draft';

    /** @var array<int, array<string, mixed>> */
    public array $lines = [];

    public string $assetSearch     = '';
    public string $loadCategoryId  = '';
    public bool   $hideSystemQty   = false;

    /** @var array<string, float> asset key => unit cost, server-authored only */
    #[Locked]
    public array $lineCosts = [];

    protected function rules(): array
    {
        return [
            'outlet_id'                  => 'required|integer',
            'count_date'                 => 'required|date',
            'reference_number'           => 'nullable|string|max:100',
            'department_id'              => 'nullable|exists:departments,id',
            'notes'                      => 'nullable|string',
            'lines'                      => 'required|array|min:1',
            'lines.*.counted_quantity'   => 'nullable|numeric|min:0',
        ];
    }

    protected function messages(): array
    {
        return [
            'outlet_id.required'            => 'Select an outlet for this count.',
            'count_date.required'           => 'Enter the date this count was taken.',
            'lines.required'                => 'Add at least one asset.',
            'lines.min'                     => 'Add at least one asset.',
            'lines.*.counted_quantity.min'  => 'A counted quantity cannot be negative.',
        ];
    }

    public function mount(?int $id = null): void
    {
        $this->count_date = now()->toDateString();

        if (! $id) {
            $this->initOutlet();
            return;
        }

        $record = AssetCount::with(['lines.asset.uom', 'lines.asset.category'])->findOrFail($id);

        abort_unless(Auth::user()->canAccessOutlet($record->outlet_id), 403);

        $this->recordId         = $record->id;
        $this->initOutlet($record->outlet_id);
        $this->department_id    = $record->department_id;
        $this->count_date       = $record->count_date->toDateString();
        $this->reference_number = $record->reference_number ?? '';
        $this->notes            = $record->notes ?? '';
        $this->status           = $record->status;

        // A draft re-reads what the system expects and re-prices off the live
        // asset cost, because both may have moved since it was started. A
        // completed count is a filed record and keeps its own snapshot.
        $isDraft = ! $record->isCompleted();

        $systemQuantities = $isDraft
            ? app(AssetOnHandService::class)->quantities(
                (int) $record->outlet_id,
                $record->lines->pluck('asset_id')->all()
            )
            : [];

        foreach ($record->lines as $line) {
            $unitCost = $isDraft && $line->asset
                ? (float) $line->asset->unit_cost
                : (float) $line->unit_cost;

            $systemQty = $isDraft
                ? ($systemQuantities[$line->asset_id] ?? 0.0)
                : (float) $line->system_quantity;

            $this->lines[] = $this->rememberCost([
                'asset_id'         => $line->asset_id,
                'asset_name'       => $line->asset?->name ?? '(Deleted asset)',
                'code'             => $line->asset?->code,
                'category'         => $line->asset?->category?->name,
                'uom'              => $line->asset?->uom?->abbreviation ?? '',
                'system_quantity'  => $systemQty,
                'counted_quantity' => (string) floatval($line->counted_quantity),
                'notes'            => $line->notes ?? '',
            ], $unitCost);
        }
    }

    public function isEditable(): bool
    {
        return $this->status !== AssetCount::STATUS_COMPLETED;
    }

    // ── Locked unit cost ──────────────────────────────────────────────────

    private function costKey(array $line): string
    {
        return 'asset:' . (int) ($line['asset_id'] ?? 0);
    }

    private function rememberCost(array $line, float $cost): array
    {
        $cost = round($cost, 4);

        $this->lineCosts[$this->costKey($line)] = $cost;
        $line['unit_cost'] = $cost;

        return $line;
    }

    /** The cost to store. A miss re-reads the asset; it never trusts the row. */
    private function lockedCost(array $line): float
    {
        $key = $this->costKey($line);

        if (isset($this->lineCosts[$key])) {
            return (float) $this->lineCosts[$key];
        }

        return round((float) (Asset::whereKey($line['asset_id'] ?? null)->value('unit_cost') ?? 0), 4);
    }

    // ── Building the sheet ────────────────────────────────────────────────

    public function addAsset(int $assetId): void
    {
        $this->assetSearch = '';

        foreach ($this->lines as $line) {
            if ((int) $line['asset_id'] === $assetId) {
                return;
            }
        }

        $asset = Asset::with(['uom', 'category'])->find($assetId);

        if (! $asset) {
            return;
        }

        $this->lines[] = $this->buildLine($asset);
    }

    /** Everything active, or everything in one category — the usual way a sheet starts. */
    public function loadAll(?string $categoryId = null): void
    {
        $categoryId ??= $this->loadCategoryId;

        $existing = collect($this->lines)->pluck('asset_id')->map(fn ($id) => (int) $id)->all();

        $query = Asset::with(['uom', 'category'])->active();

        if ($categoryId) {
            $cat = AssetCategory::with('children')->find((int) $categoryId);

            if ($cat) {
                $query->whereIn('asset_category_id', $cat->children->pluck('id')->push($cat->id)->all());
            }
        }

        $assets = $query->when($existing, fn ($q) => $q->whereNotIn('id', $existing))
            ->orderBy('name')->get();

        if ($assets->isEmpty()) {
            session()->flash('info', 'Nothing to add — every matching asset is already on this count.');
            $this->loadCategoryId = '';
            return;
        }

        $systemQuantities = $this->systemQuantitiesFor($assets->pluck('id')->all());

        foreach ($assets as $asset) {
            $this->lines[] = $this->buildLine($asset, $systemQuantities[$asset->id] ?? 0.0);
        }

        $this->loadCategoryId = '';
    }

    private function buildLine(Asset $asset, ?float $systemQuantity = null): array
    {
        $systemQuantity ??= $this->systemQuantitiesFor([$asset->id])[$asset->id] ?? 0.0;

        return $this->rememberCost([
            'asset_id'         => $asset->id,
            'asset_name'       => $asset->name,
            'code'             => $asset->code,
            'category'         => $asset->category?->name,
            'uom'              => $asset->uom?->abbreviation ?? '',
            'system_quantity'  => $systemQuantity,
            'counted_quantity' => '',
            'notes'            => '',
        ], (float) $asset->unit_cost);
    }

    /** @param array<int, int> $assetIds */
    private function systemQuantitiesFor(array $assetIds): array
    {
        if (! $this->outlet_id) {
            return [];
        }

        return app(AssetOnHandService::class)->quantities((int) $this->outlet_id, $assetIds);
    }

    /**
     * Changing the outlet re-reads what the system expects for every row.
     *
     * Rows are usually added before the outlet is settled, and a count sheet
     * showing the expected quantities of a different branch is worse than
     * showing none at all — every line would read as a variance.
     */
    public function updatedOutletId(): void
    {
        $assetIds = collect($this->lines)->pluck('asset_id')->map(fn ($id) => (int) $id)->all();

        if ($assetIds === []) {
            return;
        }

        $quantities = $this->systemQuantitiesFor($assetIds);

        foreach ($this->lines as $i => $line) {
            $this->lines[$i]['system_quantity'] = $quantities[(int) $line['asset_id']] ?? 0.0;
        }
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
    }

    public function clearLines(): void
    {
        $this->lines = [];
    }

    // ── Save ──────────────────────────────────────────────────────────────

    public function save(string $action = 'save')
    {
        // Re-checked here, not just on the route: a Livewire action is its own request.
        abort_unless(Auth::user()?->canDo('assets.counts.record'), 403);
        abort_unless($this->isEditable(), 403);

        $this->validate();

        $lines = collect($this->lines);

        if ($action === 'complete') {
            // A blank row was never counted, so it must not become a counted
            // zero — completing is what makes these numbers the new baseline.
            $lines = $lines->filter(fn ($l) => $l['counted_quantity'] !== '' && $l['counted_quantity'] !== null)->values();

            if ($lines->isEmpty()) {
                $this->addError('lines', 'Enter a counted quantity for at least one asset before completing.');
                return;
            }
        }

        $totalValue    = 0.0;
        $totalVariance = 0.0;

        $prepared = $lines->map(function ($line) use (&$totalValue, &$totalVariance) {
            $counted   = $line['counted_quantity'] === '' || $line['counted_quantity'] === null
                ? 0.0 : (float) $line['counted_quantity'];
            $system    = (float) ($line['system_quantity'] ?? 0);
            $unitCost  = $this->lockedCost($line);
            $variance  = round($counted - $system, 4);

            $lineValue    = round($counted * $unitCost, 4);
            $varianceCost = round($variance * $unitCost, 4);

            $totalValue    += $lineValue;
            $totalVariance += $varianceCost;

            return [
                'asset_id'          => (int) $line['asset_id'],
                'system_quantity'   => $system,
                'counted_quantity'  => $counted,
                'variance_quantity' => $variance,
                'unit_cost'         => $unitCost,
                'line_value'        => $lineValue,
                'variance_cost'     => $varianceCost,
                'notes'             => $line['notes'] ?: null,
            ];
        });

        $data = [
            'department_id'       => $this->department_id ?: null,
            'count_date'          => $this->count_date,
            'reference_number'    => $this->reference_number ?: null,
            'notes'               => $this->notes ?: null,
            'status'              => $action === 'complete' ? AssetCount::STATUS_COMPLETED : AssetCount::STATUS_DRAFT,
            'total_asset_value'   => round($totalValue, 4),
            'total_variance_cost' => round($totalVariance, 4),
        ];

        if ($this->recordId) {
            $record = AssetCount::findOrFail($this->recordId);
            $record->update($data);
        } else {
            $data['company_id'] = Auth::user()->company_id;
            $data['outlet_id']  = $this->resolveOutletId();
            $data['created_by'] = Auth::id();
            $record = AssetCount::create($data);
            $this->recordId = $record->id;
        }

        $record->lines()->delete();

        foreach ($prepared as $line) {
            $record->lines()->create($line);
        }

        $this->status = $record->status;

        session()->flash('success', $action === 'complete'
            ? 'Asset count completed. The register now values this outlet from these figures.'
            : 'Asset count saved as a draft.');

        return $this->redirect(route('assets.records', ['tab' => 'counts']), navigate: true);
    }

    /**
     * Put a completed count back in progress.
     *
     * Its own ability, `assets.counts.reopen`, exactly as stock takes have —
     * reopening leaves the record in place and only makes it editable again,
     * which is a lighter act than deleting it outright.
     *
     * Redirecting into itself re-runs mount(), so the reopened draft re-reads
     * the expected quantities and re-prices off live asset costs through the
     * one path that already does that, rather than a second copy of it here.
     */
    public function reopen()
    {
        abort_unless(Auth::user()?->canDo('assets.counts.reopen'), 403);

        $record = AssetCount::findOrFail($this->recordId);
        abort_unless($record->isCompleted(), 403);

        $record->update(['status' => AssetCount::STATUS_DRAFT]);

        session()->flash('success', 'Count reopened for editing.');

        return $this->redirect(route('assets.counts.show', $record->id), navigate: true);
    }

    public function render()
    {
        $searchResults = [];

        if (strlen($this->assetSearch) >= 2) {
            $onSheet = collect($this->lines)->pluck('asset_id')->map(fn ($id) => (int) $id)->all();
            $term    = '%' . $this->assetSearch . '%';

            $searchResults = Asset::with(['uom', 'category'])
                ->active()
                ->when($onSheet, fn ($q) => $q->whereNotIn('id', $onSheet))
                ->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                      ->orWhere('code', 'like', $term)
                      ->orWhere('brand', 'like', $term);
                })
                ->orderBy('name')
                ->limit(15)
                ->get();
        }

        $countedValue = 0.0;
        $varianceCost = 0.0;

        foreach ($this->lines as $line) {
            if ($line['counted_quantity'] === '' || $line['counted_quantity'] === null) {
                continue;
            }

            $counted = (float) $line['counted_quantity'];
            $cost    = $this->lockedCost($line);

            $countedValue += $counted * $cost;
            $varianceCost += ($counted - (float) ($line['system_quantity'] ?? 0)) * $cost;
        }

        return view('livewire.assets.count-form', [
            'searchResults' => $searchResults,
            'outlets'       => $this->outletOptions(),
            'hasOutletChoice' => $this->hasOutletChoice(),
            'departments'   => Department::selectable($this->department_id)->orderBy('sort_order')->get(),
            'categories'    => AssetCategory::ordered()->get(),
            'isEditable'    => $this->isEditable(),
            'countedValue'  => round($countedValue, 2),
            'varianceCost'  => round($varianceCost, 2),
            'countedLines'  => collect($this->lines)
                ->filter(fn ($l) => $l['counted_quantity'] !== '' && $l['counted_quantity'] !== null)
                ->count(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), [
            'title' => $this->recordId ? 'Asset Count' : 'New Asset Count',
        ]);
    }
}
