<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportLog extends Model
{
    protected $fillable = [
        'subscription_id',
        'company_id',
        'outlet_id',
        'report_type',
        'report_date',
        'period_start',
        'period_end',
        'delivery_channel',
        'recipient_email',
        'delivery_status',
        'report_data',
        'ai_insights',
        'error_message',
        'sent_at',
        'opened_at',
    ];

    protected $casts = [
        'report_date'  => 'date',
        'period_start' => 'date',
        'period_end'   => 'date',
        'report_data'  => 'array',
        'ai_insights'  => 'array',
        'sent_at'      => 'datetime',
        'opened_at'    => 'datetime',
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

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ReportSubscription::class, 'subscription_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeStatus($query, string $status)
    {
        return $query->where('delivery_status', $status);
    }

    public function scopeSent($query)
    {
        return $query->where('delivery_status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('delivery_status', 'failed');
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('report_date', $date);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function markAsSent(): void
    {
        $this->update([
            'delivery_status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'delivery_status' => 'failed',
            'error_message' => $error,
        ]);
    }

    public function markAsOpened(): void
    {
        if (!$this->opened_at) {
            $this->update([
                'delivery_status' => 'opened',
                'opened_at' => now(),
            ]);
        }
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->delivery_status) {
            'sent'    => 'bg-green-100 text-green-700',
            'opened'  => 'bg-blue-100 text-blue-700',
            'failed'  => 'bg-red-100 text-red-700',
            'pending' => 'bg-amber-100 text-amber-700',
            default   => 'bg-gray-100 text-gray-700',
        };
    }
}
