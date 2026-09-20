<?php

namespace App\Livewire\Assets;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Services\AssetOnHandService;
use App\Traits\PicksRecordOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Booking assets in when they arrive, and out when they are disposed of.
 *
 * One form, two documents. A receipt names a supplier and a price; a disposal
 * names a reason and spends the cost the asset already carries. Everything else
 * — outlet, date, reference, lines — is identical, and keeping them in one form
 * is what stops the two drifting into different ideas of what a line is.
 *
 * COST IS TYPED ON A RECEIPT, and that is deliberate — unlike every stock form
 * in the product, which shows a cost somebody else set. A receipt IS the
 * purchasing document for an asset: the invoice is in the buyer's hand and the
 * price on it is the fact being recorded, not a figure being second-guessed.
 * Completing one also writes that price back onto the asset and its supplier
 * link, which is how the catalogue's unit cost stays current without anybody
 * maintaining it by hand.
 *
 * A DISPOSAL NEVER ASKS FOR A PRICE. What a broken plate was worth is what the
 * register has been carrying it at, so the line takes the asset's unit cost and
 * the form does not offer to change it — a typed figure there would silently
 * move asset value with no document behind it.
 */
class MovementForm extends Component
{
    use PicksRecordOutlet;

    public ?int $recordId = null;

    public string $movement_type    = AssetMovement::TYPE_RECEIPT;
    public string $movement_date    = '';
    public string $reference_number = '';
    public ?int   $supplier_id      = null;
    public ?int   $department_id    = null;
    public ?int   $purchase_request_id = null;
    public string $reason           = '';
    public string $notes            = '';

    /** @var array<int, array<string, mixed>> */
    public array $lines = [];

    public string $assetSearch = '';

    public function mount(?int $id = null, ?string $type = null): void
    {
        $this->movement_date = now()->toDateString();

        if (! $id) {
            // From the querystring, not a route parameter: both forms live at
            // /assets/movements/create and differ by ?type=, so Livewire has
            // nothing to hand mount() here. A missing or unknown value is a
            // receipt, which is the one somebody reaches for by accident least
            // destructively.
            $type ??= request('type');

            $this->movement_type = in_array($type, [AssetMovement::TYPE_RECEIPT, AssetMovement::TYPE_DISPOSAL], true)
                ? $type
                : AssetMovement::TYPE_RECEIPT;

            $this->initOutlet();
            $this->purchase_request_id = (int) request('pr') ?: null;
            $this->prefillFromPurchaseRequest();

            return;
        }

        $record = AssetMovement::with(['lines.asset.uom'])->findOrFail($id);

        abort_unless(Auth::user()->canAccessOutlet($record->outlet_id), 403);

        $this->recordId            = $record->id;
        $this->movement_type       = $record->movement_type;
        $this->initOutlet($record->outlet_id);
        $this->movement_date       = $record->movement_date->toDateString();
        $this->reference_number    = $record->reference_number ?? '';
        $this->supplier_id         = $record->supplier_id;
        $this->department_id       = $record->department_id;
        $this->purchase_request_id = $record->purchase_request_id;
        $this->reason              = $record->reason ?? '';
        $this->notes               = $record->notes ?? '';

        foreach ($record->lines as $line) {
            $this->lines[] = [
                'asset_id'   => $line->asset_id,
                'asset_name' => $line->asset?->name ?? '(Deleted asset)',
                'uom'        => $line->asset?->uom?->abbreviation ?? '',
                'quantity'   => (string) floatval($line->quantity),
                'unit_cost'  => (string) floatval($line->unit_cost),
                'notes'      => $line->notes ?? '',
            ];
        }
    }

    public function isReceipt(): bool
    {
        return $this->movement_type === AssetMovement::TYPE_RECEIPT;
    }

    protected function rules(): array
    {
        $rules = [
            'outlet_id'           => 'required|integer',
            'movement_type'       => 'required|in:receipt,disposal',
            'movement_date'       => 'required|date',
            'reference_number'    => 'nullable|string|max:100',
            'department_id'       => 'nullable|exists:departments,id',
            'notes'               => 'nullable|string',
            'lines'               => 'required|array|min:1',
            'lines.*.asset_id'    => 'required|exists:assets,id',
            'lines.*.quantity'    => 'required|numeric|min:0.0001',
        ];

        if ($this->isReceipt()) {
            $rules['supplier_id']       = 'nullable|exists:suppliers,id';
            $rules['lines.*.unit_cost'] = 'required|numeric|min:0';
        } else {
            $rules['reason'] = 'required|string|max:60';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'outlet_id.required'        => 'Select the outlet these assets are at.',
            'lines.required'            => 'Add at least one asset.',
            'lines.min'                 => 'Add at least one asset.',
            'lines.*.quantity.min'      => 'Quantity must be greater than zero.',
            'lines.*.unit_cost.required' => 'Enter what each one cost.',
            'reason.required'           => 'Say why these assets are being written off.',
        ];
    }

    // ── Lines ─────────────────────────────────────────────────────────────

    public function addAsset(int $assetId): void
    {
        $this->assetSearch = '';

        foreach ($this->lines as $line) {
            if ((int) $line['asset_id'] === $assetId) {
                return;
            }
        }

        $asset = Asset::with('uom')->find($assetId);

        if (! $asset) {
            return;
        }

        // On a receipt the supplier's own last price beats the catalogue's, when
        // there is one: it is the more recent fact about what this vendor charges.
        $unitCost = (float) $asset->unit_cost;

        if ($this->isReceipt() && $this->supplier_id) {
            $supplierCost = $asset->supplierLinks()
                ->where('supplier_id', $this->supplier_id)
                ->value('last_cost');

            if ($supplierCost !== null) {
                $unitCost = (float) $supplierCost;
            }
        }

        $this->lines[] = [
            'asset_id'   => $asset->id,
            'asset_name' => $asset->name,
            'uom'        => $asset->uom?->abbreviation ?? '',
            'quantity'   => '1',
            'unit_cost'  => (string) round($unitCost, 4),
            'notes'      => '',
        ];
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
    }

    /**
     * Switching supplier re-prices the rows that are still at a catalogue price.
     *
     * Only the untouched ones: a price the buyer has typed off the invoice in
     * front of them is the document, and no dropdown should overwrite it.
     */
    public function updatedSupplierId(): void
    {
        if (! $this->isReceipt() || ! $this->supplier_id) {
            return;
        }

        $assetIds = collect($this->lines)->pluck('asset_id')->map(fn ($id) => (int) $id)->all();

        if ($assetIds === []) {
            return;
        }

        $assets = Asset::with('supplierLinks')->whereIn('id', $assetIds)->get()->keyBy('id');

        foreach ($this->lines as $i => $line) {
            $asset = $assets[(int) $line['asset_id']] ?? null;

            if (! $asset) {
                continue;
            }

            // "Untouched" = still showing the catalogue cost this row was built with.
            if (abs((float) $line['unit_cost'] - (float) $asset->unit_cost) > 0.00005) {
                continue;
            }

            $supplierCost = $asset->supplierLinks->firstWhere('supplier_id', (int) $this->supplier_id)?->last_cost;

            if ($supplierCost !== null) {
                $this->lines[$i]['unit_cost'] = (string) round((float) $supplierCost, 4);
            }
        }
    }

    /**
     * Pull the asset lines off an approved purchase request.
     *
     * This is the other half of raising a request for an asset: the request
     * said what was wanted, the receipt says what turned up. Quantities carry
     * across; costs do not, because a request has no prices on it — they come
     * from the asset's supplier price, and the buyer overwrites them from the
     * invoice.
     */
    private function prefillFromPurchaseRequest(): void
    {
        if (! $this->purchase_request_id) {
            return;
        }

        $pr = PurchaseRequest::with(['lines.asset.uom', 'lines.preferredSupplier'])
            ->find($this->purchase_request_id);

        if (! $pr) {
            $this->purchase_request_id = null;
            return;
        }

        abort_unless(! $pr->outlet_id || Auth::user()->canAccessOutlet($pr->outlet_id), 403);

        if ($pr->outlet_id) {
            $this->initOutlet($pr->outlet_id);
        }

        $assetLines = $pr->lines->filter(fn ($l) => $l->asset_id);

        $this->supplier_id      = $assetLines->firstWhere('preferred_supplier_id', '!=', null)?->preferred_supplier_id;
        $this->reference_number = $pr->pr_number;

        foreach ($assetLines as $line) {
            if (! $line->asset) {
                continue;
            }

            $this->addAsset((int) $line->asset_id);

            if ($this->lines !== []) {
                $this->lines[count($this->lines) - 1]['quantity'] = (string) floatval($line->quantity);
            }
        }
    }

    // ── Save ──────────────────────────────────────────────────────────────

    public function save()
    {
        // Re-checked here, not just on the route: a Livewire action is its own request.
        abort_unless(Auth::user()?->canDo('assets.movements.record'), 403);

        $this->validate();

        $isReceipt = $this->isReceipt();
        $costs     = $isReceipt ? [] : $this->catalogueCosts();

        $total    = 0.0;
        $prepared = [];

        foreach ($this->lines as $line) {
            $assetId  = (int) $line['asset_id'];
            $quantity = (float) $line['quantity'];

            // A disposal is priced from the catalogue, never from the wire.
            $unitCost = $isReceipt
                ? round((float) $line['unit_cost'], 4)
                : ($costs[$assetId] ?? 0.0);

            $lineTotal = round($quantity * $unitCost, 4);
            $total    += $lineTotal;

            $prepared[] = [
                'asset_id'   => $assetId,
                'quantity'   => $quantity,
                'unit_cost'  => $unitCost,
                'total_cost' => $lineTotal,
                'notes'      => $line['notes'] ?: null,
            ];
        }

        $data = [
            'movement_type'       => $this->movement_type,
            'movement_date'       => $this->movement_date,
            'reference_number'    => $this->reference_number ?: null,
            'supplier_id'         => $isReceipt ? ($this->supplier_id ?: null) : null,
            'purchase_request_id' => $isReceipt ? ($this->purchase_request_id ?: null) : null,
            'department_id'       => $this->department_id ?: null,
            'reason'              => $isReceipt ? null : ($this->reason ?: null),
            'notes'               => $this->notes ?: null,
            'total_cost'          => round($total, 4),
        ];

        if ($this->recordId) {
            $record = AssetMovement::findOrFail($this->recordId);
            $record->update($data);
        } else {
            $data['company_id'] = Auth::user()->company_id;
            $data['outlet_id']  = $this->resolveOutletId();
            $data['created_by'] = Auth::id();
            $record = AssetMovement::create($data);
            $this->recordId = $record->id;
        }

        $record->lines()->delete();

        foreach ($prepared as $line) {
            $record->lines()->create($line);
        }

        if ($isReceipt) {
            $this->writeBackCosts($prepared);
        }

        session()->flash('success', $isReceipt
            ? 'Assets received. The register now counts them at this outlet.'
            : 'Disposal recorded. The register no longer counts these.');

        return $this->redirect(route('assets.records', [
            'tab' => $isReceipt ? 'receipts' : 'disposals',
        ]), navigate: true);
    }

    /** @return array<int, float> asset id => catalogue unit cost */
    private function catalogueCosts(): array
    {
        $ids = collect($this->lines)->pluck('asset_id')->map(fn ($id) => (int) $id)->all();

        return Asset::whereIn('id', $ids)->pluck('unit_cost', 'id')
            ->map(fn ($c) => round((float) $c, 4))->all();
    }

    /**
     * What was actually paid becomes what the asset costs.
     *
     * Behind `assets.cost`, because it is exactly the write that ability
     * governs on the Asset List — somebody who may book a delivery in is not
     * automatically somebody who may reprice the catalogue. Without it the
     * receipt still files at the price on the invoice; only the catalogue is
     * left alone.
     */
    private function writeBackCosts(array $lines): void
    {
        if (! Auth::user()?->canDo('assets.cost')) {
            return;
        }

        foreach ($lines as $line) {
            $asset = Asset::find($line['asset_id']);

            if (! $asset) {
                continue;
            }

            $asset->update(['unit_cost' => $line['unit_cost']]);

            if ($this->supplier_id) {
                $asset->supplierLinks()->updateOrCreate(
                    ['supplier_id' => (int) $this->supplier_id],
                    ['last_cost' => $line['unit_cost']]
                );
            }
        }
    }

    public function render()
    {
        $searchResults = [];

        if (strlen($this->assetSearch) >= 2) {
            $onForm = collect($this->lines)->pluck('asset_id')->map(fn ($id) => (int) $id)->all();
            $term   = '%' . $this->assetSearch . '%';

            $searchResults = Asset::with('uom')
                ->active()
                ->when($onForm, fn ($q) => $q->whereNotIn('id', $onForm))
                ->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                      ->orWhere('code', 'like', $term)
                      ->orWhere('brand', 'like', $term);
                })
                ->orderBy('name')
                ->limit(15)
                ->get();
        }

        // What each line does to the outlet's holding, so the person booking a
        // disposal can see it would take the count negative before it does.
        $onHand = $this->outlet_id && $this->lines
            ? app(AssetOnHandService::class)->quantities(
                (int) $this->outlet_id,
                collect($this->lines)->pluck('asset_id')->map(fn ($id) => (int) $id)->all()
            )
            : [];

        $total = 0.0;

        foreach ($this->lines as $line) {
            $total += (float) $line['quantity'] * (float) $line['unit_cost'];
        }

        return view('livewire.assets.movement-form', [
            'searchResults'   => $searchResults,
            'outlets'         => $this->outletOptions(),
            'hasOutletChoice' => $this->hasOutletChoice(),
            'suppliers'       => Supplier::selectable($this->supplier_id)->orderBy('name')->get(),
            'departments'     => Department::selectable($this->department_id)->orderBy('sort_order')->get(),
            'onHand'          => $onHand,
            'total'           => round($total, 2),
            'isReceipt'       => $this->isReceipt(),
            'purchaseRequest' => $this->purchase_request_id
                ? PurchaseRequest::find($this->purchase_request_id)
                : null,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), [
            'title' => $this->isReceipt() ? 'Receive Assets' : 'Dispose of Assets',
        ]);
    }
}
