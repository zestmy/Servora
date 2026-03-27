<?php

namespace App\Livewire\Supplier;

use App\Models\QuotationRequestSupplier;
use App\Models\SupplierQuotation;
use App\Models\SupplierQuotationLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class QuotationResponse extends Component
{
    public QuotationRequestSupplier $requestSupplier;

    public string $valid_until = '';
    public string $delivery_charges = '0';
    public string $notes = '';
    public array $lines = [];

    public function mount(int $quotation_request_supplier_id): void
    {
        $supplierId = Auth::guard('supplier')->user()->supplier_id;

        $this->requestSupplier = QuotationRequestSupplier::where('id', $quotation_request_supplier_id)
            ->where('supplier_id', $supplierId)
            ->where('status', 'pending')
            ->with([
                'quotationRequest' => fn ($q) => $q->withoutGlobalScopes(),
                'quotationRequest.lines.ingredient',
                'quotationRequest.lines.uom',
            ])
            ->firstOrFail();

        $rfq = $this->requestSupplier->quotationRequest;

        $this->valid_until = $rfq->needed_by_date?->format('Y-m-d') ?? now()->addDays(30)->format('Y-m-d');

        foreach ($rfq->lines as $line) {
            $this->lines[] = [
                'request_line_id' => $line->id,
                'ingredient_id'   => $line->ingredient_id,
                'ingredient_name' => $line->ingredient?->name ?? '—',
                'quantity'        => (float) $line->quantity,
                'uom_id'         => $line->uom_id,
                'uom_name'       => $line->uom?->abbreviation ?? $line->uom?->name ?? '—',
                'unit_price'     => '',
                'price_type'     => 'listed',
                'discount_percent' => '0',
                'notes'          => '',
            ];
        }
    }

    public function submit(): void
    {
        $this->validate([
            'valid_until'                 => 'required|date|after_or_equal:today',
            'delivery_charges'            => 'required|numeric|min:0',
            'lines'                       => 'required|array|min:1',
            'lines.*.unit_price'          => 'required|numeric|min:0.0001',
            'lines.*.price_type'          => 'required|in:listed,discounted,tender',
            'lines.*.discount_percent'    => 'nullable|numeric|min:0|max:100',
        ]);

        DB::transaction(function () {
            $supplierId = Auth::guard('supplier')->user()->supplier_id;
            $rfq = $this->requestSupplier->quotationRequest;

            // Calculate subtotal
            $subtotal = 0;
            foreach ($this->lines as $line) {
                $unitPrice = (float) $line['unit_price'];
                $qty = (float) $line['quantity'];
                $discount = $line['price_type'] === 'discounted' ? (float) ($line['discount_percent'] ?? 0) : 0;
                $effectivePrice = $unitPrice * (1 - $discount / 100);
                $subtotal += $effectivePrice * $qty;
            }

            $deliveryCharges = (float) $this->delivery_charges;
            $totalAmount = $subtotal + $deliveryCharges;

            // Create SupplierQuotation
            $quotation = SupplierQuotation::create([
                'quotation_request_id'          => $rfq->id,
                'quotation_request_supplier_id' => $this->requestSupplier->id,
                'supplier_id'                   => $supplierId,
                'quotation_number'              => SupplierQuotation::generateNumber(),
                'status'                        => 'submitted',
                'valid_until'                   => $this->valid_until,
                'subtotal'                      => $subtotal,
                'tax_rate_id'                   => null,
                'tax_amount'                    => 0,
                'delivery_charges'              => $deliveryCharges,
                'total_amount'                  => $totalAmount,
                'notes'                         => $this->notes ?: null,
                'submitted_at'                  => now(),
            ]);

            // Create lines
            foreach ($this->lines as $line) {
                $unitPrice = (float) $line['unit_price'];
                $qty = (float) $line['quantity'];
                $discount = $line['price_type'] === 'discounted' ? (float) ($line['discount_percent'] ?? 0) : 0;
                $effectivePrice = $unitPrice * (1 - $discount / 100);

                SupplierQuotationLine::create([
                    'supplier_quotation_id'      => $quotation->id,
                    'quotation_request_line_id'  => $line['request_line_id'],
                    'ingredient_id'              => $line['ingredient_id'],
                    'quantity'                   => $qty,
                    'uom_id'                     => $line['uom_id'],
                    'unit_price'                 => $unitPrice,
                    'total_price'                => $effectivePrice * $qty,
                    'price_type'                 => $line['price_type'],
                    'discount_percent'           => $discount > 0 ? $discount : null,
                    'notes'                      => $line['notes'] ?: null,
                ]);
            }

            // Update QuotationRequestSupplier
            $this->requestSupplier->update([
                'status'       => 'quoted',
                'responded_at' => now(),
            ]);

            // Update QuotationRequest status
            $allSuppliers = QuotationRequestSupplier::where('quotation_request_id', $rfq->id)->count();
            $respondedCount = QuotationRequestSupplier::where('quotation_request_id', $rfq->id)
                ->where('status', 'quoted')
                ->count();

            $rfq->withoutGlobalScopes()->where('id', $rfq->id)->update([
                'status' => $respondedCount >= $allSuppliers ? 'fully_quoted' : 'partial_response',
            ]);
        });

        session()->flash('message', 'Quotation submitted successfully.');
        $this->redirect(route('supplier.quotations'), navigate: true);
    }

    public function render()
    {
        return view('livewire.supplier.quotation-response')
            ->layout('layouts.supplier', ['title' => 'Submit Quotation']);
    }
}
