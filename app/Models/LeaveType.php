<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of leave a company grants — annual, replacement public holiday,
 * paternity, compassionate, and anything else it chooses to track.
 *
 * Data rather than an enum, because the list differs by company and adding
 * "marriage leave" should not need a deploy.
 */
class LeaveType extends Model
{
    protected $fillable = [
        'company_id', 'name', 'code', 'description',
        'is_paid', 'is_claimable', 'is_replacement_holiday', 'is_annual', 'requires_approval', 'allows_half_day',
        'default_days', 'carry_forward', 'carry_forward_cap',
        'colour', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_paid'                => 'boolean',
        'is_claimable'           => 'boolean',
        'is_replacement_holiday' => 'boolean',
        // Which type the company-wide annual rules govern — pro-rating and the
        // probation gate. See AnnualLeaveRules.
        'is_annual'              => 'boolean',
        'requires_approval'      => 'boolean',
        'allows_half_day'        => 'boolean',
        'carry_forward'          => 'boolean',
        'is_active'              => 'boolean',
        'default_days'           => 'decimal:1',
        'carry_forward_cap'      => 'decimal:1',
        'sort_order'             => 'integer',
    ];

    /** Seeded for a new company — the common Malaysian set, all editable. */
    public const STARTER_TYPES = [
        ['name' => 'Annual Leave',              'code' => 'AL',  'default_days' => 8,  'colour' => 'teal',   'carry_forward' => true],
        ['name' => 'Medical Leave',             'code' => 'MC',  'default_days' => 14, 'colour' => 'amber'],
        ['name' => 'Replacement Public Holiday','code' => 'RPH', 'default_days' => 0,  'colour' => 'blue', 'is_replacement_holiday' => true],
        ['name' => 'Paternity Leave',           'code' => 'PL',  'default_days' => 7,  'colour' => 'indigo'],
        ['name' => 'Maternity Leave',           'code' => 'ML',  'default_days' => 98, 'colour' => 'pink'],
        ['name' => 'Compassionate Leave',       'code' => 'CL',  'default_days' => 3,  'colour' => 'slate'],
        ['name' => 'Unpaid Leave',              'code' => 'UL',  'default_days' => 0,  'colour' => 'gray', 'is_paid' => false],
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(LeaveEntitlement::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Why this type cannot be applied for AT ALL, or null when it can.
     *
     * Returned as a SENTENCE rather than a boolean because the apply form has
     * to explain itself — "RPH is paid with your salary" is the answer, and
     * a greyed-out option with no reason just looks broken.
     *
     * Company-wide reasons only. A replacement holiday type may also be
     * unavailable to one PERSON on one DAY because they have no unspent credit
     * left; that is not knowable from the type, and
     * @see \App\Services\Hr\ReplacementHolidays::blockedReason() answers it.
     */
    public function blockedReason(): ?string
    {
        if (! $this->is_active) {
            return 'This leave type is no longer in use.';
        }

        if (! $this->is_claimable) {
            return 'This entitlement is paid out with salary and cannot be taken as leave.';
        }

        return null;
    }

    /**
     * Ensure at most one type per company is the replacement-holiday one.
     *
     * Two would make "how many replacement days do I have" ambiguous, and the
     * balance service picks the first it finds — so the answer would depend on
     * sort order, which nobody would ever guess was the cause.
     */
    public static function makeReplacementHoliday(self $type): void
    {
        static::withoutGlobalScopes()
            ->where('company_id', $type->company_id)
            ->where('id', '!=', $type->id)
            ->update(['is_replacement_holiday' => false]);

        $type->forceFill(['is_replacement_holiday' => true])->save();
    }

    /**
     * Mark the one type the annual leave rules govern, clearing it elsewhere.
     *
     * Exclusive for the same reason as the replacement holiday above: two
     * annual types would make pro-rating and the probation gate apply to
     * whichever the code happened to look at, which is not something anybody
     * would guess was the cause when one employee's balance came out wrong.
     */
    public static function makeAnnual(self $type): void
    {
        static::withoutGlobalScopes()
            ->where('company_id', $type->company_id)
            ->where('id', '!=', $type->id)
            ->update(['is_annual' => false]);

        $type->forceFill(['is_annual' => true])->save();
    }

    /** The type the annual rules apply to, if the company has marked one. */
    public static function annualFor(int $companyId): ?self
    {
        return static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_annual', true)
            ->first();
    }

    /** This company's replacement-holiday type, if it has one. */
    public static function replacementHolidayFor(int $companyId): ?self
    {
        return static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_replacement_holiday', true)
            ->active()
            ->first();
    }

    /** Create the starter set for a company that has none. */
    public static function seedDefaults(int $companyId): void
    {
        if (static::withoutGlobalScopes()->where('company_id', $companyId)->exists()) {
            return;
        }

        foreach (self::STARTER_TYPES as $i => $type) {
            static::withoutGlobalScopes()->create(array_merge([
                'company_id'  => $companyId,
                'sort_order'  => $i,
                'is_paid'     => true,
                'is_claimable' => true,
            ], $type));
        }
    }
}
