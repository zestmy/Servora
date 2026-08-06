<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user who may approve leave for an outlet, optionally one section of it.
 *
 * Shaped exactly like OvertimeClaimApprover on purpose — same idea, same
 * columns, so nobody has to learn a second one.
 */
class LeaveApprover extends Model
{
    protected $fillable = ['company_id', 'user_id', 'outlet_id', 'section_id'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function outlet(): BelongsTo  { return $this->belongsTo(Outlet::class); }
    public function section(): BelongsTo { return $this->belongsTo(Section::class); }

    /** "Main Kitchen · BOH", or "Every outlet" for a company-wide approver. */
    public function scopeLabel(): string
    {
        $parts = array_filter([
            $this->outlet?->name ?? 'Every outlet',
            $this->section?->name,
        ]);

        return implode(' · ', $parts);
    }
}
