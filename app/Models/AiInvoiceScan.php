<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInvoiceScan extends Model
{
    protected $fillable = [
        'company_id', 'uploaded_by',
        'original_file_path', 'original_file_name',
        'status', 'raw_extraction', 'matched_data', 'exceptions',
        'matched_supplier_id', 'matched_po_id', 'matched_grn_id',
        'procurement_invoice_id',
        'ai_model_used', 'input_tokens', 'output_tokens', 'error_message',
    ];

    protected $casts = [
        'raw_extraction' => 'array',
        'matched_data'   => 'array',
        'exceptions'     => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function matchedSupplier(): BelongsTo { return $this->belongsTo(Supplier::class, 'matched_supplier_id'); }
    public function matchedPo(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'matched_po_id'); }
    public function matchedGrn(): BelongsTo { return $this->belongsTo(GoodsReceivedNote::class, 'matched_grn_id'); }
    public function procurementInvoice(): BelongsTo { return $this->belongsTo(ProcurementInvoice::class); }
}
