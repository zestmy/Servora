<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An allowance or deduction the company can assign to staff. */
class PayComponent extends Model
{
    public const KINDS = [
        'allowance' => 'Allowance',
        'deduction' => 'Deduction',
    ];

    public const CALCULATIONS = [
        'fixed'         => 'Fixed amount',
        'percent_basic' => '% of basic salary',
    ];

    protected $fillable = [
        'company_id', 'name', 'description', 'kind', 'calculation',
        'default_amount', 'is_taxable', 'epf_applicable', 'socso_applicable',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'default_amount'   => 'decimal:2',
        'is_taxable'       => 'boolean',
        'epf_applicable'   => 'boolean',
        'socso_applicable' => 'boolean',
        'is_active'        => 'boolean',
    ];

    /** MySQL does not read column defaults back after an insert. */
    protected $attributes = [
        'kind'             => 'allowance',
        'calculation'      => 'fixed',
        'is_taxable'       => true,
        'epf_applicable'   => true,
        'socso_applicable' => true,
        'sort_order'       => 0,
        'is_active'        => true,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeePayComponent::class);
    }

    public function isDeduction(): bool
    {
        return $this->kind === 'deduction';
    }

    /** The signed value this component contributes, given a basic salary. */
    public function resolveAmount(float $amount, float $basicSalary): float
    {
        $value = $this->calculation === 'percent_basic'
            ? $basicSalary * ($amount / 100)
            : $amount;

        return round($this->isDeduction() ? -$value : $value, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
