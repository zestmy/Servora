<?php

namespace App\Livewire\Purchasing;

use App\Models\QuotationRequest;
use App\Models\SupplierQuotation;
use App\Services\RfqService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RfqShow extends Component
{
    public int $rfqId;
    public ?QuotationRequest $rfq = null;

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

        // Build comparison data
        $quotations = $this->rfq->suppliers
            ->filter(fn ($rqs) => $rqs->quotation !== null)
            ->map(fn ($rqs) => $rqs->quotation);

        // Find lowest price per ingredient across all quotations
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
                // Store all quotation IDs that have the lowest price
                $lowestPrices[$line->ingredient_id] = array_keys(array_filter(
                    $prices,
                    fn ($p) => abs($p - $minPrice) < 0.0001
                ));
            }
        }

        return view('livewire.purchasing.rfq-show', [
            'quotations'   => $quotations,
            'lowestPrices' => $lowestPrices,
        ])->layout('layouts.app', ['title' => 'RFQ: ' . $this->rfq->rfq_number]);
    }
}
