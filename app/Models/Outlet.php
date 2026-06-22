<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outlet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'code', 'phone', 'address', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Users assigned to this outlet (via pivot). */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function stockTakes(): HasMany
    {
        return $this->hasMany(StockTake::class);
    }

    /** Recipes tagged to this outlet. */
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class)->withTimestamps();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(OutletGroup::class, 'outlet_outlet_group')->withTimestamps();
    }

    /**
     * Exclude outlets that serve as a central kitchen. Central kitchens handle
     * production rather than retail sales, so they should not appear in sales
     * analytics or emailed sales reports (AI Analytics, scheduled reports).
     */
    public function scopeExcludingCentralKitchens($query)
    {
        return $query->whereNotIn('id', function ($sub) {
            $sub->select('outlet_id')
                ->from('central_kitchens')
                ->whereNotNull('outlet_id')
                ->whereNull('deleted_at');
        });
    }
}
