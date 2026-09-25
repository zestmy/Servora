<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reminder email about overdue audits or corrective actions. See the
 * migration for the two kinds and the once-a-day rule.
 */
class AuditReminder extends Model
{
    public const KIND_AUDITOR = 'auditor';
    public const KIND_OWNER   = 'owner';

    public const QUEUED = 'queued';
    public const SENT   = 'sent';
    public const FAILED = 'failed';

    protected $fillable = [
        'company_id', 'kind', 'user_id', 'employee_id', 'email', 'recipient_name',
        'sent_on', 'summary', 'status', 'sent_at', 'error',
    ];

    protected $casts = [
        'sent_on' => 'date',
        'summary' => 'array',
        'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
