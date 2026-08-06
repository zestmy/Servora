<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which overtime claim funded which time off, and by how many hours.
 *
 * The audit trail behind the balance. "Which overtime did my Tuesday
 * afternoon come out of" is a question people actually ask, and a running
 * total cannot answer it.
 */
class TimeOffAllocation extends Model
{
    protected $fillable = ['time_off_request_id', 'overtime_claim_id', 'hours'];

    protected $casts = ['hours' => 'decimal:2'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(TimeOffRequest::class, 'time_off_request_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(OvertimeClaim::class, 'overtime_claim_id');
    }
}
