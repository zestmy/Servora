<?php

namespace App\Models;

use App\Models\Concerns\PurgesStoredFiles;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScannedDocument extends Model
{
    use PurgesStoredFiles;

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

        // Held on the PRIVATE disk under a per-company folder because these
        // are supplier invoices and delivery notes, so an orphan here is a
        // tenant's paperwork left on the server with nothing pointing at it.
        static::deleted(fn (self $doc) => $doc->purgeOwnedFile('file_path'));
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
