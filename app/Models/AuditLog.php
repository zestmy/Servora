<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable audit-trail entry. Rows are append-only: the model blocks updates
 * and deletes at the application layer (tamper-proofing). Company isolation is
 * enforced by CompanyScope so the viewer only ever surfaces the caller's own
 * company. Administrative retention pruning goes through the raw query builder
 * in the audit:prune command, deliberately bypassing this guard.
 */
class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'outlet_id', 'user_id', 'user_name', 'guard',
        'event', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        // Append-only: reject any attempt to mutate or delete an existing entry.
        static::updating(function () {
            throw new \RuntimeException('Audit logs are immutable and cannot be modified.');
        });
        static::deleting(function () {
            throw new \RuntimeException('Audit logs are immutable and cannot be deleted.');
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Best-available actor name, even after the user row is gone. */
    public function actorName(): string
    {
        return $this->user?->name ?? $this->user_name ?? 'System';
    }
}
