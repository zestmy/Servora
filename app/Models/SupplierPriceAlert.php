<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPriceAlert extends Model
{
    protected $fillable = [
        'company_id', 'ingredient_id', 'supplier_id',
        'alert_type', 'threshold_percent', 'threshold_amount',
        'is_active', 'last_triggered_at',
    ];

    protected $casts = [
        'threshold_percent' => 'decimal:2',
        'threshold_amount'  => 'decimal:4',
        'is_active'         => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }

    public function scopeActive($query) { return $query->where('is_active', true); }
}
