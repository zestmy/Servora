<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One public holiday this company recognises.
 *
 * Two facts hang off it. Whether working it earns a day off in lieu
 * (`is_replaceable`) — false means the company pays for the day instead, at
 * the statutory rate, and no credit is issued. And how long that day off stays
 * claimable, which falls back to the company default unless this holiday
 * overrides it.
 *
 * @see \App\Services\Hr\ReplacementHolidays for how credits are derived.
 */
class PublicHoliday extends Model
{
    protected $fillable = [
        'company_id', 'date', 'name', 'is_replaceable',
        'expiry_mode', 'expiry_days', 'notes', 'is_active', 'created_by',
    ];

    protected $casts = [
        'date'           => 'date',
        'is_replaceable' => 'boolean',
        'is_active'      => 'boolean',
        'expiry_days'    => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    /**
     * The outlets that observe it — EMPTY MEANS ALL OF THEM.
     *
     * Storing the exception rather than the rule keeps federal holidays to
     * zero clicks and means a newly opened outlet observes everything already
     * in the register instead of nothing.
     */
    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'public_holiday_outlet');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeReplaceable($query)
    {
        return $query->where('is_replaceable', true);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->whereBetween('date', ["{$year}-01-01", "{$year}-12-31"]);
    }

    /**
     * Does this holiday apply at the given outlet?
     *
     * Expects `outlets` to be loaded — the callers all iterate a year's worth
     * of holidays over a list of employees, so a lazy load here would be the
     * N+1 that makes the leave screen slow.
     */
    public function appliesToOutlet(?int $outletId): bool
    {
        if ($this->outlets->isEmpty()) {
            return true;
        }

        return $outletId !== null
            && $this->outlets->contains(fn (Outlet $o) => (int) $o->id === (int) $outletId);
    }

    /** True when it observes every outlet rather than a named few. */
    public function isCompanyWide(): bool
    {
        return $this->outlets->isEmpty();
    }

    /**
     * The last date a replacement for this holiday may be TAKEN, or null when
     * there is no deadline.
     *
     * The window bounds the day off, not the application: "claimable within 90
     * days" is a promise about when you get your day, and letting somebody
     * apply on day 89 for a date in the following March would make the setting
     * meaningless.
     */
    public function expiresOn(LeaveSetting $settings): ?Carbon
    {
        $mode = $this->expiry_mode ?: $settings->rph_expiry_mode;

        return match ($mode) {
            LeaveSetting::EXPIRY_NEVER    => null,
            LeaveSetting::EXPIRY_YEAR_END => $this->date->copy()->endOfYear()->startOfDay(),
            default => $this->date->copy()->addDays(
                (int) ($this->expiry_days ?: $settings->rph_expiry_days)
            )->startOfDay(),
        };
    }

    /** How this holiday's own window reads, for the register listing. */
    public function windowLabel(LeaveSetting $settings): string
    {
        if (! $this->is_replaceable) {
            return 'Paid, not replaced';
        }

        $expires = $this->expiresOn($settings);
        $suffix  = $this->expiry_mode ? '' : ' (default)';

        return ($expires ? 'Take by ' . $expires->format('j M Y') : 'No deadline') . $suffix;
    }
}
