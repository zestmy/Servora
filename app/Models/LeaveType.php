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
        'is_paid', 'is_claimable', 'requires_approval', 'allows_half_day',
        'default_days', 'carry_forward', 'carry_forward_cap',
        'colour', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_paid'           => 'boolean',
        'is_claimable'      => 'boolean',
        'requires_approval' => 'boolean',
        'allows_half_day'   => 'boolean',
        'carry_forward'     => 'boolean',
        'is_active'         => 'boolean',
        'default_days'      => 'decimal:1',
        'carry_forward_cap' => 'decimal:1',
        'sort_order'        => 'integer',
    ];

    /** Seeded for a new company — the common Malaysian set, all editable. */
    public const STARTER_TYPES = [
        ['name' => 'Annual Leave',              'code' => 'AL',  'default_days' => 8,  'colour' => 'teal',   'carry_forward' => true],
        ['name' => 'Medical Leave',             'code' => 'MC',  'default_days' => 14, 'colour' => 'amber'],
        ['name' => 'Replacement Public Holiday','code' => 'RPH', 'default_days' => 0,  'colour' => 'blue'],
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
     * Why this type cannot be applied for, or null when it can.
     *
     * Returned as a SENTENCE rather than a boolean because the apply form has
     * to explain itself — "RPH is paid with your salary" is the answer, and
     * a greyed-out option with no reason just looks broken.
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
