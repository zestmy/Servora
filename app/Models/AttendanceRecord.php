<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'company_id', 'outlet_id', 'employee_id', 'work_date', 'attendance_code_id',
        'hours',
    ];

    protected $casts = [
        'work_date' => 'date',
        'hours'     => 'decimal:2',
    ];

    /**
     * A day worked by somebody paid for the hours, rather than marked present.
     *
     * A cell holds one or the other and never both — see the migration for why.
     */
    public function isHours(): bool
    {
        return $this->hours !== null;
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(AttendanceCode::class, 'attendance_code_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
