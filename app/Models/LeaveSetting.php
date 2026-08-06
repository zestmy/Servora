<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Company-wide leave rules, one row per company.
 *
 * Currently just the default claim window for a replacement public holiday —
 * the answer to "how long do I have to take it?", which HR sets once rather
 * than on each of the sixteen holidays in a Malaysian year.
 */
class LeaveSetting extends Model
{
    /** Claimable for a fixed number of days after the holiday. */
    public const EXPIRY_DAYS = 'days';

    /** Claimable until 31 December of the holiday's own year. */
    public const EXPIRY_YEAR_END = 'year_end';

    /** No deadline at all. */
    public const EXPIRY_NEVER = 'never';

    public const EXPIRY_MODES = [
        self::EXPIRY_DAYS     => 'Within a set number of days',
        self::EXPIRY_YEAR_END => 'Until the end of the holiday’s year',
        self::EXPIRY_NEVER    => 'No deadline',
    ];

    protected $fillable = [
        'company_id', 'rph_expiry_mode', 'rph_expiry_days',
    ];

    protected $casts = [
        'rph_expiry_days' => 'integer',
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
     * The scope is dropped and company_id matched by hand because the staff
     * app reads this with no authenticated web user — CompanyScope would match
     * nothing and firstOrCreate would mint a duplicate row every time. The
     * unique index on company_id is the backstop.
     *
     * The defaults are spelled out here as well as on the columns. A
     * firstOrCreate returns the model it BUILT, not a row read back, so on the
     * request that creates it every column defaulted in the database is still
     * zero or null in memory — which made the first visit compute a nought-day
     * claim window and expire every credit the moment it was earned.
     */
    public static function forCompany(int $companyId): self
    {
        return static::withoutGlobalScope(CompanyScope::class)
            ->firstOrCreate(['company_id' => $companyId], [
                'rph_expiry_mode' => self::EXPIRY_DAYS,
                'rph_expiry_days' => 90,
            ]);
    }

    /** How the window reads in a sentence, for the settings screen. */
    public function windowLabel(): string
    {
        return match ($this->rph_expiry_mode) {
            self::EXPIRY_YEAR_END => 'until 31 December of the holiday’s year',
            self::EXPIRY_NEVER    => 'with no deadline',
            default               => 'within ' . (int) $this->rph_expiry_days . ' days of the holiday',
        };
    }
}
