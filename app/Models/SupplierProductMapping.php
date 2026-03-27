<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProductMapping extends Model
{
    protected $fillable = [
        'company_id', 'supplier_product_id', 'ingredient_id',
        'is_verified', 'mapped_by',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function supplierProduct(): BelongsTo { return $this->belongsTo(SupplierProduct::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function mappedBy(): BelongsTo { return $this->belongsTo(User::class, 'mapped_by'); }
}
