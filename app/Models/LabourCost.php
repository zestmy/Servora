<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabourCost extends Model
{
    protected $fillable = [
        'company_id', 'outlet_id', 'month', 'department_type',
        'basic_salary', 'service_point', 'overtime', 'epf', 'eis', 'socso',
    ];

    protected $casts = [
        'month'         => 'date',
        'basic_salary'  => 'decimal:2',
        'service_point' => 'decimal:2',
        'overtime'      => 'decimal:2',
        'epf'           => 'decimal:2',
        'eis'           => 'decimal:2',
        'socso'         => 'decimal:2',
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

    public function allowances(): HasMany
    {
        return $this->hasMany(LabourCostAllowance::class);
    }

    public function getDepartmentLabelAttribute(): string
    {
        return $this->department_type === 'foh' ? 'Front of House' : 'Back of House';
    }

    public function getTotalAllowancesAttribute(): float
    {
        return (float) $this->allowances->sum('amount');
    }

    public function getTotalCostAttribute(): float
    {
        return (float) $this->basic_salary
            + (float) $this->service_point
            + (float) $this->overtime
            + (float) $this->epf
            + (float) $this->eis
            + (float) $this->socso
            + $this->total_allowances;
    }
}
