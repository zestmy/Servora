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
     * Strict check: can this user approve a claim whose employee is in the
     * given outlet + section?
     *
     * Matching rules:
     * - approver.outlet_id  IS NULL (any outlet)  OR = $outletId.
     * - approver.section_id IS NULL (any section) OR = $sectionId.
     *
     * If $sectionId is null (employee has no section), only approvers with
     * section_id NULL match — so a "FOH only" approver can't pick up a
     * sectionless employee.
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

    /**
     * Looser "is this user an approver anywhere at this outlet?" check,
     * used for UI gating (show the Approver columns, bulk-approve bar, etc.)
     * Ignores section entirely — per-claim section matching is handled by
     * isApproverFor() when the actual approve action runs.
     */
    public static function isApproverAtOutlet(int $userId, ?int $outletId): bool
    {
        return static::where('user_id', $userId)
            ->where(function ($q) use ($outletId) {
                $q->whereNull('outlet_id')
                  ->orWhere('outlet_id', $outletId);
            })
            ->exists();
    }

    /**
     * Fetch all approver rows for a user that could match claims at an
     * outlet. Callers then iterate claims and use matchesApprover() for an
     * efficient per-row check without one query per claim.
     */
    public static function scopesForOutlet(int $userId, ?int $outletId)
    {
        return static::where('user_id', $userId)
            ->where(function ($q) use ($outletId) {
                $q->whereNull('outlet_id')
                  ->orWhere('outlet_id', $outletId);
            })
            ->get(['outlet_id', 'section_id']);
    }

    /**
     * Does a pre-fetched collection of approver scopes grant authority over
     * a claim at $outletId with employee in $sectionId?
     */
    public static function scopesMatch($scopes, ?int $outletId, ?int $sectionId): bool
    {
        foreach ($scopes as $s) {
            $outletOk  = $s->outlet_id === null || (int) $s->outlet_id === (int) $outletId;
            if (! $outletOk) continue;

            if ($sectionId === null) {
                if ($s->section_id === null) return true;
            } else {
                if ($s->section_id === null || (int) $s->section_id === (int) $sectionId) return true;
            }
        }
        return false;
    }
}
