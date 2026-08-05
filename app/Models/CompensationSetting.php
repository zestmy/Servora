<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-company overtime rates and the divisors used to turn a monthly salary
 * into an hourly one.
 *
 * Defaults follow the Malaysian Employment Act: ordinary rate of pay is the
 * monthly salary over 26 days, the hourly rate is that over 8 hours, and
 * overtime pays 1.5x on a normal day, 2x on a rest day and 3x on a public
 * holiday. They are editable because contracts and states differ, and because
 * a hardcoded statutory number is the kind of thing that silently goes stale.
 */
class CompensationSetting extends Model
{
    protected $fillable = [
        'company_id',
        'ot_normal_multiplier', 'ot_rest_day_multiplier', 'ot_public_holiday_multiplier',
        'monthly_working_days', 'daily_working_hours',
    ];

    protected $casts = [
        'ot_normal_multiplier'         => 'decimal:2',
        'ot_rest_day_multiplier'       => 'decimal:2',
        'ot_public_holiday_multiplier' => 'decimal:2',
        'monthly_working_days'         => 'integer',
        'daily_working_hours'          => 'decimal:2',
    ];

    protected $attributes = [
        'ot_normal_multiplier'         => 1.50,
        'ot_rest_day_multiplier'       => 2.00,
        'ot_public_holiday_multiplier' => 3.00,
        'monthly_working_days'         => 26,
        'daily_working_hours'          => 8.00,
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
     * The company's settings, or an unsaved default set.
     *
     * Never creates a row on read — a company that has not opened the screen
     * still gets statutory defaults, and the row appears when someone edits.
     */
    public static function forCompany(int $companyId): self
    {
        return static::withoutGlobalScope(CompanyScope::class)
            ->firstWhere('company_id', $companyId)
            ?? new static(['company_id' => $companyId]);
    }

    /** Multiplier for one of the OT claim types. */
    public function multiplierFor(string $otType): float
    {
        return (float) match ($otType) {
            'rest_day'       => $this->ot_rest_day_multiplier,
            'public_holiday' => $this->ot_public_holiday_multiplier,
            default          => $this->ot_normal_multiplier,
        };
    }

    /**
     * Hourly rate for an employee, from their basic salary and pay type.
     * Null when there is no salary on file — an OT figure invented from
     * nothing is worse than no figure.
     */
    public function hourlyRate(?float $basicSalary, ?string $payType): ?float
    {
        if ($basicSalary === null || $basicSalary <= 0) {
            return null;
        }

        $hours = (float) $this->daily_working_hours ?: 8.0;
        $days  = (int) $this->monthly_working_days ?: 26;

        return round(match ($payType) {
            'hourly' => $basicSalary,
            'daily'  => $basicSalary / $hours,
            default  => $basicSalary / $days / $hours,   // monthly
        }, 4);
    }
}
