<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTake extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'outlet_id', 'reference_number', 'status',
        'stock_take_date', 'total_variance_cost', 'total_stock_cost', 'notes', 'created_by',
    ];

    protected $casts = [
        'stock_take_date'    => 'date',
        'total_variance_cost' => 'decimal:4',
        'total_stock_cost'   => 'decimal:4',
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

    public function lines(): HasMany
    {
        return $this->hasMany(StockTakeLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
