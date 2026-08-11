<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-off correction on one employee's line of one payroll run.
 *
 * Held apart from the line because the builder rebuilds every line from
 * scratch on each regenerate — see the migration for why that matters. These
 * rows are the input to that rebuild, not its output.
 */
class PayrollRunAdjustment extends Model
{
    public const DEDUCTION = 'deduction';
    public const ADDITION  = 'addition';

    public const DIRECTIONS = [
        self::DEDUCTION => 'Deduction (take off this payslip)',
        self::ADDITION  => 'Addition (pay on this payslip)',
    ];

    protected $fillable = [
        'company_id', 'payroll_run_id', 'employee_id',
        'label', 'amount', 'direction', 'affects_statutory', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount'            => 'decimal:2',
        'affects_statutory' => 'boolean',
    ];

    protected $attributes = [
        'direction'         => self::DEDUCTION,
        'affects_statutory' => false,
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The amount with its sign — negative for a deduction.
     *
     * The column is always positive and `direction` carries the sign, so the
     * sign is applied in exactly one place. A signed column invites a minus
     * typed into a deduction, which would silently pay the money out instead.
     */
    public function signedAmount(): float
    {
        return round(
            (float) $this->amount * ($this->direction === self::ADDITION ? 1 : -1),
            2
        );
    }

    public function isAddition(): bool
    {
        return $this->direction === self::ADDITION;
    }

    /**
     * The default statutory treatment for a direction, which is what the form
     * pre-selects.
     *
     * Recovering an overpayment is net-only: the contributions were already
     * remitted on the inflated figure last month, and reducing this month's as
     * well would understate a month in which nothing was overpaid. Arrears are
     * the mirror — wages in the month they are paid, so contributions follow.
     *
     * A DEFAULT, not a rule. Both cases have legitimate exceptions, which is
     * why the field exists rather than being derived.
     */
    public static function defaultAffectsStatutory(string $direction): bool
    {
        return $direction === self::ADDITION;
    }

    /** How the payslip and the run screen describe the statutory treatment. */
    public function statutoryLabel(): string
    {
        return $this->affects_statutory
            ? 'counts as wages — EPF, SOCSO and PCB apply'
            : 'after statutory — affects take-home only';
    }
}
