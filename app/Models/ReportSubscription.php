<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportSubscription extends Model
{
    protected $fillable = [
        'company_id',
        'outlet_id',
        'user_id',
        'report_type',
        'frequency',
        'delivery_channel',
        'delivery_time',
        'delivery_day',
        'is_active',
        'include_ai_insights',
        'recipient_emails',
        'last_sent_at',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'include_ai_insights' => 'boolean',
        'delivery_time'      => 'datetime:H:i',
        'last_sent_at'       => 'datetime',
        'recipient_emails'   => 'array',
    ];

    /**
     * Get all recipient emails (includes owner if no custom recipients set).
     */
    public function getRecipientEmails(): array
    {
        if (!empty($this->recipient_emails)) {
            return $this->recipient_emails;
        }

        // Default to the subscription owner's email
        return $this->user ? [$this->user->email] : [];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The single outlet, when there is exactly one.
     *
     * Kept as a derived column rather than removed: ReportLog rows, the
     * data-completeness check and a handful of existing queries all read it,
     * and it is null for both "all outlets" and "several outlets" — which is
     * the same thing those callers already handled.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * The outlets this subscription covers. NO ROWS MEANS ALL OUTLETS —
     * including ones opened after the subscription was set up, which is why
     * "all" is stored as an empty set rather than as every current outlet.
     */
    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'report_subscription_outlet');
    }

    /**
     * The outlet ids to report on, or an empty array for "every outlet".
     *
     * @return array<int, int>
     */
    public function outletIds(): array
    {
        return $this->outlets->pluck('id')->all();
    }

    public function coversAllOutlets(): bool
    {
        return $this->outletIds() === [];
    }

    /** What the list screen shows in the Outlet column. */
    public function outletLabel(): string
    {
        $outlets = $this->outlets;

        if ($outlets->isEmpty()) {
            return 'All Outlets';
        }

        if ($outlets->count() === 1) {
            return (string) $outlets->first()->name;
        }

        // Two names read better than "3 outlets"; beyond that the names stop
        // fitting the column and the count is the useful summary.
        if ($outlets->count() === 2) {
            return $outlets->pluck('name')->implode(', ');
        }

        return $outlets->count() . ' outlets';
    }

    /**
     * Point the pivot at a set of outlets and keep `outlet_id` consistent.
     *
     * One method so the two can never disagree — a row whose pivot says three
     * outlets and whose outlet_id names one of them would report on one outlet
     * and log it as if it covered all three.
     *
     * @param  array<int, int|string>  $outletIds
     */
    public function setOutlets(array $outletIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $outletIds))));

        $this->outlets()->sync($ids);
        $this->forceFill(['outlet_id' => count($ids) === 1 ? $ids[0] : null])->save();
        $this->load('outlets');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ReportLog::class, 'subscription_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForFrequency($query, string $frequency)
    {
        return $query->where('frequency', $frequency);
    }

    public function scopeDueToday($query)
    {
        $now = now();
        $currentTime = $now->format('H:i:s');
        $dayOfWeek = $now->dayOfWeekIso; // 1 = Monday, 7 = Sunday
        $dayOfMonth = $now->day;

        return $query->active()
            ->where('delivery_time', '<=', $currentTime)
            ->where(function ($q) use ($now) {
                // Not sent today
                $q->whereNull('last_sent_at')
                  ->orWhereDate('last_sent_at', '<', $now->toDateString());
            })
            ->where(function ($q) use ($dayOfWeek, $dayOfMonth) {
                // Daily reports
                $q->where('frequency', 'daily')
                  // Weekly reports on the right day
                  ->orWhere(function ($wq) use ($dayOfWeek) {
                      $wq->where('frequency', 'weekly')
                         ->where('delivery_day', $dayOfWeek);
                  })
                  // Monthly reports on the right day
                  ->orWhere(function ($mq) use ($dayOfMonth) {
                      $mq->where('frequency', 'monthly')
                         ->where('delivery_day', $dayOfMonth);
                  });
            });
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function getReportTypeLabel(): string
    {
        return match ($this->report_type) {
            'daily_sales'        => 'Daily Sales Report',
            'weekly_performance' => 'Weekly Performance Report',
            'monthly_summary'    => 'Monthly Summary Report',
            'hr_document_expiry' => 'Staff Document & Training Expiry',
            default              => ucwords(str_replace('_', ' ', $this->report_type)),
        };
    }

    public function getFrequencyLabel(): string
    {
        return match ($this->frequency) {
            'daily'   => 'Daily',
            'weekly'  => 'Weekly',
            'monthly' => 'Monthly',
            default   => ucfirst($this->frequency),
        };
    }

    public function getDeliveryDayLabel(): ?string
    {
        if (!$this->delivery_day) return null;

        if ($this->frequency === 'weekly') {
            return match ($this->delivery_day) {
                1 => 'Monday',
                2 => 'Tuesday',
                3 => 'Wednesday',
                4 => 'Thursday',
                5 => 'Friday',
                6 => 'Saturday',
                7 => 'Sunday',
                default => null,
            };
        }

        if ($this->frequency === 'monthly') {
            return ordinal($this->delivery_day) . ' of month';
        }

        return null;
    }
}
