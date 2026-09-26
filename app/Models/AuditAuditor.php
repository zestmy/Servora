<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee appointed as an auditor for the company. See the migration.
 */
class AuditAuditor extends Model
{
    protected $fillable = ['company_id', 'employee_id', 'appointed_by'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function appointedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appointed_by');
    }

    /**
     * The appointed auditors of a company as employees, active ones only,
     * in name order — what an `auditor` header field offers.
     */
    public static function employeesFor(int $companyId): \Illuminate\Support\Collection
    {
        return Employee::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('id', static::withoutGlobalScopes()->where('company_id', $companyId)->select('employee_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'designation', 'outlet_id']);
    }
}
