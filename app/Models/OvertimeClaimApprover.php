<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeClaimApprover extends Model
{
    protected $fillable = ['company_id', 'user_id', 'outlet_id', 'section_id'];

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

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Check whether a user can approve an OT claim at the given outlet and
     * for the given section.
     *
     * Matching rules:
     * - Approver's outlet_id is NULL (any outlet) OR equal to $outletId.
     * - Approver's section_id is NULL (any section) OR equal to $sectionId.
     *
     * $sectionId may be null (employee has no section assigned); in that case
     * only approvers with section_id NULL match — otherwise a "FOH only"
     * approver shouldn't pick up a sectionless employee by accident.
     */
    public static function isApproverFor(int $userId, ?int $outletId, ?int $sectionId = null): bool
    {
        return static::where('user_id', $userId)
            ->where(function ($q) use ($outletId) {
                $q->whereNull('outlet_id')
                  ->orWhere('outlet_id', $outletId);
            })
            ->where(function ($q) use ($sectionId) {
                if ($sectionId === null) {
                    $q->whereNull('section_id');
                } else {
                    $q->whereNull('section_id')
                      ->orWhere('section_id', $sectionId);
                }
            })
            ->exists();
    }
}
