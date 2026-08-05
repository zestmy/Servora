<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to email one payslip.
 *
 * A resend creates a SECOND row rather than updating the first: "we sent it
 * twice, the second time to a corrected address" is the answer someone
 * actually needs, and overwriting loses it.
 */
class PayslipDelivery extends Model
{
    public const QUEUED = 'queued';
    public const SENT   = 'sent';
    public const FAILED = 'failed';

    protected $fillable = [
        'company_id', 'payroll_run_id', 'payroll_run_line_id', 'employee_id',
        'email', 'status', 'sent_at', 'error', 'queued_by',
    ];

    protected $casts = ['sent_at' => 'datetime'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(PayrollRunLine::class, 'payroll_run_line_id');
    }

    public function queuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'queued_by');
    }
}
