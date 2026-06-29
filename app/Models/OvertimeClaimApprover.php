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
     * Looser "is this user an approver for any of these outlets?" check,
     * used for UI gating (show the Approver columns, bulk-approve bar, etc.)
     * Ignores section entirely — per-claim section matching is handled by
     * isApproverFor() when the actual approve action runs.
     *
     * Matches wildcard rows (outlet_id NULL) plus any row scoped to one of
     * $outletIds. A cross-outlet user (can_view_all_outlets) passes every
     * company outlet here, so their outlet-specific approver assignments are
     * honoured instead of only wildcard ones.
     */
    public static function isApproverInOutlets(int $userId, array $outletIds): bool
    {
        return static::where('user_id', $userId)
            ->where(function ($q) use ($outletIds) {
                $q->whereNull('outlet_id');
                if (! empty($outletIds)) {
                    $q->orWhereIn('outlet_id', $outletIds);
                }
            })
            ->exists();
    }

    /**
     * Fetch all approver rows for a user that could match claims in any of
     * the given outlets (wildcard rows + rows scoped to those outlets).
     * Callers iterate claims and use scopesMatch() for an efficient per-row
     * check without one query per claim.
     */
    public static function scopesForOutlets(int $userId, array $outletIds)
    {
        return static::where('user_id', $userId)
            ->where(function ($q) use ($outletIds) {
                $q->whereNull('outlet_id');
                if (! empty($outletIds)) {
                    $q->orWhereIn('outlet_id', $outletIds);
                }
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
