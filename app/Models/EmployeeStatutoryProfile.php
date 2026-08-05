<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's statutory scheme numbers and the inputs that no company-wide
 * default can answer — whether each contribution applies to them, and the PCB
 * category, children and zakat that decide their monthly tax deduction.
 */
class EmployeeStatutoryProfile extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_malaysian'   => 'boolean',
        'epf_enabled'    => 'boolean',
        'socso_enabled'  => 'boolean',
        'eis_enabled'    => 'boolean',
        'pcb_enabled'    => 'boolean',
        'epf_employee_rate_override' => 'decimal:2',
        'children'            => 'integer',
        'monthly_zakat'       => 'decimal:2',
        'annual_other_relief' => 'decimal:2',
    ];

    protected $attributes = [
        'is_malaysian'  => true,
        'epf_enabled'   => true,
        'socso_enabled' => true,
        'eis_enabled'   => true,
        'pcb_enabled'   => true,
        'pcb_category'  => 'single',
        'children'      => 0,
        'monthly_zakat' => 0,
        'annual_other_relief' => 0,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** An employee's profile, or unsaved defaults so calculation never blocks. */
    public static function forEmployee(Employee $employee): self
    {
        return static::withoutGlobalScope(CompanyScope::class)
            ->firstWhere('employee_id', $employee->id)
            ?? new static(['company_id' => $employee->company_id, 'employee_id' => $employee->id]);
    }
}
