<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Service charge pool for one attendance period (company + optional outlet
 * + exact from/to dates), with the per-day deduction percentages used to
 * split it across employees by Service Points entitlement.
 */
class ServiceChargePeriod extends Model
{
    protected $fillable = [
        'company_id', 'outlet_id', 'period_from', 'period_to',
        'amount', 'mc_percent', 'abs_percent',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to'   => 'date',
        'amount'      => 'decimal:2',
        'mc_percent'  => 'decimal:2',
        'abs_percent' => 'decimal:2',
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
}
