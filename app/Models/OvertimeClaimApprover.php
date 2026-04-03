<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeClaimApprover extends Model
{
    protected $fillable = ['company_id', 'user_id', 'outlet_id'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * Check whether a user is an OT approver for a given outlet.
     */
    public static function isApproverFor(int $userId, ?int $outletId): bool
    {
        return static::where('user_id', $userId)
            ->where(function ($q) use ($outletId) {
                $q->whereNull('outlet_id')        // global approver
                  ->orWhere('outlet_id', $outletId);
            })
            ->exists();
    }
}
