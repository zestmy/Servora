<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTarget extends Model
{
    protected $fillable = [
        'company_id', 'outlet_id', 'period', 'type',
        'target_revenue', 'target_pax', 'notes', 'created_by',
    ];

    protected $casts = [
        'target_revenue' => 'decimal:2',
        'target_pax'     => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function outlet(): BelongsTo   { return $this->belongsTo(Outlet::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
