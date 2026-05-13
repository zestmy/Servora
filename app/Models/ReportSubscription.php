<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'last_sent_at',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'include_ai_insights' => 'boolean',
        'delivery_time'      => 'datetime:H:i',
        'last_sent_at'       => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
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
