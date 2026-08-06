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
        'kiosk_enabled', 'byod_enabled',
        'kiosk_face_threshold', 'kiosk_face_margin', 'kiosk_cooldown_minutes',
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
        'kiosk_enabled'             => 'boolean',
        'byod_enabled'              => 'boolean',
        'kiosk_face_threshold'      => 'decimal:3',
        'kiosk_face_margin'         => 'decimal:3',
        'kiosk_cooldown_minutes'    => 'integer',
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
     *
     * THE REFRESH IS NOT OPTIONAL. Every column here has its default in the
     * database, and firstOrCreate INSERTs company_id alone — so the model it
     * hands back on the create path has null for all of them. It is only ever
     * wrong once per company, on the very first read, which is exactly why it
     * survived: any screen anybody looked at had already created the row.
     *
     * What it did on that one read was not cosmetic. face_threshold null
     * casts to 0.0, and no two captures of the same face are ever zero apart,
     * so EVERY punch came back a mismatch — on the first day of the first
     * company to use the clock, which is the worst possible day for it. The
     * kiosk's own thresholds would have failed the same way, and its cooldown
     * would have been zero.
     */
    public static function forCompany(int $companyId): self
    {
        $settings = static::withoutGlobalScope(CompanyScope::class)
            ->firstOrCreate(['company_id' => $companyId]);

        if ($settings->wasRecentlyCreated) {
            $settings->refresh();
        }

        return $settings;
    }

    /** RM charged for one late minute, or null when lateness is free. */
    public function chargesForLateness(): bool
    {
        return (float) $this->late_rate_per_minute > 0;
    }
}
