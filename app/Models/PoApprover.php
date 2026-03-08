<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoApprover extends Model
{
    protected $fillable = ['company_id', 'outlet_id', 'user_id', 'assigned_by'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Check if a user is an appointed approver for a specific outlet.
     */
    public static function isApproverFor(int $userId, int $outletId): bool
    {
        return static::where('user_id', $userId)
            ->where('outlet_id', $outletId)
            ->exists();
    }

    /**
     * Get all outlet IDs a user is appointed to approve for.
     */
    public static function approverOutletIds(int $userId): array
    {
        return static::where('user_id', $userId)
            ->pluck('outlet_id')
            ->toArray();
    }
}
