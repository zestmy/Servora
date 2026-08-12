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
        // NOT defaulted below, unlike the others: null is a real state here.
        // See contributesToSkbbk().
        'skbbk_enabled'  => 'boolean',
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

    /**
     * Whether SKBBK (LINDUNG 24 Jam) is deducted from this person.
     *
     * The scheme launched mandatory on 1 June 2026, then a Cabinet decision on
     * 10 July let LOCAL employees opt out from 14 July by filing a liability
     * release. FOREIGN workers stayed mandatory under immigration provisions.
     * So the answer depends on the person, and `skbbk_enabled` has three
     * states rather than two:
     *
     *   true   they contribute — a local who chose to stay in
     *   false  they do not — an opt-out, with a release on file
     *   null   nobody has recorded a decision, so the rule decides:
     *          a foreign worker contributes, a local does not
     *
     * The null default errs towards NOT deducting from a local. Taking money
     * from somebody who opted out is harder to undo than missing a
     * contribution from somebody who meant to stay in — the first needs a
     * refund and an apology, the second needs a tick.
     */
    public function contributesToSkbbk(): bool
    {
        return $this->skbbk_enabled ?? ! $this->is_malaysian;
    }

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
