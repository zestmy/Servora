<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's record of one catalogue certification.
 *
 * There is at most one row per (employee, course): a renewal updates the dates
 * rather than adding a second row, so "when does this lapse" has one answer.
 */
class EmployeeCertification extends Model
{
    protected $fillable = [
        'company_id', 'employee_id', 'certification_type_id',
        'reference_no', 'issued_on', 'expires_on',
    ];

    protected $casts = [
        'issued_on'  => 'date',
        'expires_on' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CertificationType::class, 'certification_type_id');
    }
}
