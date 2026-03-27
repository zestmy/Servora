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
    }

    public function selectAll(array $ids): void
    {
        $this->selectedPrIds = $ids;
        $this->showPreview = false;
        $this->preview = [];
    }

    public function deselectAll(): void
    {
        $this->selectedPrIds = [];
        $this->showPreview = false;
        $this->preview = [];
    }

    public function generatePreview(): void
    {
        if (empty($this->selectedPrIds)) return;

        $grouped = PurchaseRequestService::consolidationPreview($this->selectedPrIds);

        $this->preview = [];
        foreach ($grouped as $supplierId => $data) {
            $lines = [];
            foreach ($data['lines'] as $line) {
                $lines[] = [
                    'ingredient_id'   => $line['ingredient_id'],
                    'ingredient_name' => $line['ingredient_name'],
                    'quantity'        => $line['quantity'],
                    'uom'            => $line['uom'],
                ];
            }

            $this->preview[] = [
                'supplier_id'   => $supplierId,
                'supplier_name' => $data['supplier']?->name ?? 'No Supplier Assigned',
                'lines'         => $lines,
                'outlet_count'  => $data['outlet_ids']->unique()->count(),
            ];
        }

        $this->showPreview = true;
    }

    public function consolidate()
    {
        if (empty($this->selectedPrIds) || ! $this->cpuId) {
            session()->flash('error', 'Select at least one purchase request.');
            return;
        }

        $createdPoIds = PurchaseRequestService::consolidate($this->selectedPrIds, $this->cpuId);

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
        ])->layout('layouts.app', ['title' => 'Consolidate Purchase Requests']);
    }
}
