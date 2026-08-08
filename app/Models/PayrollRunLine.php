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
        'outlet_name', 'section_name', 'bank_name', 'bank_account_no', 'bank_account_name',
        'epf_number', 'socso_number', 'income_tax_number',
        'pay_type', 'paid_hours', 'pay_rate',
        'basic', 'allowances', 'deductions', 'ot_hours', 'ot_amount',
        'service_charge', 'service_charge_detail',
        'gross', 'epf_employee', 'epf_employer', 'socso_employee', 'socso_employer',
        'eis_employee', 'eis_employer', 'pcb', 'zakat', 'hrdf_employer',
        'statutory_employee', 'statutory_employer',
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
        'service_charge'     => 'decimal:2',
        'service_charge_detail' => 'array',
        'paid_hours'         => 'decimal:2',
        'pay_rate'           => 'decimal:2',
        'gross'              => 'decimal:2',
        'epf_employee'       => 'decimal:2',
        'epf_employer'       => 'decimal:2',
        'socso_employee'     => 'decimal:2',
        'socso_employer'     => 'decimal:2',
        'eis_employee'       => 'decimal:2',
        'eis_employer'       => 'decimal:2',
        'pcb'                => 'decimal:2',
        'zakat'              => 'decimal:2',
        'hrdf_employer'      => 'decimal:2',
        'statutory_employee' => 'decimal:2',
        'statutory_employer' => 'decimal:2',
        'net'                => 'decimal:2',
        'employer_cost'      => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (self $line) {
            $line->uuid = $line->uuid ?: (string) \Illuminate\Support\Str::uuid();
        });
    }

    /** Payslip URLs carry the UUID — see PayrollRun::getRouteKeyName(). */
    public function getRouteKeyName(): string
    {
        return 'uuid';
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

    /** Paid by the hour, whether or not any hours were found for the period. */
    public function isHourly(): bool
    {
        return $this->pay_type === 'hourly';
    }

    /**
     * Why an hourly line came out at nothing, if it did.
     *
     * Zero is a legitimate answer — somebody who did not work this period is
     * owed nothing — but it is indistinguishable from a grid nobody filled in,
     * and the two want opposite responses. Naming which of the two inputs is
     * missing is the difference between "check this" and "check what".
     *
     * Null when there is nothing to say, so callers can filter on truthiness.
     */
    public function zeroHourReason(): ?string
    {
        if (! $this->isHourly() || (float) $this->basic > 0) {
            return null;
        }

        $noHours = $this->paid_hours === null || (float) $this->paid_hours <= 0;
        $noRate  = $this->pay_rate === null || (float) $this->pay_rate <= 0;

        return match (true) {
            $noHours && $noRate => 'no hours and no rate',
            $noHours            => 'no hours entered',
            default             => 'no hourly rate set',
        };
    }
}
