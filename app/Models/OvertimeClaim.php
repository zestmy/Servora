<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OvertimeClaim extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'outlet_id', 'submitted_by', 'employee_id',
        'claim_date', 'ot_time_start', 'ot_time_end', 'total_ot_hours',
        'ot_type', 'reason', 'status',
        'approved_by', 'approved_at', 'rejected_reason',
    ];

    protected $casts = [
        'claim_date'     => 'date',
        'total_ot_hours' => 'decimal:2',
        'approved_at'    => 'datetime',
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

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function otTypeLabel(): string
    {
        return match ($this->ot_type) {
            'normal_day'     => 'Normal Day',
            'public_holiday' => 'Public Holiday',
            'rest_day'       => 'Rest Day',
            default          => ucfirst($this->ot_type),
        };
    }
}
