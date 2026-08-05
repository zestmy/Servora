<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The rules a clock-in is judged against, one row per company.
 *
 * @see \App\Services\Hr\ClockInService for how each field is applied.
 */
class ClockSetting extends Model
{
    protected $fillable = [
        'company_id', 'grace_minutes', 'late_rate_per_minute', 'late_cap_per_shift',
        'early_window_minutes', 'require_gps', 'require_face', 'require_face_match',
        'max_accuracy_m',
        'face_threshold', 'mark_attendance', 'allow_offsite_with_reason',
        'resolve_addresses',
    ];

    protected $casts = [
        'grace_minutes'             => 'integer',
        'late_rate_per_minute'      => 'decimal:2',
        'late_cap_per_shift'        => 'decimal:2',
        'early_window_minutes'      => 'integer',
        'require_gps'               => 'boolean',
        'require_face'              => 'boolean',
        'require_face_match'        => 'boolean',
        'max_accuracy_m'            => 'integer',
        'face_threshold'            => 'decimal:3',
        'mark_attendance'           => 'boolean',
        'allow_offsite_with_reason' => 'boolean',
        'resolve_addresses'         => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * This company's rules, creating the defaults on first read.
     *
     * Called from the staff app, which runs with no authenticated web user,
     * so CompanyScope would match nothing and firstOrCreate would keep
     * minting duplicate rows. The scope is dropped and company_id matched
     * explicitly instead; the unique index on company_id is the backstop.
     */
    public static function forCompany(int $companyId): self
    {
        return static::withoutGlobalScope(CompanyScope::class)
            ->firstOrCreate(['company_id' => $companyId]);
    }

    /** RM charged for one late minute, or null when lateness is free. */
    public function chargesForLateness(): bool
    {
        return (float) $this->late_rate_per_minute > 0;
    }
}
