<?php

namespace App\Livewire\Purchasing;

use App\Models\CentralPurchasingUnit;
use App\Models\PurchaseRequest;
use App\Services\PurchaseRequestService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ConsolidateForm extends Component
{
    public array $selectedPrIds = [];
    public ?int  $cpuId         = null;
    public bool  $showPreview   = false;
    public array $preview       = [];

    // Smart Review state
    public bool  $editMode          = false;
    public array $editablePreview   = [];
    public array $supplierOptions   = [];
    public array $kitchenOptions    = [];
    public array $costLookup        = [];
    public array $taxLookup         = [];

    public function mount(): void
    {
        $cpu = Auth::user()->company?->cpus()->where('is_active', true)->first();
        $this->cpuId = $cpu?->id;
    }

    public function togglePr(int $id): void
    {
        if (in_array($id, $this->selectedPrIds)) {
            $this->selectedPrIds = array_values(array_diff($this->selectedPrIds, [$id]));
        } else {
            $this->selectedPrIds[] = $id;
        }
        $this->showPreview = false;
        $this->preview = [];
        $this->editMode = false;
        $this->editablePreview = [];
    }

    public function selectAll(array $ids): void
    {
        $this->selectedPrIds = $ids;
        $this->showPreview = false;
        $this->preview = [];
        $this->editMode = false;
        $this->editablePreview = [];
    }

    public function deselectAll(): void
    {
        $this->selectedPrIds = [];
        $this->showPreview = false;
        $this->preview = [];
        $this->editMode = false;
        $this->editablePreview = [];
    }

    public function generatePreview(): void
    {
        if (empty($this->selectedPrIds)) return;

        $data = PurchaseRequestService::consolidationPreviewWithCosts($this->selectedPrIds);

        $this->editablePreview = $data['groups'];
        $this->costLookup      = $data['cost_lookup'];
        $this->taxLookup       = $data['tax_lookup'];
        $this->supplierOptions = $data['supplier_options'];
        $this->kitchenOptions  = $data['kitchen_options'];

        // Also build the simple preview for quick view
        $this->preview = collect($data['groups'])->map(fn ($g) => [
            'supplier_id'   => $g['supplier_id'],
            'supplier_name' => $g['supplier_name'],
            'lines'         => $g['lines'],
            'outlet_count'  => count($g['outlet_ids']),
            'po_total'      => $g['po_total'],
            'tax_total'     => $g['tax_total'],
        ])->toArray();

        $this->showPreview = true;
    }

    public function enterEditMode(): void
    {
        $this->editMode = true;
    }

    public function exitEditMode(): void
    {
        $this->editMode = false;
    }

    public function updateLineSupplier(int $groupIdx, int $lineIdx, int $newSupplierId): void
    {
        if (! isset($this->editablePreview[$groupIdx]['lines'][$lineIdx])) return;

        $line = &$this->editablePreview[$groupIdx]['lines'][$lineIdx];
        $ingredientId = $line['ingredient_id'];

        // Update supplier and cost
        $line['supplier_id'] = $newSupplierId;
        $line['unit_cost'] = $this->costLookup[$ingredientId][$newSupplierId] ?? $line['unit_cost'];
        $this->recalcLine($groupIdx, $lineIdx);

        // Regroup after supplier change
        $this->regroupPreview();
    }

    public function updatedEditablePreview($value, $key): void
    {
        // Handle quantity changes via wire:model
        $parts = explode('.', $key);
        // Expected: groups.{groupIdx}.lines.{lineIdx}.quantity
        if (count($parts) >= 4 && $parts[1] === 'lines' && ($parts[3] ?? '') === 'quantity') {
            $groupIdx = (int) $parts[0];
            $lineIdx = (int) $parts[2];
            // For deeply nested wire:model, path is like: {groupIdx}.lines.{lineIdx}.quantity
            $this->recalcLine($groupIdx, $lineIdx);
        }
    }

    public function toggleLineExclusion(int $groupIdx, int $lineIdx): void
    {
        if (! isset($this->editablePreview[$groupIdx]['lines'][$lineIdx])) return;
        $this->editablePreview[$groupIdx]['lines'][$lineIdx]['excluded'] =
            ! ($this->editablePreview[$groupIdx]['lines'][$lineIdx]['excluded'] ?? false);
        $this->recalcGroupTotals($groupIdx);
    }

    public function consolidate()
    {
        if (empty($this->selectedPrIds) || ! $this->cpuId) {
            session()->flash('error', 'Select at least one purchase request.');
            return;
        }

        if ($this->editMode && ! empty($this->editablePreview)) {
            $createdPoIds = PurchaseRequestService::consolidateFromCustomized(
                $this->editablePreview,
                $this->cpuId,
                $this->selectedPrIds
            );
        } else {
            $createdPoIds = PurchaseRequestService::consolidate($this->selectedPrIds, $this->cpuId);
        }

        if (empty($createdPoIds)) {
            session()->flash('error', 'No POs created — check that selected PRs have ingredients with preferred suppliers assigned.');
            return;
        }

        $count = count($createdPoIds);
        session()->flash('success', "{$count} Purchase Order(s) created from consolidated requests.");

        return $this->redirect(route('purchasing.index', ['tab' => 'po']), navigate: true);
    }

    public function render()
    {
        $approvedPrs = PurchaseRequest::with(['outlet', 'lines.ingredient', 'lines.preferredSupplier', 'createdBy', 'department'])
            ->where('status', PurchaseRequest::STATUS_APPROVED)
            ->orderByDesc('requested_date')
            ->get();

        $cpus = CentralPurchasingUnit::where('is_active', true)->get();

        return view('livewire.purchasing.consolidate-form', [
            'approvedPrs' => $approvedPrs,
            'cpus'        => $cpus,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Consolidate Purchase Requests']);
    }

    public function recalcLinePublic(int $groupIdx, int $lineIdx): void
    {
        $this->recalcLine($groupIdx, $lineIdx);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function recalcLine(int $groupIdx, int $lineIdx): void
    {
        if (! isset($this->editablePreview[$groupIdx]['lines'][$lineIdx])) return;
        $line = &$this->editablePreview[$groupIdx]['lines'][$lineIdx];
        $totalCost = round(floatval($line['quantity']) * floatval($line['unit_cost']), 4);
        $taxPct = floatval($line['tax_rate_pct'] ?? 0);
        $line['total_cost'] = $totalCost;
        $line['tax_amount'] = $taxPct > 0 ? round($totalCost * ($taxPct / 100), 4) : 0;
        $this->recalcGroupTotals($groupIdx);
    }

    private function recalcGroupTotals(int $groupIdx): void
    {
        if (! isset($this->editablePreview[$groupIdx])) return;
        $activeLines = collect($this->editablePreview[$groupIdx]['lines'])
            ->filter(fn ($l) => ! ($l['excluded'] ?? false));
        $this->editablePreview[$groupIdx]['po_total'] = round($activeLines->sum('total_cost'), 2);
        $this->editablePreview[$groupIdx]['tax_total'] = round($activeLines->sum('tax_amount'), 2);
    }

    private function regroupPreview(): void
    {
        // Collect all non-excluded lines and regroup by supplier_id
        $allLines = [];
        $outletsBySupplier = [];

        foreach ($this->editablePreview as $group) {
            foreach ($group['lines'] as $line) {
                $sid = $line['supplier_id'] ?? 0;
                $allLines[$sid][] = $line;
                if (! isset($outletsBySupplier[$sid])) {
                    $outletsBySupplier[$sid] = $group['outlet_ids'] ?? [];
                } else {
                    $outletsBySupplier[$sid] = array_values(array_unique(
                        array_merge($outletsBySupplier[$sid], $group['outlet_ids'] ?? [])
                    ));
                }
            }
        }

        $supplierNameMap = collect($this->supplierOptions)->pluck('name', 'id')->toArray();

        $newGroups = [];
        foreach ($allLines as $sid => $lines) {
            $group = [
                'supplier_id'   => $sid,
                'supplier_name' => $supplierNameMap[$sid] ?? 'No Supplier',
                'lines'         => array_values($lines),
                'outlet_ids'    => $outletsBySupplier[$sid] ?? [],
                'po_total'      => 0,
                'tax_total'     => 0,
            ];

            $activeLines = collect($lines)->filter(fn ($l) => ! ($l['excluded'] ?? false));
            $group['po_total'] = round($activeLines->sum('total_cost'), 2);
            $group['tax_total'] = round($activeLines->sum('tax_amount'), 2);

            $newGroups[] = $group;
        }

        $this->editablePreview = $newGroups;
    }
}
