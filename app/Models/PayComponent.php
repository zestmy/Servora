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
        'fixed'            => 'Fixed amount',
        'percent_basic'    => '% of basic salary',
        'per_working_day'  => 'Per working day (from attendance)',
        'attendance_bonus' => 'Fixed, forfeited on MC / absence / lateness',
    ];

    /**
     * Calculations that read the attendance grid, and so only make sense as
     * an allowance: a deduction per working day, or one "forfeited" by good
     * attendance, is not something anybody has asked for and would be easy
     * to set up by accident.
     */
    public const ATTENDANCE_CALCULATIONS = ['per_working_day', 'attendance_bonus'];

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

    public function readsAttendance(): bool
    {
        return in_array($this->calculation, self::ATTENDANCE_CALCULATIONS, true);
    }

    /**
     * What the assigned amount means, for a label beside the figure:
     * "7.50 per working day", "10.00% of basic".
     */
    public function amountSuffix(): string
    {
        return match ($this->calculation) {
            'percent_basic'   => '% of basic',
            'per_working_day' => ' per working day',
            default           => '',
        };
    }

    /**
     * The signed value this component contributes, given a basic salary.
     *
     * For the attendance calculations this is the RATE — one day's worth, or
     * the allowance if it is kept. CompensationSummary applies the attendance
     * to it, because only it has the grid.
     */
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
