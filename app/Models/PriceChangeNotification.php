<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceChangeNotification extends Model
{
    protected $fillable = [
        'company_id', 'ingredient_id', 'supplier_id',
        'old_price', 'new_price', 'change_percent', 'change_amount',
        'direction', 'is_read', 'is_dismissed', 'detected_at',
    ];

    protected $casts = [
        'old_price'       => 'decimal:4',
        'new_price'       => 'decimal:4',
        'change_percent'  => 'decimal:2',
        'change_amount'   => 'decimal:4',
        'is_read'         => 'boolean',
        'is_dismissed'    => 'boolean',
        'detected_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }

    public function scopeUnread($query) { return $query->where('is_read', false)->where('is_dismissed', false); }
}
