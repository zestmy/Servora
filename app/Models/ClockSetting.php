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
        'kiosk_allow_pin',
        'auto_approve_flags',
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
        'kiosk_allow_pin'           => 'boolean',
        'auto_approve_flags'        => 'array',
    ];

    /**
     * One row per company, memoised for the life of the request.
     *
     * forCompany() went from being read once per punch to being read once per
     * PUNCH ROW on the staff app's history screen, because the review policy
     * now decides which flags an employee is shown as waiting on. A page of
     * thirty punches was thirty identical queries.
     */
    private static array $cache = [];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        // Any write drops the memo. Without this the settings screen saves,
        // re-reads in the same request, and renders what it just replaced.
        static::saved(fn (self $settings) => self::forget($settings->company_id));
        static::deleted(fn (self $settings) => self::forget($settings->company_id));
    }

    /** Drop the memo for one company, or all of them. */
    public static function forget(?int $companyId = null): void
    {
        if ($companyId === null) {
            self::$cache = [];

            return;
        }

        unset(self::$cache[$companyId]);
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
        if (isset(self::$cache[$companyId])) {
            return self::$cache[$companyId];
        }

        $settings = static::withoutGlobalScope(CompanyScope::class)
            ->firstOrCreate(['company_id' => $companyId]);

        if ($settings->wasRecentlyCreated) {
            $settings->refresh();
        }

        return self::$cache[$companyId] = $settings;
    }

    /**
     * Which flags this company does NOT want a manager asked about.
     *
     * NULL is not an empty list — it means the column was never written, and
     * the company gets the shipped default. An empty ARRAY is a real answer
     * and means the opposite: review everything, including lateness.
     *
     * Unknown keys are dropped on the way out rather than on the way in, so a
     * flag retired in some later release cannot leave a stale string sitting
     * in the policy of every company that ever ticked it.
     */
    public function autoApproveFlags(): array
    {
        if ($this->auto_approve_flags === null) {
            return ClockEvent::DEFAULT_AUTO_APPROVE_FLAGS;
        }

        return array_values(array_intersect(
            $this->auto_approve_flags,
            array_keys(ClockEvent::FLAG_LABELS),
        ));
    }

    /** Whether this flag, on its own, would send a punch to a manager. */
    public function sendsToReview(string $flag): bool
    {
        return ! in_array($flag, $this->autoApproveFlags(), true);
    }

    /** RM charged for one late minute, or null when lateness is free. */
    public function chargesForLateness(): bool
    {
        return (float) $this->late_rate_per_minute > 0;
    }
}
