<?php

namespace App\Livewire\Purchasing;

use App\Models\CentralPurchasingUnit;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\QuotationRequest;
use App\Models\SupplierIngredient;
use App\Models\SupplierQuotation;
use App\Services\RfqService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RfqShow extends Component
{
    public int $rfqId;
    public ?QuotationRequest $rfq = null;

    // Add to ingredients modal
    public bool $showAddModal = false;
    public ?int $addFromQuotationId = null;
    public string $addTarget = 'outlet'; // 'outlet' or 'cpu'
    public ?int $addTargetOutletId = null;
    public array $addLines = [];

    public function mount(int $id): void
    {
        $this->rfqId = $id;
    }

    public function acceptQuotation(int $quotationId): void
    {
        $quotation = SupplierQuotation::where('quotation_request_id', $this->rfqId)
            ->findOrFail($quotationId);

        $po = RfqService::acceptAndCreatePo($quotation);

        session()->flash('success', "Quotation accepted — PO {$po->po_number} created.");
        $this->redirectRoute('purchasing.index');
    }

    /**
     * Open the "Add to Ingredients" modal for a quotation.
     */
    public function openAddToIngredients(int $quotationId): void
    {
        $quotation = SupplierQuotation::with('lines.ingredient', 'lines.uom')->findOrFail($quotationId);
        $this->addFromQuotationId = $quotationId;
        $this->addTargetOutletId = Auth::user()->activeOutletId();

        $supplierId = $quotation->supplier_id;

        $this->addLines = $quotation->lines->map(function ($line) use ($supplierId) {
            $existing = Ingredient::where('name', strtoupper($line->ingredient?->name ?? ''))->first();

            $mapped = $existing
                ? SupplierIngredient::where('supplier_id', $supplierId)
                    ->where('ingredient_id', $existing->id)->exists()
                : false;

            return [
                'quotation_line_id' => $line->id,
                'ingredient_id'     => $line->ingredient_id,
                'ingredient_name'   => $line->ingredient?->name ?? '—',
                'unit_price'        => floatval($line->unit_price),
                'uom_id'            => $line->uom_id,
                'uom_name'          => $line->uom?->abbreviation ?? '',
                'quantity'          => floatval($line->quantity),
                'price_type'        => $line->price_type,
                'selected'          => ! $mapped,
                'exists'            => (bool) $existing,
                'mapped'            => $mapped,
            ];
        })->toArray();

        $this->showAddModal = true;
    }

    /**
     * Add selected quotation items to company's ingredient list and create supplier mappings.
     */
    public function addToIngredients(): void
    {
        $quotation = SupplierQuotation::findOrFail($this->addFromQuotationId);
        $companyId = Auth::user()->company_id;
        $supplierId = $quotation->supplier_id;
        $added = 0;
        $linked = 0;

        foreach ($this->addLines as $line) {
            if (! ($line['selected'] ?? false)) continue;

            // Check if ingredient already exists in company by name
            $ingredient = Ingredient::where('name', strtoupper($line['ingredient_name']))->first();

            if (! $ingredient) {
                $ingredient = Ingredient::create([
                    'company_id'     => $companyId,
                    'name'           => $line['ingredient_name'],
                    'base_uom_id'    => $line['uom_id'],
                    'recipe_uom_id'  => $line['uom_id'],
                    'purchase_price' => $line['unit_price'],
                    'pack_size'      => 1,
                    'yield_percent'  => 100,
                    'current_cost'   => $line['unit_price'],
                    'is_active'      => true,
                ]);
                $added++;
            }

            // Create or update supplier-ingredient mapping
            SupplierIngredient::updateOrCreate(
                ['supplier_id' => $supplierId, 'ingredient_id' => $ingredient->id],
                ['last_cost' => $line['unit_price'], 'uom_id' => $line['uom_id'], 'is_preferred' => false]
            );
            $linked++;
        }

        $this->showAddModal = false;
        $this->addLines = [];

        $parts = [];
        if ($added > 0) $parts[] = "{$added} new ingredient(s) added";
        if ($linked > 0) $parts[] = "{$linked} supplier mapping(s) created";

        session()->flash('success', implode(', ', $parts) . '.');
    }

    public function render()
    {
        $this->rfq = QuotationRequest::with([
            'lines.ingredient.baseUom',
            'lines.uom',
            'suppliers.supplier',
            'suppliers.quotation.lines.ingredient',
            'suppliers.quotation.lines.uom',
            'createdBy',
        ])->findOrFail($this->rfqId);

        $quotations = $this->rfq->suppliers
            ->filter(fn ($rqs) => $rqs->quotation !== null)
            ->map(fn ($rqs) => $rqs->quotation);

        $lowestPrices = [];
        foreach ($this->rfq->lines as $line) {
            $prices = [];
            foreach ($quotations as $q) {
                $qLine = $q->lines->firstWhere('ingredient_id', $line->ingredient_id);
                if ($qLine && floatval($qLine->unit_price) > 0) {
                    $prices[$q->id] = floatval($qLine->unit_price);
                }
            }
            if (! empty($prices)) {
                $minPrice = min($prices);
                $lowestPrices[$line->ingredient_id] = array_keys(array_filter(
                    $prices, fn ($p) => abs($p - $minPrice) < 0.0001
                ));
            }
        }

        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)->orderBy('name')->get();
        $cpus = CentralPurchasingUnit::where('is_active', true)->get();

        return view('livewire.purchasing.rfq-show', [
            'quotations'   => $quotations,
            'lowestPrices' => $lowestPrices,
            'outlets'      => $outlets,
            'cpus'         => $cpus,
        ])->layout('layouts.app', ['title' => 'RFQ: ' . $this->rfq->rfq_number]);
    }
}
