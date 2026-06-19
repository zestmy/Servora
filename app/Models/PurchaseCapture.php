<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseCapture extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'outlet_id', 'department_id', 'supplier_id', 'supplier_name',
        'purchase_date', 'amount', 'reference_number', 'notes', 'created_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'amount'        => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Display label for the supplier — linked supplier name, manual name, or dash. */
    public function getSupplierLabelAttribute(): string
    {
        return $this->supplier?->name ?: ($this->supplier_name ?: '—');
    }
}
