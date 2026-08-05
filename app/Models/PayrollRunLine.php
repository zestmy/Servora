<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's pay for one run — the snapshot a payslip is printed from.
 *
 * Identity is copied onto the line rather than joined from the employee: a
 * payslip has to reproduce who was paid AS THEY WERE THEN. Someone who
 * transfers outlet in July must not find last month's payslip claiming they
 * were at the new one, and a deleted employee must not take a historical
 * payslip with them.
 */
class PayrollRunLine extends Model
{
    protected $fillable = [
        'payroll_run_id', 'company_id', 'employee_id',
        'employee_name', 'staff_id', 'ic_number', 'designation',
        'outlet_name', 'section_name', 'bank_name', 'bank_account_no',
        'epf_number', 'socso_number', 'income_tax_number',
        'pay_type', 'basic', 'allowances', 'deductions', 'ot_hours', 'ot_amount',
        'gross', 'epf_employee', 'epf_employer', 'socso_employee', 'socso_employer',
        'eis_employee', 'eis_employer', 'pcb', 'statutory_employee', 'statutory_employer',
        'net', 'employer_cost', 'components', 'ot_by_type', 'statutory_notes',
    ];

    protected $casts = [
        'components'         => 'array',
        'ot_by_type'         => 'array',
        'statutory_notes'    => 'array',
        'basic'              => 'decimal:2',
        'allowances'         => 'decimal:2',
        'deductions'         => 'decimal:2',
        'ot_hours'           => 'decimal:2',
        'ot_amount'          => 'decimal:2',
        'gross'              => 'decimal:2',
        'epf_employee'       => 'decimal:2',
        'epf_employer'       => 'decimal:2',
        'socso_employee'     => 'decimal:2',
        'socso_employer'     => 'decimal:2',
        'eis_employee'       => 'decimal:2',
        'eis_employer'       => 'decimal:2',
        'pcb'                => 'decimal:2',
        'statutory_employee' => 'decimal:2',
        'statutory_employer' => 'decimal:2',
        'net'                => 'decimal:2',
        'employer_cost'      => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Allowance lines, for the earnings half of a payslip. */
    public function allowanceLines(): array
    {
        return array_values(array_filter($this->components ?? [], fn ($c) => ($c['kind'] ?? '') === 'allowance'));
    }

    /** Company deduction lines — not the statutory ones, which have their own half. */
    public function deductionLines(): array
    {
        return array_values(array_filter($this->components ?? [], fn ($c) => ($c['kind'] ?? '') === 'deduction'));
    }

    /**
     * What a payslip cannot state without it. Surfaced as a list rather than a
     * boolean so the run screen can say WHICH detail is missing for whom.
     *
     * @return array<int, string>
     */
    public function missingForPayment(): array
    {
        $missing = [];

        if (! $this->bank_name)      { $missing[] = 'bank'; }
        if (! $this->bank_account_no) { $missing[] = 'account number'; }

        return $missing;
    }
}
