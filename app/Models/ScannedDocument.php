<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScannedDocument extends Model
{
    protected $fillable = [
        'company_id', 'uploaded_by',
        'original_filename', 'file_path', 'mime_type', 'size_bytes',
        'status', 'error_message',
        'supplier_name_detected', 'supplier_id',
        'document_date_detected', 'effective_date',
        'extracted_items', 'imported_at',
    ];

    protected $casts = [
        'extracted_items'        => 'array',
        'document_date_detected' => 'date',
        'effective_date'         => 'date',
        'imported_at'            => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, ['extracted']);
    }
}
