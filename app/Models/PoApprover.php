<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoApprover extends Model
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

    /**
     * Check if a user is an appointed approver for a specific outlet + department.
     *
     * An approver with department_id = null is a wildcard (approves all departments).
     * An approver with a specific department_id only approves that department.
     */
    public static function isApproverFor(int $userId, int $outletId, ?int $departmentId = null): bool
    {
        return static::where('user_id', $userId)
            ->where('outlet_id', $outletId)
            ->where(function ($q) use ($departmentId) {
                $q->whereNull('department_id'); // wildcard approver
                if ($departmentId) {
                    $q->orWhere('department_id', $departmentId);
                }
            })
            ->exists();
    }

    /**
     * Get all outlet IDs a user is appointed to approve for.
     */
    public static function approverOutletIds(int $userId): array
    {
        return static::where('user_id', $userId)
            ->pluck('outlet_id')
            ->unique()
            ->toArray();
    }

    /**
     * Apply a where clause to a PurchaseOrder query to only include POs
     * the given user is appointed to approve (outlet + department match).
     */
    public static function scopeApprovablePos($query, int $userId): void
    {
        $assignments = static::where('user_id', $userId)
            ->select('outlet_id', 'department_id')
            ->get();

        $wildcardOutlets = $assignments->whereNull('department_id')->pluck('outlet_id')->unique()->toArray();
        $deptAssignments = $assignments->whereNotNull('department_id');

        $query->where(function ($q) use ($wildcardOutlets, $deptAssignments) {
            if (! empty($wildcardOutlets)) {
                $q->whereIn('outlet_id', $wildcardOutlets);
            }
            foreach ($deptAssignments as $a) {
                $q->orWhere(function ($sub) use ($a) {
                    $sub->where('outlet_id', $a->outlet_id)
                        ->where('department_id', $a->department_id);
                });
            }
        });
    }
}
