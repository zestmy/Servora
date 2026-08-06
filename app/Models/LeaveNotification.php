<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record of one leave email: to whom, at what address, and whether it went.
 *
 * Same reasoning as PayslipDelivery — "did my manager actually get told" is a
 * question worth being able to answer, and a queued job that dies silently is
 * indistinguishable from one that was never sent unless the row exists first.
 */
class LeaveNotification extends Model
{
    public const QUEUED = 'queued';
    public const SENT   = 'sent';
    public const FAILED = 'failed';

    public const SUBMITTED = 'submitted';
    public const DECIDED   = 'decided';

    protected $fillable = [
        'company_id', 'leave_request_id', 'time_off_request_id',
        'event', 'email', 'recipient_name', 'status', 'sent_at', 'error',
    ];

    protected $casts = ['sent_at' => 'datetime'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function timeOffRequest(): BelongsTo
    {
        return $this->belongsTo(TimeOffRequest::class);
    }
}
