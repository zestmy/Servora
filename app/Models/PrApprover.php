<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrApprover extends Model
{
    protected $fillable = ['company_id', 'outlet_id', 'department_id', 'user_id', 'assigned_by'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public static function isApproverFor(int $userId, int $outletId, ?int $departmentId = null): bool
    {
        $query = static::where('user_id', $userId)
            ->where('outlet_id', $outletId);

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        return $query->exists();
    }

    public static function approverOutletIds(int $userId): array
    {
        return static::where('user_id', $userId)
            ->pluck('outlet_id')
            ->unique()
            ->toArray();
    }

    public static function scopeApprovablePrs($query, int $userId): void
    {
        $assignments = static::where('user_id', $userId)
            ->select('outlet_id', 'department_id')
            ->get();

        $outletIds = $assignments->pluck('outlet_id')->unique()->toArray();

        $query->where(function ($q) use ($outletIds, $assignments) {
            $q->where(function ($sub) use ($outletIds) {
                $sub->whereIn('outlet_id', $outletIds)
                    ->whereNull('department_id');
            });

            foreach ($assignments as $a) {
                $q->orWhere(function ($sub) use ($a) {
                    $sub->where('outlet_id', $a->outlet_id)
                        ->where('department_id', $a->department_id);
                });
            }
        });
    }
}
