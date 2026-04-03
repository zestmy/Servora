<?php

namespace App\Livewire\Purchasing;

use App\Models\AiInvoiceScan;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierItemAlias;
use App\Models\UnitOfMeasure;
use App\Services\AiInvoiceExtractionService;
use App\Services\InvoiceMatchingService;
use App\Services\ProcurementInvoiceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class InvoiceReceive extends Component
{
    use WithFileUploads;

    // Step control: 1=upload, 3=review, 4=complete
    public int $step = 1;

    // Upload
    public $invoiceFile;

    // Scan record
    public ?int $scanId = null;

    // Review header
    public ?int $selectedSupplierId = null;
    public ?int $selectedPoId = null;
    public ?int $selectedGrnId = null;
    public string $supplierInvoiceNumber = '';
    public string $issuedDate = '';
    public string $dueDate = '';
    public string $deliveryCharges = '0';
    public string $taxAmount = '0';
    public string $notes = '';
    public string $uploadedFilePath = '';

    // Review lines — each: [description, quantity, uom_id, unit_price, total_price, ingredient_id, match_confidence, po_unit_price, po_quantity, grn_received_qty]
    public array $lines = [];

    // Exceptions from matching
    public array $exceptions = [];

    // Supplier confidence
    public float $supplierConfidence = 0;
    public float $poConfidence = 0;
    public string $matchedPoNumber = '';
    public string $matchedGrnNumber = '';

    // Error
    public string $errorMessage = '';

    // Processing state
    public bool $processing = false;

    public function upload(): void
    {
        $this->validate([
            'invoiceFile' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        $this->processing = true;
        $this->errorMessage = '';

        try {
            $path = $this->invoiceFile->store('invoices', 'public');
            $this->uploadedFilePath = $path;

            $companyId = Auth::user()->company_id;

            // Create scan record
            $scan = AiInvoiceScan::create([
                'company_id'         => $companyId,
                'uploaded_by'        => Auth::id(),
                'original_file_path' => $path,
                'original_file_name' => $this->invoiceFile->getClientOriginalName(),
                'status'             => 'processing',
            ]);
            $this->scanId = $scan->id;

            // AI extraction
            $result = AiInvoiceExtractionService::extract($path);
            $extracted = $result['data'];

            $scan->update([
                'status'         => 'extracted',
                'raw_extraction' => $extracted,
                'ai_model_used'  => $result['model'],
                'input_tokens'   => $result['tokens']['input'],
                'output_tokens'  => $result['tokens']['output'],
            ]);

            // Match against company data
            $matched = InvoiceMatchingService::match($extracted, $companyId);

            $scan->update([
                'status'              => 'matched',
                'matched_data'        => $matched,
                'exceptions'          => $matched['exceptions'],
                'matched_supplier_id' => $matched['supplier']['id'],
                'matched_po_id'       => $matched['purchase_order']['id'],
                'matched_grn_id'      => $matched['grn']['id'],
            ]);

            // Populate review fields
            $this->selectedSupplierId = $matched['supplier']['id'];
            $this->supplierConfidence = $matched['supplier']['confidence'];
            $this->selectedPoId = $matched['purchase_order']['id'];
            $this->poConfidence = $matched['purchase_order']['confidence'];
            $this->matchedPoNumber = $matched['purchase_order']['po_number'] ?? '';
            $this->selectedGrnId = $matched['grn']['id'];
            $this->matchedGrnNumber = $matched['grn']['grn_number'] ?? '';

            $this->supplierInvoiceNumber = $extracted['invoice_number'] ?? '';
            $this->issuedDate = $extracted['invoice_date'] ?? now()->format('Y-m-d');
            $this->dueDate = $extracted['due_date'] ?? '';
            $this->deliveryCharges = strval($extracted['delivery_charges'] ?? 0);
            $this->taxAmount = strval($extracted['tax_amount'] ?? 0);
            $this->notes = $extracted['notes'] ?? '';

            $this->lines = [];
            foreach ($matched['lines'] as $line) {
                $this->lines[] = [
                    'description'      => $line['extracted_description'],
                    'quantity'         => $line['extracted_quantity'],
                    'uom_id'           => $line['matched_uom_id'],
                    'unit_price'       => $line['extracted_unit_price'],
                    'total_price'      => $line['extracted_total_price'],
                    'ingredient_id'    => $line['matched_ingredient_id'],
                    'match_confidence' => $line['match_confidence'],
                    'po_unit_price'    => $line['po_unit_price'],
                    'po_quantity'      => $line['po_quantity'],
                    'grn_received_qty' => $line['grn_received_qty'],
                ];
            }

            $this->exceptions = $matched['exceptions'];
            $this->step = 3;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();

            if ($this->scanId) {
                AiInvoiceScan::withoutGlobalScopes()->where('id', $this->scanId)->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        } finally {
            $this->processing = false;
        }
    }

    public function recalcLine(int $index): void
    {
        if (! isset($this->lines[$index])) return;
        $qty = floatval($this->lines[$index]['quantity']);
        $price = floatval($this->lines[$index]['unit_price']);
        $this->lines[$index]['total_price'] = round($qty * $price, 4);
    }

    public function removeLine(int $index): void
    {
        array_splice($this->lines, $index, 1);
    }

    public function approve(): void
    {
        $this->validate([
            'selectedSupplierId' => 'required|exists:suppliers,id',
            'issuedDate'         => 'required|date',
            'lines'              => 'required|array|min:1',
            'lines.*.quantity'   => 'required|numeric|min:0.0001',
            'lines.*.unit_price' => 'required|numeric|min:0',
        ]);

        $user = Auth::user();
        $scan = AiInvoiceScan::withoutGlobalScopes()->findOrFail($this->scanId);

        $subtotal = collect($this->lines)->sum(fn ($l) => round(floatval($l['quantity']) * floatval($l['unit_price']), 4));

        $headerData = [
            'company_id'              => $user->company_id,
            'outlet_id'               => $user->activeOutletId(),
            'supplier_id'             => $this->selectedSupplierId,
            'purchase_order_id'       => $this->selectedPoId,
            'goods_received_note_id'  => $this->selectedGrnId,
            'supplier_invoice_number' => $this->supplierInvoiceNumber ?: null,
            'issued_date'             => $this->issuedDate,
            'due_date'                => $this->dueDate ?: null,
            'subtotal'                => $subtotal,
            'tax_rate_id'             => null,
            'tax_amount'              => floatval($this->taxAmount),
            'delivery_charges'        => floatval($this->deliveryCharges),
            'total_amount'            => $subtotal + floatval($this->taxAmount) + floatval($this->deliveryCharges),
            'notes'                   => $this->notes ?: null,
        ];

        $lineData = collect($this->lines)->map(fn ($l) => [
            'ingredient_id' => $l['ingredient_id'] ?? null,
            'description'   => $l['description'],
            'quantity'      => floatval($l['quantity']),
            'uom_id'        => $l['uom_id'] ?? null,
            'unit_price'    => floatval($l['unit_price']),
        ])->toArray();

        $invoice = ProcurementInvoiceService::createFromAiScan($headerData, $lineData, $scan);

        // Learn supplier item aliases from user-confirmed matches
        foreach ($this->lines as $line) {
            $ingredientId = $line['ingredient_id'] ?? null;
            $description = $line['description'] ?? '';
            if ($ingredientId && $description) {
                SupplierItemAlias::learn(
                    $user->company_id,
                    $this->selectedSupplierId,
                    $description,
                    (int) $ingredientId
                );
            }
        }

        session()->flash('success', "Invoice {$invoice->invoice_number} created from AI scan.");
        $this->redirectRoute('purchasing.invoices.show', ['id' => $invoice->id]);
    }

    public function reject(): void
    {
        if ($this->scanId) {
            AiInvoiceScan::withoutGlobalScopes()->where('id', $this->scanId)->update(['status' => 'rejected']);
        }

        session()->flash('success', 'Invoice scan rejected.');
        $this->redirectRoute('purchasing.invoices.index');
    }

    public function render()
    {
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);
        $uoms = UnitOfMeasure::orderBy('name')->get(['id', 'name', 'abbreviation']);
        $ingredients = Ingredient::orderBy('name')->get(['id', 'name']);

        $recentPos = [];
        if ($this->selectedSupplierId) {
            $recentPos = PurchaseOrder::where('supplier_id', $this->selectedSupplierId)
                ->whereIn('status', ['approved', 'sent', 'partial', 'received'])
                ->orderByDesc('order_date')
                ->limit(10)
                ->get(['id', 'po_number', 'order_date', 'total_amount']);
        }

        $errorCount = collect($this->exceptions)->where('severity', 'error')->count();
        $warningCount = collect($this->exceptions)->where('severity', 'warning')->count();
        $subtotal = collect($this->lines)->sum(fn ($l) => round(floatval($l['quantity']) * floatval($l['unit_price']), 4));

        return view('livewire.purchasing.invoice-receive', compact(
            'suppliers', 'uoms', 'ingredients', 'recentPos', 'errorCount', 'warningCount', 'subtotal'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'AI Receive Invoice']);
    }
}
